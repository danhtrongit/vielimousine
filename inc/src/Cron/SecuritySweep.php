<?php
declare(strict_types=1);

namespace Vie\Cron;

final class SecuritySweep
{
    public const OPTION_BLOCKED_IPS = 'vie_blocked_ips';
    private const THRESHOLD         = 50;

    public static function run(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_activity_log';

        $since = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ip, COUNT(*) AS c
                 FROM {$table}
                 WHERE action = %s
                   AND created_at >= %s
                   AND ip IS NOT NULL AND ip <> ''
                 GROUP BY ip
                 HAVING c > %d",
                'login_failed',
                $since,
                self::THRESHOLD
            ),
            ARRAY_A
        );

        $now      = time();
        $existing = get_option(self::OPTION_BLOCKED_IPS, []);
        $blocked  = is_array($existing) ? $existing : [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ip = (string) ($row['ip'] ?? '');
                if ($ip === '') continue;
                $blocked[$ip] = $now + DAY_IN_SECONDS;
            }
        }

        // Purge expired entries
        $blocked = array_filter(
            $blocked,
            static fn($exp) => is_numeric($exp) && (int) $exp > $now
        );

        update_option(self::OPTION_BLOCKED_IPS, $blocked, false);
    }

    public static function isBlocked(string $ip): bool
    {
        if ($ip === '') return false;
        $blocked = get_option(self::OPTION_BLOCKED_IPS, []);
        if (!is_array($blocked) || !isset($blocked[$ip])) return false;
        return (int) $blocked[$ip] > time();
    }
}
