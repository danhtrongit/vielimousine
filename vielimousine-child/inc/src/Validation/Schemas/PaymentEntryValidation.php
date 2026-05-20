<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class PaymentEntryValidation
{
    public static function rules(): array
    {
        return [
            'order_id'       => 'required|int|exists:vie_order,id',
            'type'           => 'required|string|in:deposit,payment,refund,void',
            'amount'         => 'required|int|min:1',
            'method'         => 'required|string|in:sepay,bank_transfer,cash,manual',
            'gateway'        => 'nullable|string|max:30',
            'transaction_id' => 'nullable|string|max:100',
            'note'           => 'nullable|string|max:500',
            'paid_at'        => 'nullable|string',
        ];
    }
}
