<?php
declare(strict_types=1);

namespace Vie\Cron;

use Vie\Container;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\HookRegistry;

final class NoShowSweep
{
    private const BATCH_LIMIT = 500;

    public static function run(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_order';

        // Dùng WP timezone thay vì DB CURDATE() để biên ngày khớp với cảm nhận
        // của khách (site Asia/Ho_Chi_Minh, DB có thể chạy UTC).
        $today = wp_date('Y-m-d');

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE status = %s
                   AND checkin < %s
                   AND paid_amount = 0
                 ORDER BY id ASC
                 LIMIT %d",
                'pending',
                $today,
                self::BATCH_LIMIT
            )
        );

        if (!is_array($ids) || $ids === []) {
            return;
        }

        $orderRepo = Container::get(OrderRepository::class);
        $logRepo   = Container::get(ActivityLogRepository::class);

        foreach ($ids as $idRaw) {
            $id = (int) $idRaw;

            // Compare-and-swap: UPDATE chỉ thành công khi status vẫn là 'pending'.
            // Phòng race với admin transition vừa confirm/cancel ngay trước cron.
            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                    SET status = %s, updated_at = %s
                  WHERE id = %d AND status = %s AND paid_amount = 0",
                'no_show',
                current_time('mysql'),
                $id,
                'pending'
            ));
            if ($affected !== 1) {
                // Order đã thay đổi trong lúc cron xử lý — skip không log để tránh nhiễu.
                continue;
            }

            $after = $orderRepo->find($id);

            try {
                $logRepo->create([
                    'actor_user_id' => 0,
                    'entity_type'   => 'order',
                    'entity_id'     => $id,
                    'action'        => 'no_show_sweep',
                    'before_json'   => ['status' => 'pending'],
                    'after_json'    => ['status' => 'no_show'],
                    'ip'            => null,
                    'user_agent'    => 'cron:vie_no_show_sweep',
                ]);
            } catch (\Throwable) {
                // ignore log failure
            }

            do_action(HookRegistry::ORDER_NO_SHOW, $id, $after);
        }
    }
}
