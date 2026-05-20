<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_img_banner' ) {
	return;
}
$banner = $args['img'] ?? '';
$link = $args['link'] ?? '';  ?>
<section class="home-img-banner section-padding relative">
    <div class="container">
        <?php if($link){
        echo '<a href="'. $link .'" class="block" target="_blank">';
        } else {
            echo '<div class="relative">';
        } ?>
        <?php if($banner){
            echo '<img src="'. $banner .'" alt="image">';
        }
        if($link){
            echo '</a>';
        } else {
            echo '</div>';
        } ?>
    </div>
</section>