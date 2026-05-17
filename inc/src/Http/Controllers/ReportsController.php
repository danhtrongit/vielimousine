<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Support\ResponseEnvelope;

final class ReportsController
{
    /** Cap khoảng ngày để chặn full-table scan / DoS. */
    private const MAX_RANGE_DAYS = 366;
    /** Cap số phần tử filter array. */
    private const MAX_FILTER_LEN = 50;

    public static function byHotel(\WP_REST_Request $request): \WP_REST_Response
    {
        $dateFrom = (string) $request->get_param('date_from');
        $dateTo   = (string) $request->get_param('date_to');

        if ($dateFrom === '' || $dateTo === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'date_from và date_to bắt buộc'],
            ], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'date_from / date_to phải đúng định dạng YYYY-MM-DD'],
            ], 422);
        }
        if ($dateFrom > $dateTo) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_to', 'message' => 'date_to phải >= date_from'],
            ], 422);
        }
        // Cap khoảng ngày.
        $fromTs = strtotime($dateFrom);
        $toTs   = strtotime($dateTo);
        if ($fromTs === false || $toTs === false) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'Ngày không hợp lệ'],
            ], 422);
        }
        $rangeDays = (int) floor(($toTs - $fromTs) / 86400) + 1;
        if ($rangeDays > self::MAX_RANGE_DAYS) {
            return ResponseEnvelope::error([
                ['code' => 'range_too_large', 'field' => 'date_to', 'message' => sprintf('Khoảng ngày tối đa %d ngày', self::MAX_RANGE_DAYS)],
            ], 422);
        }

        global $wpdb;
        $orderTbl = $wpdb->prefix . 'vie_order';
        $itemTbl  = $wpdb->prefix . 'vie_order_item';
        $hotelTbl = $wpdb->prefix . 'vie_hotel';

        $sources         = array_slice((array) $request->get_param('sources'),        0, self::MAX_FILTER_LEN);
        $salesUserIds    = array_slice((array) $request->get_param('sales_user_ids'), 0, self::MAX_FILTER_LEN);
        $hotelIds        = array_slice((array) $request->get_param('hotel_ids'),      0, self::MAX_FILTER_LEN);

        $where  = ["o.status != 'cancelled'", "i.status = 'active'", 'o.checkin >= %s', 'o.checkin <= %s'];
        $params = [$dateFrom, $dateTo];

        if ($sources !== []) {
            $place = implode(',', array_fill(0, count($sources), '%s'));
            $where[] = "o.source IN ({$place})";
            foreach ($sources as $s) { $params[] = (string) $s; }
        }
        if ($salesUserIds !== []) {
            $place = implode(',', array_fill(0, count($salesUserIds), '%d'));
            $where[] = "o.sales_user_id IN ({$place})";
            foreach ($salesUserIds as $s) { $params[] = (int) $s; }
        }
        if ($hotelIds !== []) {
            $place = implode(',', array_fill(0, count($hotelIds), '%d'));
            $where[] = "i.hotel_id IN ({$place})";
            foreach ($hotelIds as $h) { $params[] = (int) $h; }
        }

        $whereSql = implode(' AND ', $where);
        $sql = "
            SELECT
                i.hotel_id AS hotel_id,
                COALESCE(h.name, CONCAT('Hotel #', i.hotel_id)) AS hotel_name,
                COUNT(DISTINCT o.id) AS orders_count,
                COALESCE(SUM(i.line_total), 0)   AS revenue,
                COALESCE(SUM(i.cost_total), 0)   AS cost,
                COALESCE(SUM(i.profit_total), 0) AS profit
            FROM {$orderTbl} o
            INNER JOIN {$itemTbl} i ON i.order_id = o.id
            LEFT JOIN {$hotelTbl} h ON h.id = i.hotel_id
            WHERE {$whereSql}
            GROUP BY i.hotel_id, h.name
            ORDER BY revenue DESC
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $data = array_map(static fn(array $r) => [
            'hotel_id'   => (int) $r['hotel_id'],
            'hotel_name' => (string) $r['hotel_name'],
            'orders'     => (int) $r['orders_count'],
            'revenue'    => (int) $r['revenue'],
            'cost'       => (int) $r['cost'],
            'profit'     => (int) $r['profit'],
        ], $rows ?? []);

        return ResponseEnvelope::success($data);
    }
}
