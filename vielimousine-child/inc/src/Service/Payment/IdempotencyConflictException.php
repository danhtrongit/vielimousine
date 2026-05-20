<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

final class IdempotencyConflictException extends PaymentException
{
    public function __construct(public readonly int $existingPaymentLogId, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Payment với transaction_id này đã tồn tại');
    }
}
