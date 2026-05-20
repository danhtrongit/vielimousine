<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_booking' ) {
	return;
}
$title = $args['title'] ?? '';
$code = $args['script_form'] ?? ''; ?>
<section class="section home-booking route-booking section-padding relative" id="dat-ve">
    <div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        }
        if($code){
            echo '<div class="code">'. $code .'</div>';
        } ?>
    </div>
</section>