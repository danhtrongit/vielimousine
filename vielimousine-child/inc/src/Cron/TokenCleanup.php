<?php
declare(strict_types=1);

namespace Vie\Cron;

final class TokenCleanup
{
    public static function run(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_token';

        // Xoá token đã revoked > 30 ngày
        $wpdb->query(
            "DELETE FROM {$table}
             WHERE revoked_at IS NOT NULL
               AND revoked_at < (NOW() - INTERVAL 30 DAY)"
        );

        // Xoá token đã expired > 7 ngày
        $wpdb->query(
            "DELETE FROM {$table}
             WHERE expires_at < (NOW() - INTERVAL 7 DAY)"
        );
    }
}
