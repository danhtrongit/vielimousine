<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class CouponValidateValidation
{
    public static function rules(): array
    {
        return [
            'code'           => 'required|string|max:50',
            'order_subtotal' => 'required|int|min:0',
            'hotel_id'       => 'nullable|int|exists:vie_hotel,id',
            'room_id'        => 'nullable|int|exists:vie_room,id',
            'booking_type'   => 'nullable|string|in:room,combo',
            'user_email'     => 'nullable|email',
        ];
    }
}
