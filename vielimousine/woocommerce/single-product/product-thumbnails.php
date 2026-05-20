<?php
/**
 * Single Product Thumbnails
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( '_wc_get_gallery_image_html' ) ) {
	return;
}

global $product;

$attachment_ids = $product->get_gallery_image_ids();

if ( $attachment_ids && $product->get_image_id() ) {
	foreach ( $attachment_ids as $attachment_id ) {
		echo '<div class="swiper-slide">';
		echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', _wc_get_gallery_image_html( $attachment_id, false, true ), $attachment_id ); // phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
