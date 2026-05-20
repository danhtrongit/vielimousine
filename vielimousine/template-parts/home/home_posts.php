<?php
use HD\Helper;
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_posts' ) {
	return;
}
$heading_title = $args['heading_title'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? 'Xem tất cả';
$select_categories = $args['select_categories'] ?? false;
$number = $args['number'] ?? false;
$post_query = Helper::queryByTerms( $select_categories, 'post', 'category', false, $number );
$autoplay = $args['autoplay'] ?? false;
?>
<section class="home-posts section-padding">
    <div class="container">
        <?php if($heading_title){
            echo '<h2 class="heading-title text-center">'. $heading_title .'</h2>';
        } ?>
        <div class="home-posts__content">
            <?php if ( $select_categories ) : ?>
                <div class="swiper-container">
                    <?php
                    $_data = [
                        'autoview'   => true,
                        'loop' => true,
                        'navigation' => true,
                    ];
                    if ( $autoplay ) {
                        $_data['autoplay'] = Helper::toBool( $autoplay );
                    }
                    try {
                        $swiper_data = json_encode( $_data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE );
                    } catch ( \JsonException $e ) {}

                    if ( $swiper_data ) : ?>
                        <div class="w-swiper swiper" data-options='<?= $swiper_data ?>'>
                            <div class="swiper-wrapper">
                                <?php $i = 0;
                                while ( $post_query?->have_posts() && $i < $number ) : $post_query->the_post();
                                $i++;
                                $post = get_post();
                                $post_title     = get_the_title( $post->ID );
                                $post_title     = ( ! empty( $post_title ) ) ? $post_title : __( '(no title)', TEXT_DOMAIN );
                                // $ratio_class    = Helper::aspectRatioClass( 'post' );
                                $post_thumbnail = get_the_post_thumbnail( $post, 'large', [ 'alt' => Helper::escAttr( $post_title ) ] );?>
                                    <div class="swiper-slide">
                                        <div class="item">
                                            <div class="cover">
                                                <span class="scale res ar-4-3">
                                                    <?= $post_thumbnail ?>
                                                    <a class="link-cover" href="<?= get_permalink( $post->ID ) ?>" aria-label="<?= Helper::escAttr( $post_title ) ?>"></a>
                                                </span>
                                            </div>
                                            <div class="content">
                                                <h3 class="title">
                                                    <a href="<?= get_permalink( $post->ID ) ?>" aria-label="<?= Helper::escAttr( $post_title ) ?>"><?= $post_title ?></a>
                                                </h3>
                                                <?= Helper::loopExcerpt( $post ) ?>
                                                <a href="<?= get_permalink( $post->ID ) ?>" class="btn-see-more">Đọc tiếp <i class="fa-regular fa-arrow-right-long"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; 
                wp_reset_postdata(); ?>
            <?php echo '</div>';
        endif;
        if($btn_link) {
            echo '<a href="'. $btn_link .'" class="btn-main ml-auto mr-auto">'. $btn_name .' <i class="fa-regular fa-arrow-right-long"></i></a>';
        } ?>
    </div>    
</section>