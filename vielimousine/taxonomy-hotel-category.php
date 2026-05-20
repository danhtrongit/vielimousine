<?php
/**
 * The template for displaying archive.
 *
 * @author Gaudev
 */
use HD\Helper;
\defined( 'ABSPATH' ) || die;

// header
get_header( 'archive' );

$object = get_queried_object();

// breadcrumbs
// \HD\Helper::blockTemplate( 'template-blocks/breadcrumbs', [
// 		'title' => get_the_archive_title()
// 	]
// );

/**
 * HOOK: hd_archive_before_action
 */
do_action( 'hd_archive_before_action' );
$css_class = get_field('css_class', 'category_' . $object->term_id);
$sl_hotel_hot_deal = get_field('sl_hotel_hot_deal', 'option');
$term = get_queried_object();

?>
<h1 class="hidden"><?php echo get_the_archive_title(); ?></h1>
<div class="archive_hotel-wrapper section-padding">
    <div class="archive_hotel_search">
        <div class="container archive_hotel_search-inner">
            <?php echo do_shortcode( '[hotel_archive_search_form]'); ?>
        </div>
    </div>
    <div id="primary">
        <div class="container">
            <div class="vie-row flex flex-x">
                <div class="vie-col-9">
                    <div class="vie-row flex flex-x">
                        <div class="vie-col-3">
                            <div class="archive_hotel-filter">
                                <div class="hotel_filter-wrapper">
                                    <div class="hotel_filter-inner">
                                        <?php echo do_shortcode( '[fe_widget]' ); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="vie-col-9">
                            <div class="archive_hotel-sorting">
                                <lable class="archive_hotel-sort-text">Sắp xếp: </lable>
                                <?php dynamic_sidebar( 'hotel-sort-widget' );?>
                            </div>
                            <?php //hotel_deal_hot(); ?>

                            <?php hotel_content_loop(); ?>
                                    
                            <?php \HD\Helper::paginateLinks(); ?>

                        </div>
                    </div>
                </div> 
                <div class="vie-col-3">
                    <?php tour_content_sidebar(); ?>
                </div>
            </div> 
        </div>
    </div>
    
    <?php echo do_shortcode('[add_banner_taxonomy_hotel]'); ?>
    <?php echo do_shortcode('[add_route_taxonomy_hotel]'); ?>
</div>
<?php

/**
 * HOOK: hd_archive_after_action
 */
do_action( 'hd_archive_after_action' );

// footer
get_footer( 'archive' );