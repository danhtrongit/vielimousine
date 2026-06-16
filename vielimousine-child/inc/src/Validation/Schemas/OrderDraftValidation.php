<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class OrderDraftValidation
{
    /**
     * Rule cực lỏng — nháp được phép dở dang. Chỉ kiểm tra kiểu dữ liệu của
     * field có mặt. Validation đầy đủ chỉ chạy lúc "Tạo đơn" (OrderCreateValidation).
     */
    public static function rules(): array
    {
        return [
            'customer_phone' => 'nullable|string|max:50',
            'customer_name'  => 'nullable|string|max:255',
            'customer_email' => 'nullable|email',
            'source'         => 'nullable|string|max:50',
            'customer_note'  => 'nullable|string',
            'coupon_code'    => 'nullable|string|max:50',
            'checkin'        => 'nullable|date',
            'checkout'       => 'nullable|date',
            'nights'         => 'nullable|int|min:0',
            'adults'         => 'nullable|int|min:0|max:20',
            'children'       => 'nullable|int|min:0',
            'child_ages'     => 'nullable|array|max_items:10',
            'subtotal'       => 'nullable|float|min:0',
            'discount'       => 'nullable|float|min:0',
            'total'          => 'nullable|float|min:0',
            'draft_payload'  => 'nullable|array',
        ];
    }
}
