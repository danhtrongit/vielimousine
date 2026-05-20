<?php

/**
 * The template for displaying `thanks`
 * Template Name: Page Cảm ơn
 * Template Post Type: page
 *
 * @author Vie
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
$title = get_field('title');
$content = get_field('content');
?>
<section class="section page-thanks section-padding">
	<div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        }
        if($content){
            echo '<div class="content text-center">'. $content .'</div>';
        } ?>
	</div>
</section>
<?php

// footer
get_footer();