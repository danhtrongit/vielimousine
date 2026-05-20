<?php
declare(strict_types=1);

namespace Vie\Service\Coupon;

final class CouponException extends \RuntimeException
{
    public function __construct(public readonly array $messages, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Mã giảm giá không hợp lệ');
    }
}
