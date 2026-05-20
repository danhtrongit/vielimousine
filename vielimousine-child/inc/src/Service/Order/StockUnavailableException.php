<?php
declare(strict_types=1);

namespace Vie\Service\Order;

final class StockUnavailableException extends OrderException
{
    public function __construct(public readonly array $unavailableDates, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Hết phòng cho một số ngày đã chọn');
    }
}
