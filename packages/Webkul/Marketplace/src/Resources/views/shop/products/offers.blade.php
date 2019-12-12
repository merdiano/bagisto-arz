@extends('marketplace::shop.layouts.master')

@section('page_title')
    {{ trim($product->meta_title) != "" ? $product->meta_title : $product->name }}
@stop

@section('seo')
    <meta name="description" content="{{ trim($product->meta_description) != "" ? $product->meta_description : str_limit(strip_tags($product->description), 120, '') }}"/>
    <meta name="keywords" content="{{ $product->meta_keywords }}"/>
@stop

@section('content-wrapper')

    <?php 
        $baseProduct = $product->parent_id ? $product->parent : $product;

        $productRepository = app('Webkul\Marketplace\Repositories\ProductRepository'); 
    ?>

    {!! view_render_event('bagisto.shop.sellers.products.offers.before', ['product' => $product]) !!}

    <div class="product-offer-container">

        <div class="product">
            <div class="product-information">

                <?php $productBaseImage = app('Webkul\Product\Helpers\ProductImage')->getProductBaseImage($product); ?>

                <div class="product-logo-block">
                    <a href="{{ route('shop.products.index', $baseProduct->url_key) }}" title="{{ $baseProduct->name }}">
                        <img src="{{ $productBaseImage['medium_image_url'] }}" />
                    </a>
                </div>

                <div class="product-information-block">
                    <a href="{{ route('shop.products.index', $baseProduct->url_key) }}" class="product-title">
                        {{ $baseProduct->name }}
                    </a>

                    <div class="price">
                        @include ('shop::products.price', ['product' => $product])
                    </div>

                    @include ('shop::products.view.stock', ['product' => $product])

                    <?php $attributes = []; ?>

                    @if ($baseProduct->type == 'configurable')

                        <div class="options">
                            <?php $options = []; ?>

                            @foreach ($baseProduct->super_attributes as $attribute)
                            
                                @foreach ($attribute->options as $option)

                                    @if ($product->{$attribute->code} == $option->id)

                                        <?php $attributes[$attribute->id] = $option->id; ?>
                                        
                                        <?php array_push($options, $attribute->name . ' : ' . $option->label); ?>

                                    @endif

                                @endforeach

                            @endforeach

                            {{ implode(', ', $options) }}

                        </div>

                    @endif

                </div>
            </div>

            <div class="review-information">

                @include ('shop::products.review', ['product' => $baseProduct])

            </div>
        </div>
        
        <div class="seller-product-list">
            <h2 class="heading">{{ __('marketplace::app.shop.products.more-sellers') }}</h2>

            <div class="content">

                @foreach ($productRepository->getSellerProducts($product) as $sellerProduct)
                
                    <form action="{{ route('cart.add', $baseProduct->id) }}" method="POST">
                        @csrf()
                        <input type="hidden" name="product" value="{{ $baseProduct->id }}">
                        <input type="hidden" name="seller_info[product_id]" value="{{ $sellerProduct->id }}">
                        <input type="hidden" name="seller_info[seller_id]" value="{{ $sellerProduct->seller->id }}">
                        <input type="hidden" name="seller_info[is_owner]" value="0">

                        @if ($baseProduct->type == 'configurable')
                            <input type="hidden" name="selected_configurable_option" value="{{ $product->id }}">

                            @foreach ($attributes as $attributeId => $optionId)
                                <input type="hidden" name="super_attribute[{{$attributeId}}]" value="{{$optionId}}"/>
                            @endforeach
                        @endif

                        <div class="seller-product-item">

                            <div class="product-info-top table">

                                <table>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="profile-logo-block">
                                                    @if ($logo = $sellerProduct->seller->logo_url)
                                                        <img src="{{ $logo }}" />
                                                    @else
                                                        <img src="{{ bagisto_asset('images/default-logo.svg') }}" />
                                                    @endif
                                                </div>

                                                <div class="profile-information-block">
                                                    <a href="{{ route('marketplace.seller.show', $sellerProduct->seller->url) }}" class="shop-title">
                                                        {{ $sellerProduct->seller->shop_title }}
                                                    </a>

                                                    <div class="review-information">

                                                        <?php $reviewRepository = app('Webkul\Marketplace\Repositories\ReviewRepository') ?>

                                                        <span class="stars">
                                                            <span class="icon star-icon"></span>

                                                            {{ 
                                                                __('marketplace::app.shop.products.seller-total-rating', [
                                                                        'avg_rating' => $reviewRepository->getAverageRating($sellerProduct->seller),
                                                                        'total_rating' => $reviewRepository->getTotalRating($sellerProduct->seller),
                                                                    ]) 
                                                            }}
                                                        </span>

                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                @if ($sellerProduct->condition == 'new')
                                                    {{ __('marketplace::app.shop.products.new') }}
                                                @else
                                                    {{ __('marketplace::app.shop.products.used') }}
                                                @endif
                                            </td>

                                            <td>
                                                {{ core()->currency($sellerProduct->price) }}
                                            </td>

                                            <td>
                                                <div class="control-group">
                                                    <input type="text" name="quantity" value="1" class="control">
                                                </div>
                                                
                                                @if ($sellerProduct->haveSufficientQuantity(1))

                                                    <button type="submit" class="btn btn-black btn-lg">
                                                        {{ __('marketplace::app.shop.products.add-to-cart') }}
                                                    </button>
                                                @else

                                                    <div class="stock-status">
                                                        {{ __('marketplace::app.shop.products.out-of-stock') }}
                                                    </div>

                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>

                            <div class="product-info-bottom">
                                <?php $baseSellerProduct = $sellerProduct->parent_id ? $sellerProduct->parent : $sellerProduct; ?>
                                <div class="product">
                                    <div class="product-information">

                                        <?php $productImages = app('Webkul\Product\Helpers\ProductImage')->getGalleryImages($baseSellerProduct); ?>

                                        <div class="product-images-block">
                                            <carousel :per-page="1" pagination-active-color="#979797" pagination-color="#E8E8E8">
                                                @foreach ($productImages as $productImage)
                                                    
                                                    <slide>
                                                        <div class="product-image">
                                                            <img src="{{ $productImage['medium_image_url'] }}" />
                                                        </div>
                                                    </slide>

                                                @endforeach
                                            </carousel>
                                        </div>

                                        <div class="product-information-block">

                                            {{ $baseSellerProduct->description }}

                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </form>
                    
                @endforeach

            </div>

        </div>

    </div>     

    {!! view_render_event('bagisto.shop.sellers.products.offers.after', ['product' => $product]) !!}

@endsection