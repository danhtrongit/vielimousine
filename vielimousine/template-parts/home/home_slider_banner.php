<?php

use HD\Helper;

\defined( 'ABSPATH' ) || die;

$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_slider_banner' ) {
	return;
}
$slider_banner = $args['slider_banner'] ?? ''; 
$autoplay = $args['autoplay'] ?? false;
$navigation = $args['navigation'] ?? false;
$pagination = $args['pagination'] ?? false;
?>
<section class="home-slider">
    <?php if ( $slider_banner ) : ?>
    <div class="home-slider__wrap">
        <div class="swiper-container">
        <?php
        $_data = [
            // 'autoview'   => true,
            'loop' => true,
            'navigation' => true,
            'mobile' => [
                'slidesPerView'  => 1,
                'slidesPerGroup' => 1,
            ],
            'tablet' => [
                'slidesPerView'  => 1,
                'slidesPerGroup' => 1,
            ],
            'desktop' => [
                'slidesPerView'  => 1,
                'slidesPerGroup' => 1,
            ]
        ];
        if ( $autoplay ) {
            $_data['autoplay'] = Helper::toBool( $autoplay );
        }
        if ( $navigation ) {
            $_data['navigation'] = Helper::toBool( $navigation );
        }
        if ( $pagination ) {
            $_data['pagination'] = 'bullets';
        }
        try {
            $swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
        } catch ( \JsonException $e ) {}

        if ( $swiper_data ) : ?>
            <div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
                <div class="swiper-wrapper">
                    <?php foreach($slider_banner as $val){
                    $image_id = $val['img'];
                    $image_src_full = wp_get_attachment_image_src($image_id, 'full')[0];
                    $image_src_large = wp_get_attachment_image_src($image_id, 'large')[0];
                    $image_src_medium = wp_get_attachment_image_src($image_id, 'medium')[0]; ?>
                    <div class="swiper-slide">
                        <div class="item-wrap">
                            <picture>
                                <source srcset="<?php echo esc_url($image_src_medium); ?>" media="(max-width: 576px)">
                                <source srcset="<?php echo esc_url($image_src_large); ?>" media="(max-width: 992px)">
                                <img src="<?php echo esc_url($image_src_full); ?>" alt="image">
                            </picture>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</section>