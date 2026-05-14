<?php
declare(strict_types=1);

namespace Vie\Frontend;

final class PublicAssets
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'vie-public',
            VIE_CHILD_URL . '/inc/assets/css/frontend.css',
            [],
            VIE_CHILD_VERSION
        );

        wp_enqueue_script(
            'vie-public',
            VIE_CHILD_URL . '/inc/assets/js/vie-public.js',
            [],
            VIE_CHILD_VERSION,
            true
        );

        wp_localize_script('vie-public', 'VieRest', [
            'root'            => esc_url_raw(rest_url(VIE_API_NAMESPACE . '/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'checkoutUrl'     => esc_url_raw(home_url('/dat-phong/')),
            'successUrl'      => esc_url_raw(home_url('/dat-phong-thanh-cong/')),
        ]);
    }
}
