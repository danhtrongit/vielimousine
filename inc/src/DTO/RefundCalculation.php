<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class RefundCalculation
{
    public function __construct(
        public array $itemRefunds,
        public int   $totalRefundable,
        public int   $paidAmount,
        public int   $actualRefund,
        public int   $remainingHeld,
    ) {
    }

    public function toArray(): array
    {
        return [
            'item_refunds'     => $this->itemRefunds,
            'total_refundable' => $this->totalRefundable,
            'paid_amount'      => $this->paidAmount,
            'actual_refund'    => $this->actualRefund,
            'remaining_held'   => $this->remainingHeld,
        ];
    }
}
