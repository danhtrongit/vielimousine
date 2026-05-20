<?php

/**
 * The template for displaying `Page Hotel`
 * Template Name: Page Khách Sạn
 * Template Post Type: page
 *
 * @author Vie
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header( 'home' );

if ( have_posts() ) {
	the_post();
}

if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer( 'home' );

	return;
}

$ACF = \HD\Helper::getFields( get_the_ID() );
$hotel_flexible = ! empty( $ACF['hotel_flexible'] ) ? (array) $ACF['hotel_flexible'] : false;
if ( $hotel_flexible ) {

	foreach ( $hotel_flexible as $section ) {
		$acf_fc_layout = $section['acf_fc_layout'] ?? '';

		if ( $acf_fc_layout ) {
			\HD\Helper::blockTemplate( 'template-parts/hotel/' . $acf_fc_layout, $section );
		}
	}
} else {
	\HD\Helper::blockTemplate( 'template-blocks/static-page' );
}
?>

<div class="hotel-general">

	<?php echo do_shortcode('[section_partner]'); ?>
	<?php get_template_part('template-blocks/section-newspapers'); ?>
	<?php get_template_part('template-blocks/section-posts'); ?>
</div>

<?php

// footer
get_footer( 'home' );