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

    /**
     * Detailed CSV-export rows: 1 row per active order_item in date range.
     * Joins order + item + hotel + sales user; aggregates payments per order
     * (deposit sum, total paid, last method/date). Frontend formats to CSV.
     */
    public static function ordersDetailedExport(\WP_REST_Request $request): \WP_REST_Response
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
        $orderTbl    = $wpdb->prefix . 'vie_order';
        $itemTbl     = $wpdb->prefix . 'vie_order_item';
        $hotelTbl    = $wpdb->prefix . 'vie_hotel';
        $paymentTbl  = $wpdb->prefix . 'vie_payment_log';
        $usersTbl    = $wpdb->users;

        $sources      = array_slice((array) $request->get_param('sources'),        0, self::MAX_FILTER_LEN);
        $salesUserIds = array_slice((array) $request->get_param('sales_user_ids'), 0, self::MAX_FILTER_LEN);
        $hotelIds     = array_slice((array) $request->get_param('hotel_ids'),      0, self::MAX_FILTER_LEN);

        $where  = ["o.status != 'cancelled'", "i.status = 'active'", 'o.created_at >= %s', 'o.created_at <= %s'];
        $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

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
                o.id              AS order_id,
                o.code            AS order_code,
                o.created_at      AS order_created_at,
                o.confirmed_at    AS order_confirmed_at,
                o.customer_id     AS customer_id,
                o.customer_name   AS customer_name,
                o.customer_phone  AS customer_phone,
                o.source          AS order_source,
                o.invoice_number  AS invoice_number,
                o.voucher_code    AS voucher_code,
                o.coupon_code     AS coupon_code,
                o.total           AS order_total,
                o.paid_amount     AS order_paid,
                o.discount        AS order_discount,
                o.customer_note   AS customer_note,
                o.internal_note   AS internal_note,
                COALESCE(o.sales_user_id, o.created_by) AS sales_user_id,
                u.display_name    AS sales_name,
                i.id              AS item_id,
                i.name            AS item_name,
                i.unit_label      AS item_unit,
                i.booking_type    AS booking_type,
                i.quantity        AS item_qty,
                i.nights          AS item_nights,
                i.checkin         AS item_checkin,
                i.checkout        AS item_checkout,
                i.room_subtotal   AS item_room_subtotal,
                i.extra_adult_total AS item_extra_adult,
                i.child_surcharge_total AS item_child_surcharge,
                i.ticket_subtotal AS item_ticket,
                i.line_discount   AS item_discount,
                i.line_total      AS item_total,
                i.cost_total      AS item_cost,
                i.profit_total    AS item_profit,
                i.room_id         AS room_id,
                i.hotel_id        AS hotel_id,
                i.partner_name    AS partner_name,
                i.hotel_area      AS hotel_area,
                i.supplier_booking_code AS supplier_booking_code,
                h.name            AS hotel_name,
                h.city            AS hotel_city
            FROM {$orderTbl} o
            INNER JOIN {$itemTbl} i ON i.order_id = o.id
            LEFT JOIN {$hotelTbl} h ON h.id = i.hotel_id
            LEFT JOIN {$usersTbl} u ON u.ID = COALESCE(o.sales_user_id, o.created_by)
            WHERE {$whereSql}
            ORDER BY o.created_at DESC, o.id DESC, i.id ASC
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];

        // Aggregate payments per order
        $payments = [];
        if ($rows !== []) {
            $orderIds = array_values(array_unique(array_map(static fn($r) => (int) $r['order_id'], $rows)));
            $place    = implode(',', array_fill(0, count($orderIds), '%d'));
            $pSql = "
                SELECT
                    order_id,
                    SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) AS deposit_sum,
                    SUM(CASE WHEN type='payment' THEN amount ELSE 0 END) AS payment_sum,
                    MAX(paid_at) AS last_paid_at,
                    GROUP_CONCAT(DISTINCT method ORDER BY paid_at DESC SEPARATOR ', ') AS methods
                FROM {$paymentTbl}
                WHERE order_id IN ({$place})
                  AND type IN ('deposit','payment')
                GROUP BY order_id
            ";
            $pRows = $wpdb->get_results($wpdb->prepare($pSql, ...$orderIds), ARRAY_A) ?: [];
            foreach ($pRows as $pr) {
                $payments[(int) $pr['order_id']] = [
                    'deposit_sum'  => (int) $pr['deposit_sum'],
                    'payment_sum'  => (int) $pr['payment_sum'],
                    'last_paid_at' => $pr['last_paid_at'] ?: null,
                    'methods'      => (string) ($pr['methods'] ?? ''),
                ];
            }
        }

        $data = array_map(static function (array $r) use ($payments): array {
            $orderId  = (int) $r['order_id'];
            $pay      = $payments[$orderId] ?? [
                'deposit_sum' => 0, 'payment_sum' => 0, 'last_paid_at' => null, 'methods' => '',
            ];
            $total    = (int) $r['order_total'];
            $paid     = (int) $r['order_paid'];
            $itemQty  = max(1, (int) $r['item_qty']);
            $nights   = max(1, (int) $r['item_nights']);
            $roomSub  = (int) $r['item_room_subtotal'];
            $sellUnit = (int) round($roomSub / ($itemQty * $nights));
            $surchargeTotal = (int) $r['item_extra_adult']
                + (int) $r['item_child_surcharge']
                + (int) $r['item_ticket'];

            return [
                'order_id'              => $orderId,
                'order_code'            => (string) $r['order_code'],
                'order_created_at'      => (string) $r['order_created_at'],
                'order_confirmed_at'    => $r['order_confirmed_at'] ?? null,
                'customer_id'           => (int) $r['customer_id'],
                'customer_name'         => (string) $r['customer_name'],
                'customer_phone'        => (string) $r['customer_phone'],
                'order_source'          => (string) $r['order_source'],
                'invoice_number'        => $r['invoice_number'] ?? '',
                'voucher_code'          => $r['voucher_code'] ?? '',
                'coupon_code'           => $r['coupon_code'] ?? '',
                'order_total'           => $total,
                'order_paid'            => $paid,
                'order_outstanding'     => max(0, $total - $paid),
                'order_discount'        => (int) $r['order_discount'],
                'customer_note'         => (string) ($r['customer_note'] ?? ''),
                'internal_note'         => (string) ($r['internal_note'] ?? ''),
                'sales_user_id'         => $r['sales_user_id'] ? (int) $r['sales_user_id'] : null,
                'sales_name'            => (string) ($r['sales_name'] ?? ''),
                'item_id'               => (int) $r['item_id'],
                'item_name'             => (string) $r['item_name'],
                'item_unit'             => (string) ($r['item_unit'] ?? 'đêm'),
                'booking_type'          => (string) $r['booking_type'],
                'item_qty'              => $itemQty,
                'item_nights'           => $nights,
                'item_checkin'          => (string) $r['item_checkin'],
                'item_checkout'         => (string) $r['item_checkout'],
                'sell_unit_price'       => $sellUnit,
                'surcharge_total'       => $surchargeTotal,
                'item_total'            => (int) $r['item_total'],
                'item_cost'             => (int) $r['item_cost'],
                'item_profit'           => (int) $r['item_profit'],
                'room_id'               => (int) $r['room_id'],
                'hotel_id'              => (int) $r['hotel_id'],
                'hotel_name'            => (string) ($r['hotel_name'] ?? ''),
                'hotel_city'            => (string) ($r['hotel_city'] ?? ''),
                'partner_name'          => (string) ($r['partner_name'] ?? ''),
                'hotel_area'            => (string) ($r['hotel_area'] ?? ''),
                'supplier_booking_code' => (string) ($r['supplier_booking_code'] ?? ''),
                'pay_methods'           => (string) $pay['methods'],
                'pay_last_at'           => $pay['last_paid_at'],
                'pay_deposit_sum'       => (int) $pay['deposit_sum'],
                'pay_payment_sum'       => (int) $pay['payment_sum'],
            ];
        }, $rows);

        return ResponseEnvelope::success($data);
    }
}
