<?php
if (!defined('ABSPATH')) {
    exit;
}

function generate_acf_shortcodes() {
    // Lấy dữ liệu từ repeater trong option page
    if (have_rows('cta_setting_repeat', 'option')):
        while (have_rows('cta_setting_repeat', 'option')): the_row();
            $cta_img_set = get_sub_field('cta_img_set');
            $cta_link = get_sub_field('cta_link');
            $cta_form_shortcode = get_sub_field('cta_form_shortcode');
            $cta_shortcode_id = get_sub_field('ten_id_cta');

            if ($cta_shortcode_id) {
                // Tạo shortcode dựa trên `shortcode_id`
                add_shortcode($cta_shortcode_id, function() use ($cta_img_set, $cta_link,  $cta_form_shortcode, $cta_shortcode_id) {
                    ob_start();
                    if ($cta_link) {

                        echo '<a href="' . esc_url($cta_link) . '" class="custom-button" rel="nofollow" target="_blank">';
                        echo '<img src="' . esc_url($cta_img_set['url']) . '" alt="' . esc_attr($cta_img_set['alt']) . '">';
                        echo '</a>';
                        
                    } elseif ($cta_form_shortcode) {
                        echo '<button class="custom-button" data-popup-id="popup-' . esc_attr($cta_shortcode_id) . '" onclick="showPopup(this)">'; 
                        echo '<img src="' . esc_url($cta_img_set['url']) . '" alt="' . esc_attr($cta_img_set['alt']) . '">';
                        echo '</button>';

                        echo '<div id="popup-' . esc_attr($cta_shortcode_id) . '" class="cta_popup-content" style="display: none;">';
                        echo '<div class="popup-inner">';
                        echo '<br><button onclick="closePopup(this)">x</button>';
                        echo do_shortcode($cta_form_shortcode);
                        echo '</div>';
                        echo '</div>';
                    }
                    return ob_get_clean();
                });
            }
        endwhile;
    endif;
}
add_action('init', 'generate_acf_shortcodes');

function my_custom_popup_script() {
    ?>
    <script type="text/javascript">
        function showPopup(button) {
            var popupId = button.getAttribute('data-popup-id');
            var popup = document.getElementById(popupId);
            if (popup) {
                popup.style.display = 'flex';
                document.querySelector('body').classList.add('no-scroll');
            }
        }

        function closePopup(button) {
            var popup = button.closest('.cta_popup-content');
            if (popup) {
                popup.style.display = 'none'; 
                document.querySelector('body').classList.remove('no-scroll');
            }
        }
    </script>
    <?php
}
add_action('wp_footer', 'my_custom_popup_script');

?>