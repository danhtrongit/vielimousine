<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class TicketPriceValidation
{
    public static function createRules(): array
    {
        return [
            'hotel_id'     => 'required|int|exists:vie_hotel,id',
            'route_id'     => 'nullable|int|min:0',
            'date'         => 'required|date',
            'ticket_price' => 'required|float|min:0',
            'is_active'    => 'nullable|bool',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'hotel_id'     => 'nullable|int|exists:vie_hotel,id',
            'route_id'     => 'nullable|int|min:0',
            'date'         => 'nullable|date',
            'ticket_price' => 'nullable|float|min:0',
            'is_active'    => 'nullable|bool',
        ];
    }
}
