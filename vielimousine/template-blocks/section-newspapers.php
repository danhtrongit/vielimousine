<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$title_newspapers = Helper::getField( 'title_newspapers', 'option' );
$lists_newspapers = Helper::getField( 'lists_newspapers', 'option' );
?>
<section class="section home-bao-chi">
    <div class="container">
        <?php if($title_newspapers){
            echo '<h2 class="heading-title text-center">'. $title_newspapers .'</h2>';
        } ?>
        <?php if($lists_newspapers){
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
                    <?php foreach($lists_newspapers as $val){
                        echo '<div class="swiper-slide">';
                        echo '<div class="item">';
                        echo '<a href="'. $val['link'] .'" class="block" target="_blank">';
                        echo '<img src="'. $val['logo'] .'">';
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