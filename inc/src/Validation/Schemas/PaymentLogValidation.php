<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class PaymentLogValidation
{
    public static function createRules(): array
    {
        return [
            'order_id'       => 'required|int|exists:vie_order,id',
            'type'           => 'required|string|in:deposit,payment,refund',
            'amount'         => 'required|float|min:0',
            'method'         => 'required|string|max:30',
            'gateway'        => 'nullable|string|max:30',
            'transaction_id' => 'nullable|string|max:100',
            'note'           => 'nullable|string',
            'paid_at'        => 'nullable|string',
            'created_by'     => 'nullable|int',
            'raw_payload'    => 'nullable|array',
        ];
    }
}
