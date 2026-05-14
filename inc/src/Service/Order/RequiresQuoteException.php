<?php
declare(strict_types=1);

namespace Vie\Service\Order;

final class RequiresQuoteException extends OrderException
{
    public function __construct(public readonly array $messages, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Đơn hàng yêu cầu báo giá thủ công');
    }
}
