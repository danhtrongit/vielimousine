<?php
\defined( 'ABSPATH' ) || die;
// $acf_fc_layout = $args['acf_fc_layout'] ?? '';
// if ( $acf_fc_layout !== 'section_why_choose' ) {
// 	return;
// }
$title = get_field('title_why_choose', 'option');
$lists_why  = get_field('lists_why','option');
?>
<section class="section tour-why-choose section-padding relative">
    <div class="container">
        <?php if($title){
            echo '<h2 class="heading-title text-center">'. $title .'</h2>';
        }
        if($lists_why){
            echo '<ul class="wrapper flex flex-x">';
            foreach($lists_why as $val){
                echo '<li class="item">';
                echo '<div class="img relative">';
                echo '<img src="'. $val['img'] .'">';
                echo '</div>';
                echo '<div class="title text-center">'. $val['title'] .'</div>';
                echo '<div class="content text-center">'. $val['content'] .'</div>';
                echo '</li>';
            }
            echo '</ul>';
        } ?>
    </div>
</section>