<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$title_main = Helper::getField( 'title_main' );
$desc_main = Helper::getField( 'desc_main' );
$content_main = Helper::getField( 'content_main' );
?>
<section class="section route-main-content section-padding relative">
    <div class="group-heading text-center">
        <?php if($title_main){
            echo '<h2 class="heading-title">'. $title_main .'</h2>';
        }
        if($desc_main){
            echo '<div class="desc ml-auto mr-auto">'. $desc_main .'</div>';
        } ?>
    </div>
    <?php if($content_main){
        echo '<div class="content-toggle-wrapper">';
        echo '<div class="content-route collapsed">';
        echo $content_main;
        echo '</div>';
        echo '<div class="mask"></div>';
        echo '<button class="toggle-button ml-auto mr-auto">Xem thêm</button>';
        echo '</div>';
    } ?>
</section>