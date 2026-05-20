<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'home_combo' ) {
	return;
}
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$img = $args['img'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? '';  ?>
<section class="home-combo">
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell cell-img">
                <img src="<?php echo $img; ?>" alt="img">
            </div>
            <div class="cell cell-content flex justify-center flex-column">
                <h2 class="heading-title"><?php echo $title; ?></h2>
                <?php if($content) { echo '<div class="content">'. $content .'</div>'; } ?>
                <a href="<?php echo $btn_link; ?>" class="btn-main"><?php echo $btn_name; ?></a>
            </div>
            
        </div>
    </div>
</section>