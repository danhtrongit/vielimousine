<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_booking' ) {
	return;
}
$title = $args['title'] ?? '';
$code = $args['code'] ?? '';  ?>
<section class="home-booking section-padding relative">
    <div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        }
        if($code){
            echo '<div class="code">'. $code .'</div>';
        } ?>
    </div>
</section>