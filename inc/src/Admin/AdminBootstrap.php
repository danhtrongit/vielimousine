<?php
declare(strict_types=1);

namespace Vie\Admin;

use Vie\Container;
use Vie\Service\Hotel\HotelSyncService;

/**
 * Entry point cho wp-admin: register save_post hook + first-run hotel backfill.
 */
final class AdminBootstrap
{
    private const FIRST_RUN_OPTION = 'vie_hotel_sync_v1';

    public static function register(): void
    {
        add_action('save_post_hotel', [self::class, 'onSavePost'], 20, 2);
        add_action('admin_init',       [self::class, 'maybeBackfill']);
    }

    public static function onSavePost(int $postId, $post): void
    {
        if (!($post instanceof \WP_Post)) {
            return;
        }
        try {
            Container::get(HotelSyncService::class)->pullFromPost($postId, $post);
        } catch (\Throwable $e) {
            // Không throw — không được block save_post của admin
            error_log('[vie] hotel reverse sync fail: ' . $e->getMessage());
        }
    }

    public static function maybeBackfill(): void
    {
        if (get_option(self::FIRST_RUN_OPTION) === 'done') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        try {
            $stats = Container::get(HotelSyncService::class)->backfillAll();
            update_option(self::FIRST_RUN_OPTION, 'done', false);

            add_action('admin_notices', static function () use ($stats): void {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>'
                    . 'Vielimousine: Đã đồng bộ Hotel với WP Posts — tạo %d, cập nhật %d, bỏ qua %d.'
                    . '</p></div>',
                    (int) $stats['created'],
                    (int) $stats['updated'],
                    (int) $stats['skipped']
                );
            });
        } catch (\Throwable $e) {
            error_log('[vie] hotel first-run backfill fail: ' . $e->getMessage());
        }
    }
}
