<?php
/**
 * The template for displaying archive.
 *
 * @author Vie
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header( 'archive' );

$object = get_queried_object();

/**
 * HOOK: hd_archive_before_action
 */
do_action( 'hd_archive_before_action' );
$css_class = get_field('css_class', 'category_' . $object->term_id);
$sl_hotel_hot_deal = get_field('sl_hotel_hot_deal', 'option');
$term = get_queried_object();

?>
<h1 class="hidden"><?php echo get_the_archive_title(); ?></h1>

<div class="tour-main-content section-padding">
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell cell-content">
                <div class="group-heading">
                    <h2 class="heading-title"><?php echo get_the_archive_title(  ); ?></h2>
                    <div class="desc"><?php echo __('Book ngay thôi!') ?></div>
                </div>
                <?php tour_content_loop(); ?>
            </div>
            <div class="cell cell-sidebar">
                <?php tour_content_sidebar(); ?>
            </div>
        </div>
    </div>
    
</div>

<?php get_template_part('template-parts/tour/section_why_choose'); ?>

<?php

/**
 * HOOK: hd_archive_after_action
 */
do_action( 'hd_archive_after_action' );

// footer
get_footer( 'archive' );