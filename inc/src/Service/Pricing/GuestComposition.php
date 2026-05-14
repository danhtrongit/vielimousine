<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

final class GuestComposition
{
    private int   $effectiveAdults;
    private array $childrenUnderFloor = [];
    private array $convertedAdultAges = [];

    public function __construct(int $adults, array $childAges, int $roomAdultAgeFloor)
    {
        $converted = 0;
        foreach ($childAges as $age) {
            $age = (int) $age;
            if ($age >= $roomAdultAgeFloor) {
                $converted++;
                $this->convertedAdultAges[] = $age;
            } else {
                $this->childrenUnderFloor[] = $age;
            }
        }
        $this->effectiveAdults = $adults + $converted;
    }

    public function effectiveAdults(): int
    {
        return $this->effectiveAdults;
    }

    public function effectiveChildren(): int
    {
        return count($this->childrenUnderFloor);
    }

    public function childrenUnderFloor(): array
    {
        return $this->childrenUnderFloor;
    }

    public function convertedAdultAges(): array
    {
        return $this->convertedAdultAges;
    }
}
