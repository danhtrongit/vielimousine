<?php

/**
 * The template for displaying `contact`
 * Template Name: Page Contact Us
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

// $ACF                = Helper::getFields( get_the_ID() );
$ACF = \HD\Helper::getFields( get_the_ID() );
$phone = get_field('m_hotline','option');
$m_email = get_field('m_email','option');
$link_gmap = get_field('link_gmap','option');
$iframe_map = get_field('iframe_map','option');
?>
<section class="section page-contact-us">
    <?php
    // breadcrumbs
    \HD\Helper::blockTemplate( 'template-blocks/breadcrumbs', [
        'title' => \HD\Helper::primaryTerm( $post )?->name
    ]
    );
    ?>
	<div class="container">
        <div class="iframe-map">
            <?php echo $iframe_map; ?>
        </div>
        <div class="main-content">
            <?php echo get_the_content(); ?>
        </div>
	</div>
</section>
<?php

// footer
get_footer();