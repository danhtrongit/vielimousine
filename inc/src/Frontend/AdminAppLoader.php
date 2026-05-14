<?php
declare(strict_types=1);

namespace Vie\Frontend;

final class AdminAppLoader
{
    public static function register(): void
    {
        add_action('after_switch_theme', [self::class, 'flushRewrites']);

        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'vie_admin_app';
            return $vars;
        });

        // register() được gọi qua add_action('init') — đã đang trong init phase,
        // gọi add_rewrite_rule() trực tiếp (không cần wrap thêm action).
        add_rewrite_rule(
            '^vie-admin(/.*)?$',
            'index.php?vie_admin_app=1',
            'top'
        );

        add_action('template_redirect', [self::class, 'serve']);
    }

    public static function serve(): void
    {
        if (!get_query_var('vie_admin_app')) {
            return;
        }

        // SPA tự handle JWT auth (Phase 6) — không cần WP login wall.
        // Mọi API call sẽ bị Auth middleware chặn nếu không có Bearer hợp lệ.

        $index = VIE_CHILD_PATH . '/admin-app/dist/index.html';

        if (!is_file($index)) {
            wp_die(
                'Admin SPA chưa build. Chạy: <code>cd admin-app && npm install && npm run build</code>',
                'Vielimousine Admin',
                ['response' => 503]
            );
        }

        // Clean WP output buffers — SPA HTML cần serve verbatim, không append wp_footer().
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        status_header(200);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        readfile($index);
        exit;
    }

    public static function flushRewrites(): void
    {
        add_rewrite_rule(
            '^vie-admin(/.*)?$',
            'index.php?vie_admin_app=1',
            'top'
        );
        flush_rewrite_rules();
    }
}
