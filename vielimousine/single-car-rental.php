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
$banner = get_field('banner_single_rental','option');
$link_banner = get_field('link_banner_single_rental','option');
// Main
$title_single_rental = get_field('title_single_rental','option');
$form_single_rental = get_field('form_single_rental','option');
$car_rental_information = get_field('car_rental_information');
// Why choose
$title_choose = get_field('title_whychoose','option');
$lists_choose = get_field('lists_whychoose','option');
// Policy
$title_policy = get_field('title_policy_rental','option');
$content_policy = get_field('content_policy_rental','option');
?>
<section class="section section-page section-single singular">
    <div class="banner-single">
        <?php if($link_banner){
            echo '<a href="'. $link_banner .'" class="block">';
            } else {
                echo '<div class="relative">';
            } ?>
            <?php if($banner){
                echo '<img src="'. $banner .'">';
            }
            if($link_banner){
                echo '</a>';
            } else {
                echo '</div>';
        } ?>
    </div>
    <div class="container">
        <div class="content-car-rental section-padding flex flex-x">
            <div class="cell cell-form">
                <?php if($title_single_rental){
                    echo '<p class="heading-title">'. $title_single_rental .'</p>';
                }
                if($form_single_rental){
                    echo do_shortcode('[contact-form-7 id="' . esc_attr($form_single_rental) . '"]');
                } ?>
            </div>
            <div class="cell cell-inf">
                <p class="heading-title">CHI TIẾT XE</p>
                <div class="content">
                    <h2 class="title-rental"><?php echo get_the_title(); ?></h2>
                    <?php 
                        if($car_rental_information['car_rental_short_description']){
                            echo '<div class="car-utilities">';
                            echo '<p class="title">Tiện ích:</p>';
                            echo '<div class="desc">'. $car_rental_information['car_rental_short_description'] .'</div>';
                            echo '</div>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
		if($lists_choose){
			echo '<div class="rental-why-choose section-route-experience section-padding">';
			echo '<div class="container">';
			if($title_choose){
				echo '<div class="heading-group text-center">';
				echo '<h2 class="heading-title">'. $title_choose .'</h2>';
				echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
				echo '</div>';
			}
			echo '<ul class="wrapper">';
			foreach($lists_choose as $val){
				echo '<li class="item flex">';
				echo '<div class="cover">';
				echo '<span class="scale res ar-4-3">';
				echo '<img src="'. $val['img'] .'">';
				echo '</span>';
				echo '</div>';
				echo '<div class="content text-center flex align-center">';
				echo '<h3 class="title">'. $val['title'] .'</h3>';
				echo '<div class="desc">'. $val['content'] .'</div>';
				echo '</div>';
				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
			echo '</div>';
		}
    ?>

    <section class="section rental-policy section-padding">
        <div class="container">
            <?php 
                if($title_policy){
                    echo '<div class="heading-group text-center">';
                    echo '<h2 class="heading-title">'. $title_policy .'</h2>';
                    echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
                    echo '</div>';
                }
                echo '<div class="content-toggle-wrapper relative">';
				echo '<div class="content-rental collapsed">';
				echo $content_policy;
				echo '</div>';
				echo '<div class="mask"></div>';
				echo '<button class="toggle-button ml-auto mr-auto"><i class="fa-regular fa-chevrons-down"></i><span>Xem thêm</span></button>';
				echo '</div>';
            ?>
        </div>
    </section>
</section>
<?php

/**
 * HOOK: hd_single_after_action
 */
do_action( 'hd_single_after_action' );

// footer
get_footer( 'single' );
