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
$sl_form_single_tour = get_field('sl_form_single_tour','option');
?>
<section class="section section-page section-single singular">
    <div class="container">
        <div class="vie-row flex flex-x">
            <div class="cell vie-col-9">
                <main id="main" class="site-main">
                    <article <?php post_class('article'); ?>>
                        <p class="single-tour-title heading-title"><?php the_title(); ?></p>
                        <div class="entry-content">
                            <?php 
                                if ( get_field('tour_hightlights') && !empty(get_field('tour_hightlights')) ){
                                    ?>
                                        <div class="tour_hightlight">
                                            <div class="tour_hightlight-inner">
                                                <h2 class="single-tour-title-content">Hoạt động nổi bật</h2>
                                                <?php 
                                                    echo get_field('tour_hightlights');
                                                ?>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>

                            <?php 
                                if ( get_field('tour_gellary')){
                                    $tour_gellaries =  get_field('tour_gellary');
                                    $count = 0;
                                    if ( count($tour_gellaries) > 0 ){
                                        ?>
                                            <div class="tour_gallery">
                                                <div class="tour_gallery-inner">
                                                    <h2 class="single-tour-title-content">Hình Ảnh Tour</h2>
                                                    <div class="tour_gallery-wrapper">
                                                        <div id="tour_gallery-list">
                                                            <?php
                                                                foreach ( $tour_gellaries as $tour_image ){ ?>
                                                                    <div class="img">
                                                                        <a href="<?php echo $tour_image['url']; ?>" class="block relative" data-fancybox="gallery-<?php echo get_the_ID(); ?>">
                                                                            <?php echo wp_get_attachment_image( $tour_image['id'], 'large' ); ?>
                                                                        </a>
                                                                    </div>
                                                                    <?php  
                                                                    $count++;   
                                                                }
                                                            ?>
                                                        </div>
                                                        <div class="tour_gallery-nav">
                                                            <?php 
                                                                $count2 = 0;
                                                                foreach (  $tour_gellaries as $tour_image ){ ?>
                                                                    <div class="img-dots">
                                                                        <div class="img block relative">
                                                                            <?php echo wp_get_attachment_image( $tour_image['id'], 'large' ); ?>
                                                                        </div>
                                                                    </div>
                                                                    <?php    
                                                                    $count2++;    
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                    }
                                    
                                }
                            ?>

                            <?php 
                                if ( get_field('tour_experience') && !empty(get_field('tour_experience')) ){
                                    ?>
                                        <div class="tour_experience">
                                            <div class="tour_experience-inner">
                                                <h2 class="single-tour-title-content"></h2>
                                                <?php 
                                                    echo get_field('tour_experience');
                                                ?>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>

                            <div class="tour_process">
                                <?php 
                                    if ( get_field('tour_process_short_content') && !empty(get_field('tour_process_short_content')) ){
                                        ?>
                                    <h2 class="single-tour-title-content">Lịch trình chi tiết</h2>

                                            <div class="tour_process_short_content">
                                                <div class="tour_process_short_content-inner">
                                                    <?php 
                                                        echo get_field('tour_process_short_content');
                                                    ?>
                                                </div>
                                            </div>
                                        <?php
                                    }
                                ?>
                                <?php 
                                    if ( get_field('tour_process_repeat') ){
                                        while( have_rows('tour_process_repeat') ) : the_row();
                                            $tour_process_title = get_sub_field('tour_process_title');
                                            $tour_process__repeat =  get_sub_field('tour_process__repeat');
                                            ?>
                                                <div class="timeline__item_item">
                                                    <div class="timeline__item__content">
                                                        <div class="timeline__item__head">
                                                            <div class="timeline__item__icon-wrap">
                                                                <span class="timeline__item__icon"><i class="fa-solid fa-clock"></i></span>
                                                            </div>
                                                            <h4 class="timeline__item__head-title"><?php echo $tour_process_title; ?></h4>
                                                        </div>
                                                        <?php 
                                                            if (  $tour_process__repeat  ) {
                                                                foreach( $tour_process__repeat as $tourDetail ) {
                                                                    ?>
                                                                        <div class="timeline__item__content-group">
                                                                            <div class="timeline__item__icon-wrap">
                                                                                <span class="timeline__item__icon">o</span>
                                                                            </div>
                                                                            <div class="timeline__item__content-detaill">
                                                                                <?php echo $tourDetail['tour_process__detail']; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                }
                                                                ?>
                                                                
                                                                <?php
                                                            }
                                                        ?>
                                                    </div>
                                                
                                                </div>
                                            <?php
                                        endwhile;
                                    }
                                ?>
                            </div>

                            <?php 
                                if ( have_rows('tour_included_repeat') ){
                                    ?>
                                        <div class="tour_included">
                                            <div class="tour_included-inner">
                                                <h2 class="single-tour-title-content">Chương trình đã bao gồm</h2>
                                                <ul class="tour_included-list">
                                                    <?php 
                                                        while( have_rows('tour_included_repeat') ) {
                                                            the_row();
                                                            $tour_included_item = get_sub_field('tour_included_item');
                                                            ?>
                                                                    <li class="tour_included-item"><?php echo $tour_included_item; ?></li>
                                                                <?php
                                                        }
                                                    ?>
                                                    
                                                </ul>
                                            </div>
                                        </div>
                                    <?php
                                }
                            ?>

                            <?php the_content(); ?>
                        </div>
                    </article>
                </main>
            </div>
            <div class="cell vie-col-3">
                <?php //single_sidebar_form(); ?>
                <div class="single_tour_sidebar-wrapper">
                    <div class="single-tour-sidebar" id="form_booking">
                        <div class="single_tour_sidebar-price">
                            <div class="single_tour_sidebar-price-inner">
                                <h4 class="singl_hotel-form-title">ĐẶT TOUR</h4>
                            </div>
                        </div>
                        <div class="single_tour_sidebar-form">
                            <div class="single_tour_sidebar-form-inner">
                                <?php if($sl_form_single_tour){
                                    echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form_single_tour) . '"]');
                                } ?>
                            </div>
                        </div>
                    </div>	
                    <?php tour_content_sidebar(); ?>   
                </div>
            </div>
        </div>
        <?php
        $current_tour_id = get_the_ID();
        $terms = get_the_terms($current_tour_id, 'tour-location');

        if ($terms && !is_wp_error($terms)) {
            $term_slugs = wp_list_pluck($terms, 'slug');
            $related_args = array(
                'post_type' => 'tour',
                'post_status' => 'publish',
                'posts_per_page' => 4,
                'post__not_in' => array($current_tour_id),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'tour-location',
                        'field' => 'slug',
                        'terms' => $term_slugs,
                        'operator' => 'IN',
                    ),
                ),
            );
            $related_query = new WP_Query($related_args);
            if ($related_query->have_posts()) {
                ?>
                <!-- <section class="vie_related-post">
                    <div class="vie-row">
                        <div class="vie-col-9">
                            <div class="single_tour__other_post">
                                <h3 class="single_tour__other_post-title">Tour Khác</h3>
                                <div class="vie_related-post-list owl-carousel">
                                <?php
                                    //$temp = 1;
                                    while ($related_query->have_posts()) {
                                        $related_query->the_post();
                                        ?>
                                        <div class="vie_related-post-item <?php //if ($temp%2==0){ echo ' ' . 'item-right';} ?>">
                                            
                                            <div class="vie_related-post-thumb">
                                                <a href="<?php the_permalink(); ?>">
                                                    <?php the_post_thumbnail( 'medium' ); ?>
                                                </a>
                                            </div>
                                            <div class="vie_related-post-body">
                                                <?php 
                                                    $related_terms = get_the_terms(get_the_ID(), 'tour-location');
                                                    if ($related_terms && !is_wp_error($related_terms)) {
                                                        ?> 
                                                            <span class="vie_related-post-categories">
                                                                <?php
                                                                    foreach ($related_terms as $term) {
                                                                        echo '<a href="' . esc_url(get_term_link($term)) . '">' . $term->name . '</a>, ';
                                                                    }
                                                                ?>
                                                            </span>
                                                        <?php
                                                    }
                                                ?>
                                                <h4 class="vie_related-post-item-title"><?php the_title( ); ?></h4>
                                            </div>
                                        </div>

                                        <?php
                                        $temp++;
                                    }
                                    wp_reset_postdata();
                                ?>
                            </div>         
                            </div>
                        </div>
                    </div>  
                </section> -->
                <?php
            }
        }
        ?>
    </div>
</section>


<?php

/**
 * HOOK: hd_single_after_action
 */
do_action( 'hd_single_after_action' );

// footer
get_footer( 'single' );
