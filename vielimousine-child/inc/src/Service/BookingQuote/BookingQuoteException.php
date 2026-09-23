<?php
declare(strict_types=1);

namespace Vie\Service\BookingQuote;

final class BookingQuoteException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'booking_quote_error',
        public readonly int $httpStatus = 400,
        public readonly ?string $field = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
