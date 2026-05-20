<?php

/**
 * The template for displaying `about`
 * Template Name: Page About Us
 * Template Post Type: page
 *
 * @author Gaudev
 */

use HD\Helper;

\defined( 'ABSPATH' ) || die;

// header
get_header();

if ( have_posts() ) {
	the_post();
}

$ACF = \HD\Helper::getFields( get_the_ID() );
$about_flexible_content = ! empty( $ACF['aboutus_flexible'] ) ? (array) $ACF['aboutus_flexible'] : false;
if ( $about_flexible_content ) {

	foreach ( $about_flexible_content as $section ) {
		$acf_fc_layout = $section['acf_fc_layout'] ?? '';

		if ( $acf_fc_layout ) {
			\HD\Helper::blockTemplate( 'template-parts/about-us/' . $acf_fc_layout, $section );
		}
	}
} else {
	\HD\Helper::blockTemplate( 'template-blocks/static-page' );
}

?>

<?php

// footer
get_footer();