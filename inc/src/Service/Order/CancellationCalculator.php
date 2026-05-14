<?php
declare(strict_types=1);

namespace Vie\Service\Order;

use Vie\DTO\RefundCalculation;

final class CancellationCalculator
{
    public function compute(array $order, array $items, array $hotelsById): RefundCalculation
    {
        $now             = current_time('timestamp', true);
        $itemRefunds     = [];
        $totalRefundable = 0;

        foreach ($items as $item) {
            if (($item['status'] ?? 'active') !== 'active') {
                continue;
            }

            $hotelId = (int) ($item['hotel_id'] ?? 0);
            $hotel   = $hotelsById[$hotelId] ?? null;
            $policy  = is_array($hotel['cancellation_policy'] ?? null) ? $hotel['cancellation_policy'] : null;
            $rules   = is_array($policy['rules'] ?? null) ? $policy['rules'] : [];

            usort($rules, static fn($a, $b) => ((int) ($b['hours_before_checkin'] ?? 0)) <=> ((int) ($a['hours_before_checkin'] ?? 0)));

            $checkinTs   = strtotime(((string) ($item['checkin'] ?? '')) . ' 00:00:00 +07:00');
            $deltaHours  = $checkinTs !== false ? ($checkinTs - $now) / 3600 : -1.0;
            $penaltyPct  = 100;
            $note        = 'Hủy sau checkin: 100%';

            foreach ($rules as $rule) {
                $threshold = (int) ($rule['hours_before_checkin'] ?? 0);
                if ($deltaHours >= $threshold) {
                    $penaltyPct = (int) ($rule['penalty_percent'] ?? 100);
                    $note       = (string) ($rule['description'] ?? "Hủy trước {$threshold}h: {$penaltyPct}%");
                    break;
                }
            }

            $lineTotal  = (int) ($item['line_total'] ?? 0);
            $refundable = (int) floor($lineTotal * (100 - $penaltyPct) / 100);

            $itemRefunds[] = [
                'item_id'         => (int) ($item['id'] ?? 0),
                'line_total'      => $lineTotal,
                'penalty_percent' => $penaltyPct,
                'refundable'      => $refundable,
                'notes'           => $note,
            ];
            $totalRefundable += $refundable;
        }

        $paidAmount   = (int) ($order['paid_amount'] ?? 0);
        $actualRefund = min($totalRefundable, $paidAmount);

        return new RefundCalculation(
            itemRefunds:     $itemRefunds,
            totalRefundable: $totalRefundable,
            paidAmount:      $paidAmount,
            actualRefund:    $actualRefund,
            remainingHeld:   $paidAmount - $actualRefund,
        );
    }
}
