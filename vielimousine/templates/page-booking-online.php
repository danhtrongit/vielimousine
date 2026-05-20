<?php

/**
 * The template for displaying `booking online`
 * Template Name: Page Đặt vé trực tuyến
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

// $ACF                = Helper::getFields( get_the_ID() );
$ACF = \HD\Helper::getFields( get_the_ID() );
$script_form_booking = get_field('script_form_booking');
?>
<section class="section page-booking-online">
	<div class="section-form-booking section-padding">
        <div class="container">
            <?php if($script_form_booking){
                echo '<div class="code">'. $script_form_booking .'</div>';
            } ?>
        </div>
        
	</div>
</section>
<?php

// footer
get_footer();