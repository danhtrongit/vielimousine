<?php
declare(strict_types=1);

namespace Vie\Schema;

final class ActivityLogSchema
{
    public const VERSION = '1.0.0';

    public static function install(\wpdb $wpdb): void
    {
        $table   = $wpdb->prefix . 'vie_activity_log';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            before_json LONGTEXT DEFAULT NULL,
            after_json LONGTEXT DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_actor_user_id (actor_user_id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_action (action),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB {$charset};";

        dbDelta($sql);
    }
}
