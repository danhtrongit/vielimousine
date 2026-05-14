<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\ChildAssessment;

final class ChildPolicy
{
    private array $assessments = [];
    private int   $freeCount   = 0;

    public function __construct(array $childrenUnderFloor, array $convertedAdultAges, int $freeQuota)
    {
        $ages = $childrenUnderFloor;
        usort($ages, static fn($a, $b) => $b <=> $a);

        foreach ($ages as $i => $age) {
            $isFree = $i < $freeQuota;
            if ($isFree) {
                $this->freeCount++;
            }
            $this->assessments[] = new ChildAssessment(
                age:            $age,
                isFree:         $isFree,
                treatedAsAdult: false,
            );
        }

        foreach ($convertedAdultAges as $age) {
            $this->assessments[] = new ChildAssessment(
                age:            (int) $age,
                isFree:         false,
                treatedAsAdult: true,
            );
        }
    }

    public function assessments(): array
    {
        return $this->assessments;
    }

    public function freeChildrenCount(): int
    {
        return $this->freeCount;
    }
}
