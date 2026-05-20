<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_banner' ) {
	return;
}
$banner = $args['banner_image'] ?? '';
$title = $args['title'] ?? '';
$desc  = $args['desc'] ?? '';
$shortcode  = $args['shortcode'] ?? ''; ?>
<section class="section tour-banner relative">
    <?php if($banner){
        echo '<img src="'. $banner .'">';
    } ?>
    <div class="container tour-banner-content">
        <?php if($title || $desc){ ?>
            <div class="group-heading">
                <?php if($title){ echo '<p class="title">'. $title .'</p>'; } ?>
                <?php if($desc){ echo '<div class="desc">'. $desc .'</div>'; } ?>
            </div>
        <?php }
        if($shortcode){
            echo '<div class="section-form">';
            echo do_shortcode($shortcode);
            echo '</div>';
        } ?>

    </div>
</section>