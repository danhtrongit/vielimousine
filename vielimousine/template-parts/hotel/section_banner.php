<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_banner' ) {
	return;
}
$banner = $args['banner_image'] ?? '';
$link = $args['link'] ?? '';
$full_screen  = $args['full_screen'] ?? '';
$blank  = $args['target_blank'] ?? ''; ?>
<section class="section hotel-banner relative <?php if($full_screen != 1){ echo 'section-padding'; } ?>">
    <?php 
    if($full_screen != 1){ echo '<div class="container">'; }
    if($link){
        echo '<a href="'. $link .'" class="block" target="_blank">';
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
    }
    if($full_screen == 'false'){ echo '</div>'; } ?>
</section>