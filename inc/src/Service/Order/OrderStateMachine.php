<?php
declare(strict_types=1);

namespace Vie\Service\Order;

final class OrderStateMachine
{
    private const TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled', 'no_show'],
        'confirmed' => ['cancelled', 'completed'],
        'cancelled' => [],
        'completed' => [],
        'no_show'   => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw new IllegalTransitionException(
                "Không thể chuyển trạng thái đơn hàng từ '{$from}' sang '{$to}'"
            );
        }
    }
}
