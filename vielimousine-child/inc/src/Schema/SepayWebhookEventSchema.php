<?php
declare(strict_types=1);

namespace Vie\Schema;

final class SepayWebhookEventSchema
{
    public const VERSION = '1.1.0';

    public static function install(\wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'vie_sepay_webhook_event';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sepay_id VARCHAR(100) NOT NULL,
            object_code VARCHAR(100) DEFAULT NULL,
            raw_payload LONGTEXT NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            review_reason VARCHAR(255) DEFAULT NULL,
            error_message VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sepay_id (sepay_id),
            KEY idx_status_updated (status, updated_at),
            KEY idx_object_code (object_code)
        ) ENGINE=InnoDB {$charset};";
        dbDelta($sql);
    }
}
