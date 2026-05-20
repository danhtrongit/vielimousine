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
$banner_tax_hotel = get_field('banner_tax_hotel', 'category_' . $object->term_id);
$title_tax_hotel = get_field('title_tax_hotel', 'category_' . $object->term_id);
$sl_hotel_hot_deal = get_field('sl_hotel_hot_deal', 'option');

?>
<h1 class="hidden"><?php echo get_the_archive_title(); ?></h1>
<div class="archive_hotel-wrapper section-padding">
    <?php if($banner_tax_hotel){
        echo '<div class="archive_banner">';
        echo '<div class="banner-img relative">';
        echo '<img src="'. $banner_tax_hotel .'" alt="img">';
        echo '<div class="container">';
        if($title_tax_hotel){
            echo '<p class="title-banner">'. $title_tax_hotel .'</p>';
        } else {
            echo '<p class="title-banner">'. get_the_archive_title() .'</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    } ?>

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

                            <?php //hotel_content_loop(); ?>

                            <ul class="list-hotel flex flex-x">
                                <?php
                                $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
                                $posts_per_page = 12;
                                $current_location = get_queried_object();
                                $all_hotel_ids = get_posts(array(
                                    'post_type'      => 'hotel',
                                    'posts_per_page' => -1,
                                    'fields'         => 'ids',
                                    'tax_query'      => array(
                                        array(
                                            'taxonomy' => 'hotel-location',
                                            'field'    => 'term_id',
                                            'terms'    => $current_location->term_id,
                                        ),
                                    ),
                                ));
                                if (!empty($all_hotel_ids)) {
                                    // usort($all_hotel_ids, function($a, $b) {
                                    //     $terms_a = get_the_terms($a, 'hotel-rank');
                                    //     $terms_b = get_the_terms($b, 'hotel-rank');
                                    //     $name_a = ($terms_a && !is_wp_error($terms_a)) ? $terms_a[0]->name : '';
                                    //     $name_b = ($terms_b && !is_wp_error($terms_b)) ? $terms_b[0]->name : '';
                                    //     return strnatcmp($name_b, $name_a); // Sắp xếp tự nhiên giảm dần
                                    // });
                                    usort($all_hotel_ids, function($a, $b) {
                                    $terms_a = get_the_terms($a, 'hotel-rank');
                                    $terms_b = get_the_terms($b, 'hotel-rank');
                                    $name_a = ($terms_a && !is_wp_error($terms_a)) ? $terms_a[0]->name : '';
                                    $name_b = ($terms_b && !is_wp_error($terms_b)) ? $terms_b[0]->name : '';
                                    $rank_diff = strnatcmp($name_b, $name_a);
                                    if ($rank_diff === 0) {
                                        $price_a = (float) get_post_meta($a, 'hotel_price_discount', true);
                                        $price_b = (float) get_post_meta($b, 'hotel_price_discount', true);
                                        if ($price_a == $price_b) {
                                            return 0;
                                        }
                                        return ($price_b < $price_a) ? -1 : 1;
                                    }
                                    return $rank_diff;
                                });
                                    $offset = ($paged - 1) * $posts_per_page;
                                    $paged_ids = array_slice($all_hotel_ids, $offset, $posts_per_page);
                                    $args = array(
                                        'post_type'      => 'hotel',
                                        'post__in'       => $paged_ids,
                                        'orderby'        => 'post__in', // Giữ đúng thứ tự đã sắp xếp ở bước 3
                                        'posts_per_page' => $posts_per_page,
                                    );
                                    $hotel_query = new WP_Query($args);
                                    if ($hotel_query->have_posts()) :
                                        while ($hotel_query->have_posts()) : $hotel_query->the_post();
                                            hotel_item_template(); 
                                        endwhile;
                                        echo '</ul>';
                                        wp_reset_postdata();
                                    endif;
                                } else {
                                    echo '<p>Chưa có khách sạn nào tại khu vực này.</p></ul>';
                                }
                                ?>
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