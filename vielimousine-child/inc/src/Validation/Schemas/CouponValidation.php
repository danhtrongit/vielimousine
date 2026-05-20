<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class CouponValidation
{
    public static function createRules(): array
    {
        return [
            'code'                 => 'required|string|max:50|unique:vie_coupon,code',
            'description'          => 'nullable|string',
            'type'                 => 'required|string|in:percentage,fixed',
            'value'                => 'required|float|min:0',
            'min_order'            => 'nullable|float|min:0',
            'max_discount'         => 'nullable|float|min:0',
            'usage_limit'          => 'nullable|int|min:0',
            'usage_limit_per_user' => 'nullable|int|min:0',
            'valid_from'           => 'nullable|string',
            'valid_to'             => 'nullable|string',
            'hotel_ids'            => 'nullable|array',
            'room_ids'             => 'nullable|array',
            'booking_types'        => 'nullable|array',
            'is_active'            => 'nullable|bool',
            'sales_only'           => 'nullable|bool',
            'created_by'           => 'nullable|int',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'code'                 => 'nullable|string|max:50|unique:vie_coupon,code,' . $id,
            'description'          => 'nullable|string',
            'type'                 => 'nullable|string|in:percentage,fixed',
            'value'                => 'nullable|float|min:0',
            'min_order'            => 'nullable|float|min:0',
            'max_discount'         => 'nullable|float|min:0',
            'usage_limit'          => 'nullable|int|min:0',
            'usage_limit_per_user' => 'nullable|int|min:0',
            'valid_from'           => 'nullable|string',
            'valid_to'             => 'nullable|string',
            'hotel_ids'            => 'nullable|array',
            'room_ids'             => 'nullable|array',
            'booking_types'        => 'nullable|array',
            'is_active'            => 'nullable|bool',
            'sales_only'           => 'nullable|bool',
        ];
    }
}
