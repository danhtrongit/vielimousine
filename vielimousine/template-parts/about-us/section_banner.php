<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_banner' ) {
	return;
}
$banner = $args['banner'] ?? '';
$link = $args['link'] ?? '';  ?>
<section class="section about-us__banner relative">
    <?php if($link){
        echo '<a href="'. $link .'" class="block">';
    } else {
        echo '<div class="relative">';
    } ?>
    <?php if($banner){
        echo '<img src="'. $banner .'">';
    }
    if($link){
        echo '</a>';
    } else {
        echo '</div>';
    } ?>
</section>