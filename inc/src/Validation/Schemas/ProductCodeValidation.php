<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class ProductCodeValidation
{
    public static function createRules(): array
    {
        return [
            'code'            => 'required|string|max:50|unique:vie_product_code,code',
            'hotel_id'        => 'required|int|exists:vie_hotel,id',
            'room_id'         => 'required|int|exists:vie_room,id',
            'booking_type'    => 'required|string|in:night,day',
            'weekday_pattern' => 'nullable|string|max:30',
            'display_name'    => 'required|string|max:255',
            'unit_label'      => 'required|string|max:50',
            'is_active'       => 'nullable|bool',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'code'            => 'nullable|string|max:50|unique:vie_product_code,code,' . $id,
            'hotel_id'        => 'nullable|int|exists:vie_hotel,id',
            'room_id'         => 'nullable|int|exists:vie_room,id',
            'booking_type'    => 'nullable|string|in:night,day',
            'weekday_pattern' => 'nullable|string|max:30',
            'display_name'    => 'nullable|string|max:255',
            'unit_label'      => 'nullable|string|max:50',
            'is_active'       => 'nullable|bool',
        ];
    }
}
