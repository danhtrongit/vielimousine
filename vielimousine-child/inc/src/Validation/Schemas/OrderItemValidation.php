<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class OrderItemValidation
{
    public static function createRules(): array
    {
        return [
            'order_id'               => 'required|int|exists:vie_order,id',
            'hotel_id'               => 'required|int|exists:vie_hotel,id',
            'room_id'                => 'required|int|exists:vie_room,id',
            'name'                   => 'required|string|max:255',
            'booking_type'           => 'required|string|in:night,day',
            'unit_label'             => 'required|string|max:50',
            'quantity'               => 'required|int|min:1',
            'checkin'                => 'required|date',
            'checkout'               => 'required|date',
            'nights'                 => 'required|int|min:0',
            'adults'                 => 'required|int|min:1',
            'children'               => 'nullable|int|min:0',
            'child_ages'             => 'nullable|array',
            'room_subtotal'          => 'nullable|float|min:0',
            'extra_adult_total'      => 'nullable|float|min:0',
            'child_surcharge_total'  => 'nullable|float|min:0',
            'ticket_count'           => 'nullable|int|min:0',
            'ticket_subtotal'        => 'nullable|float|min:0',
            'line_discount'          => 'nullable|float|min:0',
            'line_total'             => 'nullable|float|min:0',
            'cost_total'             => 'nullable|float|min:0',
            'profit_total'           => 'nullable|float',
            'partner_name'           => 'nullable|string|max:255',
            'hotel_area'             => 'nullable|string|max:50',
            'supplier_booking_code'  => 'nullable|string|max:100',
            'pricing_snapshot'       => 'required|array',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'name'                   => 'nullable|string|max:255',
            'quantity'               => 'nullable|int|min:1',
            'room_subtotal'          => 'nullable|float|min:0',
            'extra_adult_total'      => 'nullable|float|min:0',
            'child_surcharge_total'  => 'nullable|float|min:0',
            'ticket_count'           => 'nullable|int|min:0',
            'ticket_subtotal'        => 'nullable|float|min:0',
            'line_discount'          => 'nullable|float|min:0',
            'line_total'             => 'nullable|float|min:0',
            'cost_total'             => 'nullable|float|min:0',
            'profit_total'           => 'nullable|float',
            'partner_name'           => 'nullable|string|max:255',
            'hotel_area'             => 'nullable|string|max:50',
            'supplier_booking_code'  => 'nullable|string|max:100',
            'status'                 => 'nullable|string|in:active,cancelled',
            'cancel_reason'          => 'nullable|string',
        ];
    }
}
