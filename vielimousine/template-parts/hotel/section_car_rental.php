<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_car_rental' ) {
	return;
}
$title = $args['heading_title'] ?? '';
$desc = $args['desc_section'] ?? '';
$lists_car = $args['lists_thue_xe'] ?? '';  ?>
<section class="section home-car_rental section-padding">
    <div class="container">
        <div class="group-title text-center">
            <?php if($title){
                echo '<h2 class="heading-title">'. $title .'</h2>';
            }
            if($desc){
                echo '<div class="desc">'. $desc .'</div>';
            } ?>
        </div>
        <?php if($lists_car) {
            foreach($lists_car as $val){
                $group = $val['group_title']; ?>
                <div class="wrapper flex flex-x">
                    <div class="cell cell-content flex justify-center flex-column">
                        <div class="group">
                            <h3 class="heading-title"><?php echo $group['title']; ?></h3>
                            <?php if($group['desc']) { echo '<div class="desc">'. $group['desc'] .'</div>'; } ?>
                        </div>
                        <?php if($val['content']) { echo '<div class="content">'. $val['content'] .'</div>'; } ?>
                        <a href="<?php echo $val['btn_link']; ?>" class="btn-main"><?php echo $val['btn_name']; ?></a>
                    </div>
                    <div class="cell cell-img">
                        <img src="<?php echo $val['img']; ?>" alt="img">
                    </div>
                </div>
            <?php }
        } ?>
    </div>
</section>