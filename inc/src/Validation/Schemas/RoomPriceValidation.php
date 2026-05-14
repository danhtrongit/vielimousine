<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class RoomPriceValidation
{
    public static function createRules(): array
    {
        return [
            'room_id'           => 'required|int|exists:vie_room,id',
            'date'              => 'required|date',
            'price'             => 'required|float|min:0',
            'extra_adult_price' => 'required|float|min:0',
            'stock'             => 'required|int|min:0',
            'is_active'         => 'nullable|bool',
            'source'            => 'nullable|string|max:30',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'room_id'           => 'nullable|int|exists:vie_room,id',
            'date'              => 'nullable|date',
            'price'             => 'nullable|float|min:0',
            'extra_adult_price' => 'nullable|float|min:0',
            'stock'             => 'nullable|int|min:0',
            'is_active'         => 'nullable|bool',
            'source'            => 'nullable|string|max:30',
        ];
    }
}
