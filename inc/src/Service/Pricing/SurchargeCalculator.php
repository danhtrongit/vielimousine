<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\ChildAssessment;
use Vie\Repository\SurchargePriceRepository;
use Vie\Repository\SurchargeRepository;

final class SurchargeCalculator
{
    private array $rules = [];
    private array $priceOverrides = [];

    public function __construct(
        private readonly int $roomId,
        private readonly array $nights,
        private readonly array $assessments,
        SurchargeRepository $surchargeRepo,
        SurchargePriceRepository $surchargePriceRepo,
    ) {
        $this->loadRules($surchargeRepo);
        $this->loadOverrides($surchargePriceRepo);
    }

    public function nightlyBreakdown(): array
    {
        $breakdown = [];
        foreach ($this->nights as $date) {
            $items = [];
            foreach ($this->assessments as $c) {
                if ($c->isFree || $c->treatedAsAdult) {
                    continue;
                }
                $rule = $this->findRule($c);
                if ($rule === null) {
                    continue;
                }
                if ((int) $rule['is_free'] === 1) {
                    continue;
                }
                $amount = $this->amountFor($rule, $date);
                if ($amount <= 0) {
                    continue;
                }
                $items[] = [
                    'label'  => (string) $rule['label'],
                    'age'    => $c->age,
                    'amount' => $amount,
                ];
            }
            $breakdown[$date] = $items;
        }
        return $breakdown;
    }

    public function total(): int
    {
        $total = 0;
        foreach ($this->nightlyBreakdown() as $items) {
            foreach ($items as $item) {
                $total += (int) $item['amount'];
            }
        }
        return $total;
    }

    public function totalForAssessment(ChildAssessment $c): int
    {
        if ($c->isFree || $c->treatedAsAdult) {
            return 0;
        }
        $rule = $this->findRule($c);
        if ($rule === null || (int) $rule['is_free'] === 1) {
            return 0;
        }
        $sum = 0;
        foreach ($this->nights as $date) {
            $sum += $this->amountFor($rule, $date);
        }
        return $sum;
    }

    private function findRule(ChildAssessment $c): ?array
    {
        foreach ($this->rules as $rule) {
            if ($c->age >= (int) $rule['age_from'] && $c->age <= (int) $rule['age_to']) {
                return $rule;
            }
        }
        return null;
    }

    private function amountFor(array $rule, string $date): int
    {
        $key = $rule['id'] . '|' . $date;
        if (isset($this->priceOverrides[$key])) {
            return (int) $this->priceOverrides[$key];
        }
        return (int) $rule['amount'];
    }

    private function loadRules(SurchargeRepository $repo): void
    {
        $result = $repo->all([
            'room_id'    => $this->roomId,
            'guest_type' => 'child',
            'is_active'  => 1,
            'sort'       => 'sort_order',
            'per_page'   => 100,
        ]);
        $this->rules = $result['data'] ?? [];
    }

    private function loadOverrides(SurchargePriceRepository $repo): void
    {
        if ($this->rules === [] || $this->nights === []) {
            return;
        }
        $ruleIds = array_map(static fn($r) => (int) $r['id'], $this->rules);
        $dates   = $this->nights;
        sort($dates);

        $result = $repo->all([
            'is_active' => 1,
            'date_from' => $dates[0],
            'date_to'   => $dates[count($dates) - 1],
            'per_page'  => 100,
        ]);
        foreach ($result['data'] ?? [] as $row) {
            if (!in_array((int) $row['surcharge_id'], $ruleIds, true)) {
                continue;
            }
            if (!in_array($row['date'], $this->nights, true)) {
                continue;
            }
            $this->priceOverrides[$row['surcharge_id'] . '|' . $row['date']] = (int) $row['amount'];
        }
    }
}
