<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_content' ) {
	return;
}
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$btn_link = $args['btn_link'] ?? ''; ?>
<section class="section about-us__content section-padding">
    <div class="container">
        <div class="wrapper text-center">
            <?php if($title){
            echo '<h2 class="heading-title">'. $title .'</h2>';
            }
            if($content){
                echo '<div class="content">'. $content .'</div>';
            }
            if($btn_link){
                echo '<a href="'. $btn_link .'" class="btn-main">Xem chi tiết</a>';
            } ?>
        </div>
        
    </div>
</section>