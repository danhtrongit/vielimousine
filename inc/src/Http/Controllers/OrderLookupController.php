<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Http\RateLimiter;
use Vie\Repository\CustomerRepository;
use Vie\Repository\HotelRepository;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\RoomRepository;
use Vie\Support\ResponseEnvelope;

final class OrderLookupController
{
    public static function lookup(\WP_REST_Request $request): \WP_REST_Response
    {
        // Rate-limit chống brute-force code+phone (endpoint công khai, không auth).
        if ($denied = RateLimiter::check('order_lookup', 10, 300)) {
            return $denied;
        }

        $code  = trim((string) $request->get_param('code'));
        $phone = trim((string) $request->get_param('phone'));

        if ($code === '' || $phone === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => null, 'message' => 'Thiếu code hoặc phone'],
            ], 422);
        }

        $phone = CustomerRepository::normalizePhone($phone);

        $orderRepo = Container::get(OrderRepository::class);
        $order     = $orderRepo->findByCode($code);

        if ($order === null || $order['customer_phone'] !== $phone) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }

        $itemRepo  = Container::get(OrderItemRepository::class);
        $roomRepo  = Container::get(RoomRepository::class);
        $hotelRepo = Container::get(HotelRepository::class);

        $items = $itemRepo->all([
            'order_id' => (int) $order['id'],
            'per_page' => 100,
        ])['data'] ?? [];

        $publicItems = [];
        foreach ($items as $item) {
            $room  = $roomRepo->find((int) $item['room_id']);
            $hotel = $hotelRepo->find((int) $item['hotel_id']);
            $publicItems[] = [
                'hotel_name'           => $hotel['name'] ?? null,
                'room_name'            => $room['name']  ?? $item['name'],
                'booking_type'         => $item['booking_type'],
                'checkin'              => $item['checkin'],
                'checkout'             => $item['checkout'],
                'nights'               => $item['nights'],
                'adults'               => $item['adults'],
                'children'             => $item['children'],
                'ticket_count'         => $item['ticket_count'],
                'line_total'           => $item['line_total'],
                'partner_name'         => $item['partner_name'],
                'supplier_booking_code'=> $item['supplier_booking_code'],
                'status'               => $item['status'],
            ];
        }

        return ResponseEnvelope::success([
            'code'           => $order['code'],
            'status'         => $order['status'],
            'payment_status' => $order['payment_status'],
            'source'         => $order['source'],
            'checkin'        => $order['checkin'],
            'checkout'       => $order['checkout'],
            'nights'         => $order['nights'],
            'adults'         => $order['adults'],
            'children'       => $order['children'],
            'subtotal'       => $order['subtotal'],
            'discount'       => $order['discount'],
            'total'          => $order['total'],
            'paid_amount'    => $order['paid_amount'],
            'currency'       => $order['currency'],
            'customer_name'  => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'items'          => $publicItems,
        ]);
    }
}
