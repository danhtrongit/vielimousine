<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class OrderValidation
{
    public static function createRules(): array
    {
        // Note: 'code' và 'idempotency_key' KHÔNG ở đây — system-generated:
        //   - 'code' sinh bởi OrderCodeGenerator (Phase 4)
        //   - 'idempotency_key' lấy từ header X-Idempotency-Key qua OrderController::store
        // Để client không thể inject/overwrite chúng qua POST body.
        return [
            'customer_id'        => 'required|int|exists:vie_customer,id',
            'customer_phone'     => 'required|phone',
            'customer_name'      => 'required|string|max:255',
            'customer_email'     => 'nullable|email',
            'customer_vat'       => 'nullable|array',
            'sales_user_id'      => 'nullable|int',
            'source'             => 'required|string|max:50',
            'checkin'            => 'required|date',
            'checkout'           => 'required|date',
            'nights'             => 'required|int|min:0',
            'adults'             => 'required|int|min:1',
            'children'           => 'nullable|int|min:0',
            'child_ages'         => 'nullable|array',
            'customer_note'      => 'nullable|string',
            'internal_note'      => 'nullable|string',
            'pickup'             => 'nullable|array',
            'dropoff'            => 'nullable|array',
            'subtotal'           => 'nullable|float|min:0',
            'discount'           => 'nullable|float|min:0',
            'tax'                => 'nullable|float|min:0',
            'total'              => 'nullable|float|min:0',
            'cost_total'         => 'nullable|float|min:0',
            'profit_total'       => 'nullable|float',
            'currency'           => 'nullable|string|max:3',
            'coupon_id'          => 'nullable|int',
            'coupon_code'        => 'nullable|string|max:50',
            'payment_status'     => 'nullable|string|in:pending,partial,paid,refunded',
            'status'             => 'nullable|string|in:pending,confirmed,cancelled,completed',
        ];
    }

    public static function updateRules(int $id): array
    {
        // PUT /orders/{id} chỉ cho phép sửa metadata.
        // Money / status / payment / coupon / invoice phải đi qua endpoint riêng:
        //   - status              → POST /orders/{id}/transition hoặc /cancel
        //   - paid_amount / payment_status → PaymentLedger (qua webhook hoặc /payments)
        //   - subtotal/total/discount → không sửa được (cancel + tạo lại)
        //   - coupon              → không sửa được sau khi tạo
        //   - invoice_number      → InvoiceService::getOrAssignNumber (LOCK TABLES)
        return [
            'customer_phone'          => 'nullable|phone',
            'customer_name'           => 'nullable|string|max:255',
            'customer_email'          => 'nullable|email',
            'customer_vat'            => 'nullable|array',
            'sales_user_id'           => 'nullable|int',
            'customer_note'           => 'nullable|string',
            'internal_note'           => 'nullable|string',
            'pickup'                  => 'nullable|array',
            'dropoff'                 => 'nullable|array',
            'partner_payment_status'  => 'nullable|string|max:30',
            'voucher_code'            => 'nullable|string|max:100',
            'cost_total'              => 'nullable|float|min:0',
            'profit_total'            => 'nullable|float',
        ];
    }
}
