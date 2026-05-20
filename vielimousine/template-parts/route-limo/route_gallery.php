<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_gallery' ) {
	return;
}
$title_gallery = $args['title_gallery'] ?? '';
$lists_gallery = $args['lists_gallery'] ?? '';

?>
<section class="section route-gallery section-padding relative">
    <?php if($title_gallery){
        echo '<h2 class="heading-title text-center">'. $title_gallery .'</h2>';
    }
    if($lists_gallery) {
        echo '<div class="route-gallery__wrapper">';
        foreach ($lists_gallery as $val){ ?>
            <div class="item relative">
                <div class="col-thumb relative">
                    <img src="<?php echo $val['img']; ?>" class="absolute" alt="image">
                </div>
                <div class="content"><?php echo $val['content'] ?></div>
            </div>
        <?php }
        echo '</div>';
        echo '<div class="route-gallery__dot relative">';
        foreach ($lists_gallery as $gal){ ?>
            <div class="item">
                <div class="col-thumb relative">
                    <img src="<?php echo $gal['img']; ?>" class="absolute" alt="image">
                </div>
            </div>
        <?php }
        echo '</div>';
    } ?>
</section>