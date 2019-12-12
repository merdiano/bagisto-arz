<?php

namespace Webkul\Marketplace\Repositories;

use Illuminate\Container\Container as App;
use Webkul\Core\Eloquent\Repository;
use Illuminate\Support\Facades\Event;

/**
 * Seller Invoice Reposotory
 *
 * @author    Jitendra Singh <jitendra@webkul.com>
 * @copyright 2018 Webkul Software Pvt Ltd (http://www.webkul.com)
 */
class InvoiceRepository extends Repository
{
    /**
     * SellerRepository object
     *
     * @var Object
     */
    protected $sellerRepository;

    /**
     * ProductRepository object
     *
     * @var Object
     */
    protected $productRepository;

    /**
     * OrderRepository object
     *
     * @var Object
     */
    protected $orderRepository;

    /**
     * OrderItemRepository object
     *
     * @var Object
     */
    protected $orderItemRepository;

    /**
     * InvoiceItemRepository object
     *
     * @var Object
     */
    protected $invoiceItemRepository;

    /**
     * Create a new repository instance.
     *
     * @param  Webkul\Marketplace\Repositories\SellerRepository      $sellerRepository
     * @param  Webkul\Marketplace\Repositories\ProductRepository     $productRepository
     * @param  Webkul\Marketplace\Repositories\OrderRepository       $orderRepository
     * @param  Webkul\Marketplace\Repositories\OrderItemRepository   $orderItemRepository
     * @param  Webkul\Marketplace\Repositories\InvoiceItemRepository $invoiceItemRepository
     * @param  Illuminate\Container\Container                        $app
     * @return void
     */
    public function __construct(
        SellerRepository $sellerRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository,
        InvoiceItemRepository $invoiceItemRepository,
        App $app
    )
    {
        $this->sellerRepository = $sellerRepository;

        $this->productRepository = $productRepository;

        $this->orderRepository = $orderRepository;

        $this->orderItemRepository = $orderItemRepository;

        $this->invoiceItemRepository = $invoiceItemRepository;

        parent::__construct($app);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Marketplace\Contracts\Invoice';
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        $invoice = $data['invoice'];

        Event::fire('marketplace.sales.invoice.save.before', $data);

        $sellerInvoices = [];
        foreach ($invoice->items()->get() as $item) {
            if (isset($item->additional['seller_info']) && !$item->additional['seller_info']['is_owner']) {
                $seller = $this->sellerRepository->find($item->additional['seller_info']['seller_id']);
                $sellers[] = $this->sellerRepository->find($item->additional['seller_info']['seller_id']);
            } else {
                $seller = $this->productRepository->getSellerByProductId($item->product_id);
                $sellers[] = $this->productRepository->getSellerByProductId($item->product_id);
            }

            if (! $seller)
                continue;

            $sellerOrder = $this->orderRepository->findOneWhere([
                    'order_id' => $invoice->order->id,
                    'marketplace_seller_id' => $seller->id,
                ]);

            if (! $sellerOrder)
                continue;

            $sellerOrderItem = $this->orderItemRepository->findOneWhere([
                    'marketplace_order_id' => $sellerOrder->id,
                    'order_item_id' => $item->order_item->id,
                ]);

            if (! $sellerOrderItem)
                continue;

            $sellerInvoice = $this->findOneWhere([
                    'invoice_id' => $invoice->id,
                    'marketplace_order_id' => $sellerOrder->id,
                ]);

            if (! $sellerInvoice) {
                $sellerInvoices[] = $sellerInvoice = parent::create([
                        'total_qty' => $item->qty,
                        'state' => 'paid',
                        'invoice_id' => $invoice->id,
                        'marketplace_order_id' => $sellerOrder->id,
                    ]);
            } else {
                $sellerInvoice->total_qty += $item->qty;

                $sellerInvoice->save();
            }

            $sellerInvoiceItem = $this->invoiceItemRepository->create([
                    'marketplace_invoice_id' => $sellerInvoice->id,
                    'invoice_item_id' => $item->id,
                ]);

            $this->orderItemRepository->collectTotals($sellerOrderItem);
        }

        foreach ($sellerInvoices as $sellerInvoice) {
            $this->collectTotals($sellerInvoice);
        }

        foreach ($sellers as $seller) {
            foreach ($this->orderRepository->findWhere(['order_id' => $invoice->order->id, 'marketplace_seller_id' => $seller->id]) as $order) {
                $this->orderRepository->collectTotals($order);

                $this->orderRepository->updateOrderStatus($order);
            }
        }

        foreach ($sellerInvoices as $sellerInvoice) {
            Event::fire('marketplace.sales.invoice.save.after', $sellerInvoice);
        }
    }

    /**
     * @param mixed $sellerInvoice
     * @return mixed
     */
    public function collectTotals($sellerInvoice)
    {
        $sellerInvoice->sub_total = $sellerInvoice->base_sub_total = 0;
        $sellerInvoice->tax_amount = $sellerInvoice->base_tax_amount = 0;
        $sellerInvoice->shipping_amount = $sellerInvoice->base_shipping_amount = 0;
        $sellerInvoice->grand_total = $sellerInvoice->base_grand_total = 0;

        foreach ($sellerInvoice->items as $sellerInvoiceItem) {
            $sellerInvoice->sub_total += $sellerInvoiceItem->item->total;
            $sellerInvoice->base_sub_total += $sellerInvoiceItem->item->base_total;

            $sellerInvoice->tax_amount += $sellerInvoiceItem->item->tax_amount;
            $sellerInvoice->base_tax_amount += $sellerInvoiceItem->item->base_tax_amount;
        }

        $sellerInvoice->shipping_amount = $sellerInvoice->order->shipping_amount;
        $sellerInvoice->base_shipping_amount = $sellerInvoice->order->base_shipping_amount;

        if ($sellerInvoice->order->shipping_amount) {
            foreach ($sellerInvoice->order->invoices as $prevInvoice) {
                if ((float) $prevInvoice->shipping_amount) {
                    $sellerInvoice->shipping_amount = 0;
                    $sellerInvoice->base_shipping_amount = 0;
                }
            }
        }

        $sellerInvoice->grand_total = $sellerInvoice->sub_total + $sellerInvoice->tax_amount + $sellerInvoice->shipping_amount;
        $sellerInvoice->base_grand_total = $sellerInvoice->base_sub_total + $sellerInvoice->base_tax_amount + $sellerInvoice->base_shipping_amount;

        $sellerInvoice->save();

        return $sellerInvoice;
    }
}