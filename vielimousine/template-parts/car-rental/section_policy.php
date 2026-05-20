<?php
use HD\Helper;
\defined( 'ABSPATH' ) || die;
$page_id = $args['page_id'] ?? null;
$title_policy = get_field( 'title_policy_rental', $page_id);
$content_policy = get_field( 'content_policy_rental', $page_id ); ?>
<section class="section rental-policy section-padding">
    <div class="container">
        <?php 
            if($title_policy){
                echo '<div class="heading-group text-center">';
                echo '<h2 class="heading-title">'. $title_policy .'</h2>';
                echo '<img src="'. get_template_directory_uri() . '/resources/img/line-title-vie.png' .'">';
                echo '</div>';
            }
            echo '<div class="content-toggle-wrapper relative">';
            echo '<div class="content-rental collapsed">';
            echo $content_policy;
            echo '</div>';
            echo '<div class="mask"></div>';
            echo '<button class="toggle-button ml-auto mr-auto"><i class="fa-regular fa-chevrons-down"></i><span>Xem thêm</span></button>';
            echo '</div>';
        ?>
    </div>
</section>