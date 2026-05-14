<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class CouponUsageValidation
{
    public static function createRules(): array
    {
        return [
            'coupon_id'  => 'required|int|exists:vie_coupon,id',
            'order_id'   => 'required|int|exists:vie_order,id',
            'user_email' => 'nullable|email',
            'discount'   => 'required|float|min:0',
        ];
    }
}
