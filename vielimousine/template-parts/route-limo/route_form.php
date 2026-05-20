<?php
\defined( 'ABSPATH' ) || die;
use HD\Helper;
$form_sidebar = Helper::getField( 'form_sidebar' );
$title_form = $form_sidebar['title'];
$desc_form = $form_sidebar['desc'];
$sl_form = $form_sidebar['sl_form'];
?>
<div class="route-form relative">
    <div class="wrapper">
        <div class="group-heading text-center">
            <p class="title-form heading-title"><?php echo $title_form; ?></p>
            <div class="desc"><?php echo $desc_form; ?></div>
        </div>
        <?php echo do_shortcode('[contact-form-7 id="' . esc_attr($sl_form) . '"]'); ?>
    </div>
</div>