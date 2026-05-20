<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\ChildAssessment;

final class ChildPolicy
{
    private array $assessments = [];
    private int   $freeCount   = 0;
    private int   $policyFreeCount = 0;
    private int   $spareSlotFreeCount = 0;

    /**
     * @param int[] $childAges          All child ages (đã được GuestComposition lọc < adult threshold).
     * @param int   $freeQuota          Number of age-restricted free children (room.free_children_count × numRooms).
     * @param int   $freeAgeCap         Inclusive max age eligible for room-policy free quota.
     * @param int   $spareAdultSlots    Extra free spots from unused adult-included slots; no age restriction.
     */
    public function __construct(
        array $childAges,
        int $freeQuota = 0,
        int $freeAgeCap = 99,
        int $spareAdultSlots = 0,
    ) {
        $ages = array_map('intval', $childAges);
        // Sort DESC: oldest first. Bé lớn nhất tiêu thụ free-quota trước → bé nhỏ
        // (thường free theo rule "dưới X tuổi" của surcharge table) ra rule phụ thu.
        // Đồng bộ với business rule §3.4.
        rsort($ages);

        $usedPolicyFree = 0;
        $usedSpareSlot  = 0;
        $payableIndex   = 0;
        foreach ($ages as $age) {
            $isFree = false;
            if ($age <= $freeAgeCap && $usedPolicyFree < $freeQuota) {
                $usedPolicyFree++;
                $isFree = true;
            } elseif ($usedSpareSlot < $spareAdultSlots) {
                $usedSpareSlot++;
                $isFree = true;
            }
            if ($isFree) {
                $this->freeCount++;
                $childIndex = 0;
            } else {
                $payableIndex++;
                $childIndex = $payableIndex;
            }
            $this->assessments[] = new ChildAssessment(
                age:            $age,
                isFree:         $isFree,
                treatedAsAdult: false,
                childIndex:     $childIndex,
            );
        }
        $this->policyFreeCount = $usedPolicyFree;
        $this->spareSlotFreeCount = $usedSpareSlot;
    }

    public function assessments(): array
    {
        return $this->assessments;
    }

    public function freeChildrenCount(): int
    {
        return $this->freeCount;
    }

    public function policyFreeCount(): int
    {
        return $this->policyFreeCount;
    }

    public function spareSlotFreeCount(): int
    {
        return $this->spareSlotFreeCount;
    }
}
