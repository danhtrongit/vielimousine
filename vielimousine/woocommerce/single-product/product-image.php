<?php
/**
 * Single Product Image
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 */

use HD\Helper;

defined('ABSPATH') || exit;

// Note: `wc_get_gallery_image_html` was added in WC 3.3.2 and did not exist prior. This check protects against theme overrides being used on older versions of WC.
if (!function_exists('_wc_get_gallery_image_html')) {
	return;
}

global $product;

$columns = apply_filters('woocommerce_product_thumbnails_columns', 6);
$post_thumbnail_id = $product->get_image_id();
$attachment_ids = $product->get_gallery_image_ids();

$wrapper_classes   = apply_filters(
    'woocommerce_single_product_image_gallery_classes',
    [
        'woocommerce-product-gallery',
        'woocommerce-product-gallery-default',
        'woocommerce-product-gallery--' . ($product->get_image_id() ? 'with-images' : 'without-images'),
        'woocommerce-product-gallery--columns-' . absint($columns),
        'images',
        'swiper-product-gallery',
    ]
);

?>
<div class="woocommerce-product-gallery-wrapper">
    <div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $wrapper_classes))); ?>" data-columns="<?php echo esc_attr($columns); ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
        <figure class="woocommerce-product-gallery__wrapper wpg__images">
            <div class="swiper swiper-images">
                <div class="swiper-wrapper">
                    <div class="swiper-slide swiper-images-first">
                        <?php
                        if ( $product->get_image_id() ) :
                            $html = _wc_get_gallery_image_html($post_thumbnail_id, true, false, true);
                        else :
                            $html  = '<div class="woocommerce-product-gallery__image--placeholder">';
                            $html .= sprintf('<img src="%s" alt="%s" class="wp-post-image" />', esc_url(wc_placeholder_img_src('woocommerce_single')), esc_html__('Awaiting product image', 'woocommerce'));
                            $html .= '</div>';
                        endif;

                        echo apply_filters('woocommerce_single_product_image_thumbnail_html', $html, $post_thumbnail_id); // phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
                        ?>
                    </div>
                    <?php
                    if ( $attachment_ids && $product->get_image_id() ) {
                        foreach ( $attachment_ids as $attachment_id ) {
                            echo '<div class="swiper-slide">';
                            echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', _wc_get_gallery_image_html( $attachment_id, true, false, true ), $attachment_id );
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
                <div class="swiper-controls">
                    <div class="swiper-button swiper-button-prev" data-fa=""></div>
                    <div class="swiper-button swiper-button-next" data-fa=""></div>
                </div>
            </div>
        </figure>
        <?php if ( $attachment_ids ) : ?>
        <figure class="woocommerce-product-gallery__wrapper wpg__thumbs">
            <?php
            if ($attachment_ids) :
            ?>
            <div class="swiper swiper-thumbs">
                <div class="swiper-wrapper">
                    <div class="swiper-slide swiper-thumbs-first">
                        <?php
                        if ( $product->get_image_id() ) :
                            $html  = _wc_get_gallery_image_html( $post_thumbnail_id );
                        else :
                            $html  = '<div class="woocommerce-product-gallery__image--placeholder">';
                            $html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'wpa-gallery' ) );
                            $html .= '</div>';
                        endif;

                        echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, $attachment_ids );
                        ?>
                    </div>
                    <?php do_action( 'woocommerce_product_thumbnails' ); ?>
                </div>
                <div class="swiper-controls">
                    <div class="swiper-button swiper-button-prev" data-fa=""></div>
                    <div class="swiper-button swiper-button-next" data-fa=""></div>
                </div>
            </div>
            <?php endif ?>
        </figure>
        <?php endif ?>
    </div>
</div>