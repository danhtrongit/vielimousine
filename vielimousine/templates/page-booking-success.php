<?php

/**
 * The template for displaying `booking success`
 * Template Name: Page Đặt chỗ thành công
 * Template Post Type: page
 *
 * @author Vie
 */

use HD\Helper;

\defined( 'ABSPATH' ) || die;

// header
get_header();

if ( have_posts() ) {
	the_post();
}

$ACF = \HD\Helper::getFields( get_the_ID() );
$html_field = get_field('html_field');
?>
<section class="section page-booking-success">
	<div class="section-custom-html section-padding">
        <div class="container">
            <?php if($html_field){
                echo '<div class="code">'. $html_field .'</div>';
            } ?>
        </div>
        
	</div>
</section>
<?php

// footer
get_footer();