<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_promotion' ) {
	return;
}
$title = $args['title'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? '';
$note = $args['note'] ?? '';
$img = $args['img'] ?? '';  ?>
<section class="home-promotion relative">
    <div class="container">
        <div class="wrapper flex">
            <div class="cell cell-content flex justify-center">
                <h2 class="heading-title"><?php echo $title; ?></h2>
                <a href="<?php echo $btn_link; ?>" class="btn-main"><?php echo $btn_name; ?></a>
                <?php if($note) { echo '<div class="note">'. $note .'</div>'; } ?>
            </div>
            <div class="cell cell-img">
                <img src="<?php echo $img; ?>" alt="img">
            </div>
        </div>
    </div>
</section>