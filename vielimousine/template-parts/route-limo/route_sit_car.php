<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_sit_car' ) {
	return;
}
$title = $args['title'] ?? '';
$table_content = $args['table_content'] ?? '';

?>
<section class="section route-sit-car content-has-table section-padding" id="tien-ich-xe-vie">
    <?php if($title){
        echo '<h2 class="heading-title text-center">'. $title .'</h2>';
    }
    echo '<div class="content">';
    echo $table_content;
    echo '</div>';
    ?>
</section>