<?php
/**
 * The Template for displaying all single posts.
 * http://codex.wordpress.org/Template_Hierarchy
 *
 * @author Vie
 */

\defined( 'ABSPATH' ) || die;

// header
get_header( 'single' );

if ( have_posts() ) {
	the_post();
}

if ( post_password_required() ) {
	echo get_the_password_form();
	get_footer( 'single' );

	return;
}

/**
 * HOOK: hd_single_before_action
 */
do_action( 'hd_single_before_action' );
$alternative_title = \HD\Helper::getField( 'alternative_title', $post->ID );
$sl_form_single_hotel = get_field('sl_form_single_hotel','option');
?>
<section class="section section-page section-single singular">
    <div class="container">
        <div class="vie-row flex flex-x">
            <div class="cell vie-col-9">
                <main id="main" class="site-main">
                    <article <?php post_class('article'); ?>>
                        <div class="entry-head-content">
                            <div class="vie-row flex flex-x">
                                <div class="entry-head-info">
                                    <p class="heading-title hotel_single-title"><?php the_title(); ?></p>
                                    <?php 
                                        $hotel_address = get_field('hotel_address', get_the_ID());
                                        if($hotel_address){
                                            ?>
                                                <div class="hotel_single-address">
                                                    <span class="address"><?php echo $hotel_address; ?></span>
                                                </div>
                                            <?php
                                        }
                                    ?>
                                    <div class="single_hotel-meta flex flex-x">
                                        <div class="hotel_item-type flex flex-x">
                                            <?php
                                                $taxonomy_name = 'hotel-convenient';
                                                $hotel_links = get_hotel_type_links($taxonomy_name);
                                                if (!empty($hotel_links)) {
                                                    echo $hotel_links;
                                                } else {
                                                    echo '';
                                                }
                                            ?>
                                        </div>
                                        <div class="rating">
                                            <div class="post_item-rating flex">
                                                <?php 
                                                    $postID = get_the_ID();
                                                    $ratingInfo = getPostRatingInfo($postID);
                                                    echo displayRatingStars($ratingInfo['average_rating']);
                                                    echo "Đánh giá: " . $ratingInfo["average_rating"] ." ". "(" . $ratingInfo["rating_count"] . ")";
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="hotel_item-foot">
                                    <div class="hotel_item-foot-left">
                                        <span class="hotel_note"><?php echo __('Thanh toán nhận ưu đãi', 'vie') ?></span>
                                        <?php 
                                            $hotel_price = get_field('hotel_price');
                                            $hotel_price_discount = get_field('hotel_price_discount');
                                            echo display_price( $hotel_price , $hotel_price_discount );
                                        ?>
                                    </div>
                                    <div class="hotel_item-foot-right">
                                        <a href="#form_booking" class="hotel-booknow-btn"><?php echo __('ĐẶT NGAY',TEXT_DOMAIN); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                            if ( get_field('hotel_gallery')){
                                $hotel_gallery =  get_field('hotel_gallery');
                                $count = 0;
                                if ( count($hotel_gallery) > 0 ){ ?>
                                        <div class="hotel_gallery">
                                            <div class="hotel_gallery-wrapper">
                                                <div id="hotel_gallery-list">
                                                    <?php
                                                        foreach ( $hotel_gallery as $tour_image ){ ?>
                                                        <div class="img">
                                                            <a href="<?php echo $tour_image['url']; ?>" class="block relative" data-fancybox="gallery-<?php echo get_the_ID(); ?>">
                                                                <?php echo wp_get_attachment_image( $tour_image['id'], 'large' ); ?>
                                                            </a>
                                                        </div>
                                                        <?php  
                                                    } ?>
                                                </div>
                                                <div class="hotel_gallery-nav">
                                                    <?php 
                                                        foreach (  $hotel_gallery as $tour_image ){ ?>
                                                        <div class="img-dots">
                                                            <div class="img block relative">
                                                                <?php echo wp_get_attachment_image( $tour_image['id'], 'large' ); ?>
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                }
                            }
                        ?>
                        <?php 
                            if ( get_field('hotel_combo') ){
                                if ( !empty(get_field('hotel_combo'))) ?>
                                    <div class="hotel_combo">
                                        <div class="hotel_combo-inner">
                                            <?php 
                                                if( get_field('hotel_combo_title') && !empty( get_field('hotel_combo_title') )){
                                                    ?>
                                                        <h2 class="single-tour-title-content"><?php echo get_field('hotel_combo_title'); ?></h2>
                                                    <?php
                                                }
                                            ?>
                                            <div class="hotel_combo-content">
                                                <?php echo get_field('hotel_combo'); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>

                        <?php 
                            if ( have_rows('hotel_utilities') ){
                                ?>
                                    <div class="hotel_utilities">
                                        <div class="hotel_utilities-inner">
                                            <?php 
                                                if( get_field('hotel_utilities_title') && !empty( get_field('hotel_utilities_title') )){
                                                    ?>
                                                        <h2 class="single-tour-title-content"><?php echo get_field('hotel_utilities_title'); ?></h2>
                                                    <?php
                                                }
                                            ?>
                                            <ul class="hotel_utilities-list">
                                                <?php 
                                                    while( have_rows('hotel_utilities') ) {
                                                        the_row();
                                                        $hotel_utility_item_text = get_sub_field('hotel_utility_item_text');
                                                        $hotel_utility_item_link = get_sub_field('hotel_utility_item_link');

                                                        ?>
                                                            <li class="hotel_utilities-item"><a href="<?php echo $hotel_utility_item_link; ?>" class="hotel_utilities-item-link"><?php echo $hotel_utility_item_text; ?></a></li>
                                                            <?php
                                                    }
                                                ?>
                                                
                                            </ul>
                                        </div>
                                    </div>
                                <?php
                            }
                        ?>

                        <div class="entry-content">
                            <?php
                                if (empty(get_the_content())) {	
                                    echo '';
                                } else {
                                    ?>
                                        <h2 class="single-tour-title-content">Thông tin <?php the_title(); ?></h2>
                                    <?php
                                    echo do_shortcode('[ez-toc]');
                                    the_content();
                                }
                            ?>
                        </div>

                        <!-- <div class="entry-comments">
                            <?php
                                // if ( comments_open() || get_comments_number() ) {
                                //     comments_template();
                                // }
                            ?>
                        </div> -->
                    </article>
                </main>
            </div>

            <aside class="cell vie-col-3">
                <div class="single_tour_sidebar-wrapper">
                    <div class="single-tour-sidebar" id="form_booking">
                        <div class="single_tour_sidebar-price">
                            <div class="single_tour_sidebar-price-inner">
                                <h4 class="singl_hotel-form-title">ĐẶT KHÁCH SẠN</h4>
                            </div>
                        </div>
                        <div class="single_tour_sidebar-form">
                            <div class="single_tour_sidebar-form-inner">
                                <?php if($sl_form_single_hotel){
                                    echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_single_hotel) . '"]');
                                } ?>
                            </div>
                        </div>
                    </div>	
                    <?php tour_content_sidebar(); ?>   
                </div>
            </aside>
        </div>
    </div>
    
</section>
<?php

/**
 * HOOK: hd_single_after_action
 */
do_action( 'hd_single_after_action' );

// footer
get_footer( 'single' );
