<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_bao_chi' ) {
	return;
}
$title = $args['title'] ?? '';
$lists = $args['lists'] ?? ''; ?>
<section class="home-bao-chi section-padding">
    <div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        } ?>
        <?php if($lists){
            echo '<div class="wrapper">';
            echo '<div class="swiper-container">';
            $_data = [
                'autoview'   => true,
                'loop' => true,
                // 'navigation' => true,
                'pagination' => true,
                'autoplay' => true,
            ];
            try {
                $swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
            } catch ( \JsonException $e ) {}

            if ( $swiper_data ) : ?>
            <div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
                <div class="swiper-wrapper">
                    <?php foreach($lists as $val){
                        echo '<div class="swiper-slide">';
                        echo '<div class="item">';
                        echo '<a href="'. $val['link'] .'" class="block" target="_blank" rel="nofollow">';
                        echo '<img src="'. $val['logo'] .'" alt="Báo chí">';
                        echo '</a>';
                        echo '</div>';
                        echo '</div>';
                    } ?>
                </div>
            </div>
            <?php endif;
            echo '</div>';
            echo '</div>';
        } ?>
    </div>
</section>