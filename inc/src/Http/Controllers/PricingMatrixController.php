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

        $params = [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'per_page'  => 5000,
        ];

        $roomRepo   = Container::get(RoomPriceRepository::class);
        $surRepo    = Container::get(SurchargePriceRepository::class);
        $ticketRepo = Container::get(TicketPriceRepository::class);

        $rooms     = $roomRepo->all($params);
        $surcharges = $surRepo->all($params);
        $tickets   = $ticketRepo->all($params);

        return ResponseEnvelope::success([
            'room_prices'      => $rooms['data'] ?? [],
            'surcharge_prices' => $surcharges['data'] ?? [],
            'ticket_prices'    => $tickets['data'] ?? [],
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
        ]);
    }
}
