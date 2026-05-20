<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\RoomPriceRepository;
use Vie\Repository\RoomRepository;
use Vie\Support\ResponseEnvelope;

final class PublicRoomPriceController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $dateFrom = (string) $request->get_param('date_from');
        $dateTo   = (string) $request->get_param('date_to');
        $roomIdsParam = $request->get_param('room_ids');
        $roomId  = (int) $request->get_param('room_id');

        if ($dateFrom === '' || $dateTo === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'date_from và date_to bắt buộc'],
            ], 422);
        }

        // Cap range to 180 days to prevent abuse on public endpoint.
        $fromTs = strtotime($dateFrom);
        $toTs   = strtotime($dateTo);
        if ($fromTs === false || $toTs === false) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'Ngày không hợp lệ'],
            ], 422);
        }
        if (($toTs - $fromTs) / 86400 > 180) {
            return ResponseEnvelope::error([
                ['code' => 'range_too_wide', 'field' => 'date_to', 'message' => 'Khoảng ngày tối đa 180 ngày'],
            ], 422);
        }

        $roomIds = [];
        if (is_array($roomIdsParam)) {
            $roomIds = array_filter(array_map('intval', $roomIdsParam));
        } elseif (is_string($roomIdsParam) && $roomIdsParam !== '') {
            $roomIds = array_filter(array_map('intval', explode(',', $roomIdsParam)));
        }
        if ($roomId > 0) $roomIds[] = $roomId;
        $roomIds = array_values(array_unique($roomIds));

        if ($roomIds === []) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'room_id', 'message' => 'Cần truyền room_id hoặc room_ids[]'],
            ], 422);
        }

        // Verify rooms exist + active (avoid leaking inactive room data).
        global $wpdb;
        $tableRoom = $wpdb->prefix . 'vie_room';
        $place = implode(',', array_fill(0, count($roomIds), '%d'));
        $activeRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, base_price, extra_adult_price FROM {$tableRoom} WHERE id IN ({$place}) AND is_active = 1",
                ...$roomIds
            ),
            ARRAY_A
        );
        $activeIds = array_map(static fn($r) => (int) $r['id'], $activeRows ?? []);
        if ($activeIds === []) {
            return ResponseEnvelope::success(['prices' => [], 'rooms' => []]);
        }

        // Fetch room_price rows.
        $repo = Container::get(RoomPriceRepository::class);
        $rows = [];
        $perPage = 2000;
        $page = 1;
        do {
            $resp = $repo->all([
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'is_active' => 1,
                'per_page'  => $perPage,
                'page'      => $page,
            ]);
            $data = $resp['data'] ?? [];
            foreach ($data as $row) {
                if (!in_array((int) $row['room_id'], $activeIds, true)) continue;
                $rows[] = [
                    'room_id' => (int) $row['room_id'],
                    'date'    => (string) $row['date'],
                    'price'   => (int) $row['price'],
                    'stock'   => (int) $row['stock'],
                ];
            }
            $hasNext = (bool) ($resp['pagination']['has_next'] ?? false);
            $page++;
        } while ($hasNext && $page <= 5); // safety cap

        // Room defaults so client can fallback for dates without entry.
        $rooms = array_map(static fn($r) => [
            'id'                => (int) $r['id'],
            'base_price'        => (int) $r['base_price'],
            'extra_adult_price' => (int) $r['extra_adult_price'],
        ], $activeRows);

        return ResponseEnvelope::success([
            'prices'    => $rows,
            'rooms'     => $rooms,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);
    }
}
