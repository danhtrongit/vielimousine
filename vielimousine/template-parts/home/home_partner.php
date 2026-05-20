<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_partner' ) {
	return;
}
$heading_title = $args['heading_title'] ?? '';
$gallery = $args['gallery'] ?? ''; ?>
<section class="home-partner section-padding">
    <div class="container">
        <?php if($heading_title){
            echo '<h2 class="heading-title text-center">'. $heading_title .'</h2>';
        } ?>
        <?php if($gallery) {
            if(!wp_is_mobile()){
                echo '<div class="gallery">';
                echo '<div class="row row-1 flex gap-30">';
                $i = 0;
                foreach($gallery as $val){
                    $i++;
                    if ($i == 7) {
                        echo '</div>';
                        echo '<div class="row row-2 flex gap-30">';
                    } ?>
                    <div class="item relative">
                        <img src="<?php echo $val; ?>" class="absolute" alt="đối tác">
                    </div>
                <?php }
                if ($i >= 7) {
                    echo '</div>';
                } else {
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="gallery gallery-mb">';
                    echo '<div class="swiper-container">';
                        $_data = [
                            'autoview'   => true,
                            'loop' => true,
                            'pagination' => true,
                            'autoplay' => true,
                        ];
                        try {
                            $swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
                        } catch ( \JsonException $e ) {}
                        if ( $swiper_data ) : ?>
                        <div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
                            <div class="swiper-wrapper">
                                <?php foreach($gallery as $item){ ?>
                                    <div class="swiper-slide item relative">
                                        <img src="<?php echo $item; ?>" class="absolute" alt="đối tác">
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php }
        } ?>
    </div>
</section>