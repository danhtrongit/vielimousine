<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_shortcode' ) {
	return;
}
$shortcode  = $args['shortcode'] ?? '';
$css_class  = $args['css_class'] ?? '';
?>
<section class="section tour-shortcode section-padding relative <?php if($css_class){ echo $css_class; } ?>">
    <div class="container">
        <?php if($shortcode){ ?>
            <div class="shortcode"><?php echo do_shortcode($shortcode); ?></div>
        <?php } ?>
    </div>
</section>