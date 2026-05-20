<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'route_pickup' ) {
	return;
}
$lists_pick_up = $args['lists_pick_up'] ?? '';
$bg = $args['bg'] ?? '';

?>
<section class="section route-pickup section-padding relative" id="diem-don-tra" style="<?php if($bg){ ?> background-image: url('<?php echo $bg; ?>') <?php } ?>">
    <?php if($lists_pick_up){
        foreach($lists_pick_up as $val){
            $main_content = $val['main_content'];
            echo '<div class="route-pickup__group">';
            echo '<h3 class="heading-title">'. $val['title_area'] .'</h3>';
            if($main_content){
                echo '<div class="group-col">';
                foreach($main_content as $content){
                    $pickup_locations = $content['pickup_locations'];
                    echo '<div class="group-item">';
                    echo '<div class="title-location">';
                    echo '<i class="fa-solid fa-location-dot"></i>';
                    echo '<span>'. $content['title_location'] .'</span>';
                    echo '</div>';
                    if($pickup_locations){
                        echo '<ul class="wrapper">';
                        foreach($pickup_locations as $locations){
                            echo '<li class="item">';
                            echo '<div class="line">
                                <div class="line-border"></div>
                            </div>';
                            echo '<p class="address">'. $locations['address'] .'</p>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    } ?>
</section>