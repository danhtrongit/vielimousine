<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_search' ) {
	return;
}
$shortcode = $args['shortcode'] ?? ''; ?>
<section class="section hotel-search section-padding relative">
    <div class="container">
        <?php if($shortcode){
            echo do_shortcode($shortcode);
        } ?>
    </div>
</section>