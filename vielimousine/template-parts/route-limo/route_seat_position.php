<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_seat_position' ) {
	return;
}
$title = $args['title'] ?? '';
$img_diagram = $args['img_diagram'] ?? '';
$table_position = $args['table_position'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$zalo = Helper::getField('zalo','option');
$m_hotline = Helper::getField('m_hotline','option');
$first_phone = $m_hotline[0]['phone'] ?? '';
$hotline_other = Helper::getField('hotline_other','option');
?>
<section class="section route-seat-position content-has-table section-padding relative" id="so-do-cho-ngoi">
    <?php if($title){
        echo '<h2 class="heading-title text-center">'. $title .'</h2>';
    }
    if($img_diagram || $table_position) {
        echo '<div class="wrapper flex flex-x">';
        if($img_diagram) {
        echo '<div class="col-diagram">';
        echo '<img src="'. $img_diagram .'" class="relative" alt="image">';
        echo '</div>';
        }
        if($table_position) {
            echo '<div class="col-table">';
            echo '<div class="content">';
            echo $table_position;
            echo '</div>';
            // if($btn_link) {
            //     echo '<a href="'. $btn_link .'" class="btn-main btn-pc block">'. __('ĐẶT VÉ XE NGAY', TEXT_DOMAIN) .'</a>';
            // }
            // echo '<a href="#popup-dang-ky-nhanh" class="fcy-popup btn-main btn-mobile">'. __('ĐẶT VÉ NHANH', TEXT_DOMAIN) .'</a>';
            echo '<div class="group-btn flex align-center">';
            if($zalo) {
                echo '<a href="'. $zalo .'" target="_blank" class="btn-custom flex align-center relative">
                <img class="relative" src="'. get_template_directory_uri() .'/resources/img/ic-zalo.png" alt="Zalo OA">
                <span class="text">Zalo OA</span>
                </a>';
            }
            if($hotline_other) {
                echo '<a href="tel:'. $hotline_other .'" class="btn-custom btn-phone flex align-center relative">
                <span class="icon flex align-center justify-center relative">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 32 32"><path d="M24 13h-2a3.003 3.003 0 0 0-3-3V8a5.006 5.006 0 0 1 5 5z" fill="currentColor"></path><path d="M28 13h-2a7.008 7.008 0 0 0-7-7V4a9.01 9.01 0 0 1 9 9z" fill="currentColor"></path><path d="M20.333 21.482l2.24-2.24a2.167 2.167 0 0 1 2.337-.48l2.728 1.092A2.167 2.167 0 0 1 29 21.866v4.961a2.167 2.167 0 0 1-2.284 2.169C7.594 27.806 3.732 11.61 3.015 5.408A2.162 2.162 0 0 1 5.169 3h4.873a2.167 2.167 0 0 1 2.012 1.362l1.091 2.728a2.167 2.167 0 0 1-.48 2.337l-2.24 2.24s1.242 8.732 9.908 9.815z" fill="currentColor"></path></svg>
                </span>
                <span class="text">'. $hotline_other .'</span>
                </a>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } ?>
</section>