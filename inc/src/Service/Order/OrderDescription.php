<?php
declare(strict_types=1);

namespace Vie\Service\Order;

final class OrderDescription
{
    public function build(array $order, array $items): string
    {
        $code     = (string) ($order['code'] ?? '');
        $name     = (string) ($order['customer_name'] ?? '');
        $phone    = (string) ($order['customer_phone'] ?? '');
        $hotelTxt = '';
        if ($items !== []) {
            $first = $items[0];
            $hotelTxt = (string) ($first['name'] ?? '') . ' x' . (int) ($first['quantity'] ?? 1);
        }

        return trim("{$code} | {$name} | {$phone} | {$hotelTxt}");
    }
}
