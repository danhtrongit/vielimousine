<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\Repository\ActivityLogRepository;

final class RoomPriceBulkService
{
    public function __construct(private readonly ActivityLogRepository $activityRepo)
    {
    }

    private const CHUNK_CELLS = 500;

    /**
     * Bulk upsert giá phòng / tồn / nguồn theo (room_ids × dates).
     *
     * Chỉ các field client gửi mới được UPDATE trên row đã có — các field
     * còn lại giữ nguyên giá trị cũ. Với INSERT (override mới), điền field
     * thiếu bằng default của room (base_price / extra_adult_price) để tránh
     * "wipe to 0" khi user chỉ muốn set 1 field.
     *
     * @return array{rows_affected: int, dates_count: int, rooms_count: int, cells_count: int}
     */
    public function bulkUpsert(array $scope, array $values, int $actorUserId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_room_price';

        $dates   = $this->expandDates(
            (string) $scope['date_from'],
            (string) $scope['date_to'],
            $scope['weekdays'] ?? null
        );
        $roomIds = array_map('intval', $scope['room_ids']);

        if ($dates === [] || $roomIds === []) {
            return ['rows_affected' => 0, 'dates_count' => 0, 'rooms_count' => 0, 'cells_count' => 0];
        }

        // Detect which value keys client explicitly sent.
        $hasPrice  = array_key_exists('price',             $values);
        $hasExtra  = array_key_exists('extra_adult_price', $values);
        $hasStock  = array_key_exists('stock',             $values);
        $hasActive = array_key_exists('is_active',         $values);
        $hasSource = array_key_exists('source',            $values);

        $priceVal  = $hasPrice  ? (int) $values['price']             : 0;
        $extraVal  = $hasExtra  ? (int) $values['extra_adult_price'] : 0;
        $stockVal  = $hasStock  ? (int) $values['stock']             : 0;
        $activeVal = $hasActive ? (int) (bool) $values['is_active']  : 1;
        $sourceVal = $hasSource ? (string) $values['source']         : 'manual';

        // Per-room defaults — only fetched if user omitted price/extra (INSERT path needs sane fallback).
        $roomDefaults = [];
        if (!$hasPrice || !$hasExtra) {
            $roomTable    = $wpdb->prefix . 'vie_room';
            $placeholders = implode(',', array_fill(0, count($roomIds), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, base_price, extra_adult_price FROM {$roomTable} WHERE id IN ({$placeholders})",
                    $roomIds
                ),
                ARRAY_A
            ) ?: [];
            foreach ($rows as $r) {
                $roomDefaults[(int) $r['id']] = [
                    'price' => (int) $r['base_price'],
                    'extra' => (int) $r['extra_adult_price'],
                ];
            }
        }

        // Dynamic UPDATE clause — only columns user touched get overwritten.
        $updateParts = [];
        if ($hasPrice)  $updateParts[] = 'price = VALUES(price)';
        if ($hasExtra)  $updateParts[] = 'extra_adult_price = VALUES(extra_adult_price)';
        if ($hasStock)  $updateParts[] = 'stock = VALUES(stock)';
        if ($hasActive) $updateParts[] = 'is_active = VALUES(is_active)';
        if ($hasSource) $updateParts[] = 'source = VALUES(source)';
        $updateParts[] = 'updated_at = VALUES(updated_at)';
        $updateClause = implode(', ', $updateParts);

        $now   = current_time('mysql');
        $cells = [];
        foreach ($roomIds as $rid) {
            foreach ($dates as $date) {
                $cells[] = [$rid, $date];
            }
        }
        $chunks = array_chunk($cells, self::CHUNK_CELLS);

        $rowsAffected = 0;
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($chunks as $chunk) {
                $placeholders = [];
                $params       = [];
                foreach ($chunk as [$rid, $date]) {
                    $defaults = $roomDefaults[$rid] ?? ['price' => 0, 'extra' => 0];
                    $placeholders[] = '(%d, %s, %d, %d, %d, %d, %s, %s, %s)';
                    array_push(
                        $params,
                        $rid, $date,
                        $hasPrice ? $priceVal : $defaults['price'],
                        $hasExtra ? $extraVal : $defaults['extra'],
                        $stockVal,    // 0 nếu client không gửi — đúng default cho override mới
                        $activeVal,
                        $sourceVal,
                        $now, $now
                    );
                }
                $sql = "INSERT INTO {$table}
                        (room_id, date, price, extra_adult_price, stock, is_active, source, created_at, updated_at)
                        VALUES " . implode(',', $placeholders) . "
                        ON DUPLICATE KEY UPDATE {$updateClause}";
                $prepared = $wpdb->prepare($sql, ...$params);
                $result   = $wpdb->query($prepared);
                if ($result === false) {
                    throw new \RuntimeException('Bulk upsert failed: ' . $wpdb->last_error);
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
            'entity_type'   => 'room_price_bulk',
            'entity_id'     => 0,
            'action'        => 'bulk_upsert',
            'before_json'   => null,
            'after_json'    => [
                'scope'  => $scope,
                'values' => $values,
                'rooms'  => count($roomIds),
                'dates'  => count($dates),
                'cells'  => count($roomIds) * count($dates),
            ],
        ]);

        return [
            'rows_affected' => $rowsAffected,
            'dates_count'   => count($dates),
            'rooms_count'   => count($roomIds),
            'cells_count'   => count($roomIds) * count($dates),
        ];
    }

    /**
     * @return string[] dates Y-m-d trong range, filter theo weekdays nếu set
     */
    private function expandDates(string $from, string $to, ?array $weekdays): array
    {
        $dates  = [];
        $cursor = new \DateTimeImmutable($from);
        $end    = new \DateTimeImmutable($to);
        $filter = is_array($weekdays) && $weekdays !== []
            ? array_map('intval', $weekdays)
            : null;

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
