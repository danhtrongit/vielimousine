<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_banner' ) {
	return;
}
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? '';
$bg = $args['bg_img'] ?? '';
$bg_mobile = $args['bg_img_mb'] ?? '';
$css_class = $args['css_class'] ?? '';  ?>
<section class="section route-banner relative <?php if($css_class){ echo $css_class; } ?>">
    <?php if ($bg) : ?>
    <div class="route-banner-wrapper">
        <picture>
            <?php if ($bg_mobile) : ?>
            <source media="(max-width: 640px)" srcset="<?= esc_url($bg_mobile) ?>">
            <?php endif; ?>
            <img src="<?= esc_url($bg) ?>" alt="Banner" loading="lazy" width="1920" height="600">
        </picture>
        <div class="container route-banner-cta">
            <a href="#popup-dang-ky-nhanh" class="fcy-popup btn-main">
                <?php echo $btn_name; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</section>