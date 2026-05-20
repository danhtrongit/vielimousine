<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_ticket_booking' ) {
	return;
}
$content = $args['content'] ?? '';

?>
<section class="section route-ticket-booking section-padding" id="huong-dan-dat-ve">
    <?php
    // echo '<h2 class="heading-title text-center">Hướng dẫn cách đặt vé xe tại Vie Limo</h2>';
    if($content){
        echo '<div class="content-toggle-wrapper relative">';
        echo '<div class="content-route collapsed">';
        echo $content;
        echo '</div>';
        echo '<div class="mask"></div>';
        echo '<button class="toggle-button ml-auto mr-auto"><i class="fa-regular fa-chevrons-down"></i><span>Xem thêm</span></button>';
        echo '</div>';
    } ?>
</section>