<?php
declare(strict_types=1);

namespace Vie\Schema;

final class TokenSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_token';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            hash CHAR(64) NOT NULL,
            family CHAR(36) NOT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            ua VARCHAR(500) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_hash (hash),
            KEY idx_user_id (user_id),
            KEY idx_family (family),
            KEY idx_expires_at (expires_at)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
