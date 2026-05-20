<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

final class InvalidWebhookException extends PaymentException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Webhook không hợp lệ: {$reason}");
    }
}
