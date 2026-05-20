<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class RoomValidation
{
    public static function createRules(): array
    {
        return [
            'hotel_id'              => 'required|int|exists:vie_hotel,id',
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'included_adults'       => 'required|int|min:1',
            'max_adults'            => 'required|int|min:1',
            'max_children'          => 'required|int|min:0',
            'base_price'            => 'required|float|min:0',
            'extra_adult_price'     => 'required|float|min:0',
            'free_children_count'   => 'required|int|min:0',
            'free_children_max_age' => 'nullable|int|min:0',
            'area'                  => 'nullable|int|min:0',
            'bed_type'              => 'nullable|string|max:50',
            'bed_count'             => 'nullable|int|min:0',
            'view'                  => 'nullable|string|max:100',
            'floor'                 => 'nullable|string|max:50',
            'amenities'             => 'nullable|array',
            'thumbnail_id'          => 'nullable|int',
            'gallery'               => 'nullable|array',
            'is_active'             => 'nullable|bool',
            'sort_order'            => 'nullable|int|min:0',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'hotel_id'              => 'nullable|int|exists:vie_hotel,id',
            'name'                  => 'nullable|string|max:255',
            'description'           => 'nullable|string',
            'included_adults'       => 'nullable|int|min:1',
            'max_adults'            => 'nullable|int|min:1',
            'max_children'          => 'nullable|int|min:0',
            'base_price'            => 'nullable|float|min:0',
            'extra_adult_price'     => 'nullable|float|min:0',
            'free_children_count'   => 'nullable|int|min:0',
            'free_children_max_age' => 'nullable|int|min:0',
            'area'                  => 'nullable|int|min:0',
            'bed_type'              => 'nullable|string|max:50',
            'bed_count'             => 'nullable|int|min:0',
            'view'                  => 'nullable|string|max:100',
            'floor'                 => 'nullable|string|max:50',
            'amenities'             => 'nullable|array',
            'thumbnail_id'          => 'nullable|int',
            'gallery'               => 'nullable|array',
            'is_active'             => 'nullable|bool',
            'sort_order'            => 'nullable|int|min:0',
        ];
    }
}
