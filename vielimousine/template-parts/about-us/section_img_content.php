<?php
\defined( 'ABSPATH' ) || die;
$acf_fc_layout = $args['acf_fc_layout'] ?? '';
if ( $acf_fc_layout !== 'section_img_content' ) {
	return;
}
$sub_title = $args['sub_title'] ?? '';
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$btn_link = $args['btn_link'] ?? '';
$btn_name = $args['btn_name'] ?? '';
$btn_name = $btn_name ?: 'Xem thêm';
$img = $args['img'] ?? '';
$css_class = $args['css_class'] ?? ''; ?>
<section class="section about-content_img section-padding <?php if($css_class){ echo $css_class; } ?>">
    <div class="container">
        <div class="wrapper flex flex-x">
            <div class="cell cell-content">
                <h2 class="heading-title">
                    <?php if($sub_title){
                        echo '<span class="sub-title">'. $sub_title .'</span>';
                    }
                    echo '<span>'. $title .'</span>'; ?>
                </h2>
                <?php if($content){
                    echo '<div class="content">'. $content .'</div>';
                }
                if($btn_link){
                    echo '<a href="'. $btn_link .'" class="fcy-popup btn-main">'. $btn_name .'</a>';
                } ?>
            </div>
            <div class="cell cell-img">
                <?php if($img){
                    echo '<img src="'. $img .'" alt="image">';
                } ?>
            </div>
        </div>
        
    </div>
</section>