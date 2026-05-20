<?php
use HD\Helper;
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_lists_cart' ) {
	return;
}
$title = $args['title'] ?? '';
$sl_taxonomy = $args['sl_taxonomy'] ?? false;
$car_query = Helper::queryByTerms( $sl_taxonomy, 'car-rental', 'car-rental-cat', false, -1  );
  ?>
<section class="section car-rental__lists section-padding">
    <div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        }
        if($sl_taxonomy){
            echo '<div class="wrapper flex flex-x">';
            while ( $car_query?->have_posts() ) : $car_query->the_post();
                $post = get_post();
                $post_title     = get_the_title( $post->ID );
                $post_permalink     = get_the_permalink( $post->ID );
                $post_thumbnail = get_the_post_thumbnail( $post, 'large', [ 'alt' => Helper::escAttr( $post_title ) ] );
                $gallery = get_field('single_car_rental_image_gallery');
                $numb_of_seats = get_field('numb_of_seats');
                $is_vvip = get_field('is_vvip');
                $car_rental_information = get_field('car_rental_information');
                $hotline_thue_xe = get_field('hotline_thue_xe','option');
                $zalo = get_field('zalo','option');
                ?>
                <div class="inner flex">
                    <div class="item flex">
                        <div class="cover">
                            <div class="gallery-wrapper">
                                <div class="gallery slider-for" id="slider-for-<?php echo $post->ID; ?>">
                                    <div class="img">
                                        <a href="<?php echo get_the_post_thumbnail_url(); ?>" class="relative block" data-fancybox="gallery-<?php echo $post->ID; ?>" >
                                            <?= $post_thumbnail ?>
                                        </a>
                                    </div>
                                    <?php if($gallery){
                                        foreach( $gallery as $image ) { ?>
                                        <div class="img">
                                            <a href="<?php echo esc_url($image['url']); ?>" class="relative block" data-fancybox="gallery-<?php echo $post->ID; ?>">
                                                <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                                            </a>
                                        </div>
                                        <?php }
                                    } ?>
                                </div>
                                <?php if($gallery){ ?>
                                <div class="gallery slider-nav" id="slider-nav-<?php echo $post->ID; ?>">
                                    <div class="img-dots">
                                        <div class="relative block img">
                                            <?= $post_thumbnail ?>
                                        </div>
                                    </div>    
                                    <?php foreach( $gallery as $image2 ) { ?>
                                        <div class="img-dots">
                                            <div class="relative block img">
                                                <img src="<?php echo esc_url($image2['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                            
                        </div>
                        <div class="content">
                            <h3 class="title">
                                <a href="<?php echo $post_permalink ?>"><?php echo $post_title; ?></a>
                            </h3>
                            <?php if($numb_of_seats || $is_vvip){
                                echo '<ul class="tag-car">';
                                if($numb_of_seats){
                                    echo '<li class="item">';
                                    echo '<img src="'. get_template_directory_uri() . '/resources/img/ic-seat.png' .'">';
                                    echo '<span>'. $numb_of_seats .'</span>';
                                    echo '</li>';
                                }
                                if($is_vvip == 1){
                                    echo '<li class="item">';
                                    echo '<img src="'. get_template_directory_uri() . '/resources/img/ic-vvip.png' .'">';
                                    echo '<span>VVIP</span>';
                                    echo '</li>';
                                }
                                echo '</ul>';
                            }
                            if($car_rental_information['car_rental_short_description']){
                                echo '<div class="car-utilities">';
                                echo '<p class="title">Tiện ích:</p>';
                                echo '<div class="desc">'. $car_rental_information['car_rental_short_description'] .'</div>';
                                echo '</div>';
                            } ?>
                        </div>
                        <div class="contact flex align-center justify-space-between">
                            <div class="item-cta">
                                <span class="text">Liên hệ chi tiết:</span>
                                <a href="tel:<?php echo $hotline_thue_xe; ?>" class="hotline">
                                    <i class="fa-solid fa-phone-volume"></i> Hotline
                                </a>
                                <a href="<?php echo $zalo; ?>" target="_blank" class="zalo">
                                    <img src="<?php echo get_template_directory_uri() . '/resources/img/ic-zalo.png'; ?>" alt="">
                                </a>
                            </div>
                            <a href="<?php echo $post_permalink ?>" class="btn-main">Đặt xe</a>
                        </div>
                    </div>
                </div>
            <?php endwhile;
            echo '</div>';
        } ?>
    </div>
</section>