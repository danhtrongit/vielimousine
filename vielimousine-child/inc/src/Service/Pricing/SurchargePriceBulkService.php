<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\Repository\ActivityLogRepository;

final class SurchargePriceBulkService
{
    private const CHUNK_CELLS = 500;

    public function __construct(private readonly ActivityLogRepository $activityRepo) {}

    /**
     * Bulk upsert phụ thu theo (surcharge_ids × dates).
     *
     * Chỉ field client gửi mới UPDATE; INSERT mới fill amount thiếu bằng
     * surcharge.amount để khi user "reset" (is_active=false) không wipe về 0.
     *
     * @return array{rows_affected: int, dates_count: int, surcharges_count: int, cells_count: int}
     */
    public function bulkUpsert(array $scope, array $values, int $actorUserId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_surcharge_price';

        $dates = $this->expandDates(
            (string) $scope['date_from'],
            (string) $scope['date_to'],
            $scope['weekdays'] ?? null
        );
        $ids = array_map('intval', $scope['surcharge_ids']);

        if ($dates === [] || $ids === []) {
            return ['rows_affected' => 0, 'dates_count' => 0, 'surcharges_count' => 0, 'cells_count' => 0];
        }

        $hasAmount = array_key_exists('amount',    $values);
        $hasActive = array_key_exists('is_active', $values);

        $amountVal = $hasAmount ? (int) $values['amount']           : 0;
        $activeVal = $hasActive ? (int) (bool) $values['is_active'] : 1;

        // Per-surcharge default — only fetched if user omitted amount.
        $surchargeDefaults = [];
        if (!$hasAmount) {
            $surTable     = $wpdb->prefix . 'vie_surcharge';
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, amount FROM {$surTable} WHERE id IN ({$placeholders})",
                    $ids
                ),
                ARRAY_A
            ) ?: [];
            foreach ($rows as $r) {
                $surchargeDefaults[(int) $r['id']] = (int) $r['amount'];
            }
        }

        $updateParts = [];
        if ($hasAmount) $updateParts[] = 'amount = VALUES(amount)';
        if ($hasActive) $updateParts[] = 'is_active = VALUES(is_active)';
        $updateParts[] = 'updated_at = VALUES(updated_at)';
        $updateClause = implode(', ', $updateParts);

        $now   = current_time('mysql');
        $cells = [];
        foreach ($ids as $sid) {
            foreach ($dates as $date) {
                $cells[] = [$sid, $date];
            }
        }
        $chunks = array_chunk($cells, self::CHUNK_CELLS);

        $rowsAffected = 0;
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($chunks as $chunk) {
                $placeholders = [];
                $params       = [];
                foreach ($chunk as [$sid, $date]) {
                    $placeholders[] = '(%d, %s, %d, %d, %s, %s)';
                    array_push(
                        $params,
                        $sid, $date,
                        $hasAmount ? $amountVal : ($surchargeDefaults[$sid] ?? 0),
                        $activeVal,
                        $now, $now
                    );
                }
                $sql = "INSERT INTO {$table}
                        (surcharge_id, date, amount, is_active, created_at, updated_at)
                        VALUES " . implode(',', $placeholders) . "
                        ON DUPLICATE KEY UPDATE {$updateClause}";
                $prepared = $wpdb->prepare($sql, ...$params);
                $result   = $wpdb->query($prepared);
                if ($result === false) {
                    throw new \RuntimeException('Bulk surcharge upsert failed: ' . $wpdb->last_error);
                }
                $rowsAffected += (int) $result;
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $this->activityRepo->create([
            'actor_user_id' => $actorUserId,
            'entity_type'   => 'surcharge_price_bulk',
            'entity_id'     => 0,
            'action'        => 'bulk_upsert',
            'before_json'   => null,
            'after_json'    => ['scope' => $scope, 'values' => $values, 'cells' => count($ids) * count($dates)],
        ]);

        return [
            'rows_affected'    => $rowsAffected,
            'dates_count'      => count($dates),
            'surcharges_count' => count($ids),
            'cells_count'      => count($ids) * count($dates),
        ];
    }

    private function expandDates(string $from, string $to, ?array $weekdays): array
    {
        $dates  = [];
        $cursor = new \DateTimeImmutable($from);
        $end    = new \DateTimeImmutable($to);
        $filter = is_array($weekdays) && $weekdays !== [] ? array_map('intval', $weekdays) : null;

        while ($cursor <= $end) {
            $dow = (int) $cursor->format('N');
            if ($filter === null || in_array($dow, $filter, true)) {
                $dates[] = $cursor->format('Y-m-d');
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $dates;
    }
}
