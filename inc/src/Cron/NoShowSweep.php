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

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE status = %s
                   AND checkin < CURDATE()
                   AND paid_amount = 0
                 ORDER BY id ASC
                 LIMIT %d",
                'pending',
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
            $before = $orderRepo->find($id);
            if ($before === null || ($before['status'] ?? '') !== 'pending') {
                continue;
            }

            $wpdb->update(
                $table,
                [
                    'status'     => 'no_show',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );

            $after = $orderRepo->find($id);

            try {
                $logRepo->create([
                    'actor_user_id' => 0,
                    'entity_type'   => 'order',
                    'entity_id'     => $id,
                    'action'        => 'no_show_sweep',
                    'before_json'   => ['status' => $before['status'] ?? null],
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
