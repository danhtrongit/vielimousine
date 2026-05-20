<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\Repository\ActivityLogRepository;

final class TicketPriceBulkService
{
    private const CHUNK_CELLS = 500;

    public function __construct(private readonly ActivityLogRepository $activityRepo) {}

    /**
     * Bulk upsert giá vé theo (hotel_ids × dates).
     *
     * Chỉ field client gửi mới UPDATE; INSERT mới fill ticket_price thiếu bằng
     * hotel.default_ticket_price để khi user "reset" (is_active=false) không
     * vô tình wipe giá vé về 0.
     *
     * @return array{rows_affected: int, dates_count: int, hotels_count: int, cells_count: int}
     */
    public function bulkUpsert(array $scope, array $values, int $actorUserId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_ticket_price';

        $dates = $this->expandDates(
            (string) $scope['date_from'],
            (string) $scope['date_to'],
            $scope['weekdays'] ?? null
        );
        $hotelIds = array_map('intval', $scope['hotel_ids']);
        $routeId  = (int) ($scope['route_id'] ?? 0);

        if ($dates === [] || $hotelIds === []) {
            return ['rows_affected' => 0, 'dates_count' => 0, 'hotels_count' => 0, 'cells_count' => 0];
        }

        $hasPrice  = array_key_exists('ticket_price', $values);
        $hasActive = array_key_exists('is_active',    $values);

        $priceVal  = $hasPrice  ? (int) $values['ticket_price']    : 0;
        $activeVal = $hasActive ? (int) (bool) $values['is_active'] : 1;

        // Per-hotel default — only fetched if user omitted ticket_price.
        $hotelDefaults = [];
        if (!$hasPrice) {
            $hotelTable   = $wpdb->prefix . 'vie_hotel';
            $placeholders = implode(',', array_fill(0, count($hotelIds), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, default_ticket_price FROM {$hotelTable} WHERE id IN ({$placeholders})",
                    $hotelIds
                ),
                ARRAY_A
            ) ?: [];
            foreach ($rows as $r) {
                $hotelDefaults[(int) $r['id']] = (int) $r['default_ticket_price'];
            }
        }

        $updateParts = [];
        if ($hasPrice)  $updateParts[] = 'ticket_price = VALUES(ticket_price)';
        if ($hasActive) $updateParts[] = 'is_active = VALUES(is_active)';
        $updateParts[] = 'updated_at = VALUES(updated_at)';
        $updateClause = implode(', ', $updateParts);

        $now   = current_time('mysql');
        $cells = [];
        foreach ($hotelIds as $hid) {
            foreach ($dates as $date) {
                $cells[] = [$hid, $date];
            }
        }
        $chunks = array_chunk($cells, self::CHUNK_CELLS);

        $rowsAffected = 0;
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($chunks as $chunk) {
                $placeholders = [];
                $params       = [];
                foreach ($chunk as [$hid, $date]) {
                    $placeholders[] = '(%d, %d, %s, %d, %d, %s, %s)';
                    array_push(
                        $params,
                        $hid, $routeId, $date,
                        $hasPrice ? $priceVal : ($hotelDefaults[$hid] ?? 0),
                        $activeVal,
                        $now, $now
                    );
                }
                $sql = "INSERT INTO {$table}
                        (hotel_id, route_id, date, ticket_price, is_active, created_at, updated_at)
                        VALUES " . implode(',', $placeholders) . "
                        ON DUPLICATE KEY UPDATE {$updateClause}";
                $prepared = $wpdb->prepare($sql, ...$params);
                $result   = $wpdb->query($prepared);
                if ($result === false) {
                    throw new \RuntimeException('Bulk ticket upsert failed: ' . $wpdb->last_error);
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
            'entity_type'   => 'ticket_price_bulk',
            'entity_id'     => 0,
            'action'        => 'bulk_upsert',
            'before_json'   => null,
            'after_json'    => ['scope' => $scope, 'values' => $values, 'cells' => count($hotelIds) * count($dates)],
        ]);

        return [
            'rows_affected' => $rowsAffected,
            'dates_count'   => count($dates),
            'hotels_count'  => count($hotelIds),
            'cells_count'   => count($hotelIds) * count($dates),
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
