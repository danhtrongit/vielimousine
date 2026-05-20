<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\RoomPriceRepository;
use Vie\Repository\SurchargePriceRepository;
use Vie\Repository\TicketPriceRepository;
use Vie\Support\ResponseEnvelope;

final class PricingMatrixController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
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
                ['code' => 'validation_error', 'field' => 'date_from', 'message' => 'date format phải là YYYY-MM-DD'],
            ], 422);
        }

        // Bỏ qua pagination clamp 100 của AbstractRepository — matrix cần toàn bộ
        // override trong window để UI tô đúng từng cell.
        $rooms      = Container::get(RoomPriceRepository::class)->findAllInDateRange($dateFrom, $dateTo);
        $surcharges = Container::get(SurchargePriceRepository::class)->findAllInDateRange($dateFrom, $dateTo);
        $tickets    = Container::get(TicketPriceRepository::class)->findAllInDateRange($dateFrom, $dateTo);

        return ResponseEnvelope::success([
            'room_prices'      => $rooms,
            'surcharge_prices' => $surcharges,
            'ticket_prices'    => $tickets,
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
        ]);
    }
}
