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

        $now      = current_time('mysql');
        $price    = (int) ($values['price']             ?? 0);
        $extra    = (int) ($values['extra_adult_price'] ?? 0);
        $stock    = (int) ($values['stock']             ?? 0);
        $isActive = isset($values['is_active']) ? (int) (bool) $values['is_active'] : 1;
        $source   = (string) ($values['source'] ?? 'manual');

        // Build danh sách cells, chunk theo CHUNK_CELLS để tránh vượt
        // max_allowed_packet (mặc định 4-64MB tùy hosting) và max_prepared_stmt_count.
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
                    $placeholders[] = '(%d, %s, %d, %d, %d, %d, %s, %s, %s)';
                    array_push(
                        $params,
                        $rid, $date,
                        $price, $extra, $stock, $isActive, $source,
                        $now, $now
                    );
                }
                $sql = "INSERT INTO {$table}
                        (room_id, date, price, extra_adult_price, stock, is_active, source, created_at, updated_at)
                        VALUES " . implode(',', $placeholders) . "
                        ON DUPLICATE KEY UPDATE
                            price = VALUES(price),
                            extra_adult_price = VALUES(extra_adult_price),
                            stock = VALUES(stock),
                            is_active = VALUES(is_active),
                            source = VALUES(source),
                            updated_at = VALUES(updated_at)";
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
