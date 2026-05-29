<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\ChildAssessment;

final class ChildPolicy
{
    private array $assessments = [];
    private int   $freeCount   = 0;
    private int   $policyFreeCount = 0;

    /**
     * @param int[] $childAges  All child ages (đã lọc < adult threshold bởi GuestComposition).
     * @param int   $freeQuota  Số bé được miễn theo chính sách phòng (free_children_count × numRooms).
     * @param int   $freeAgeCap Tuổi tối đa (inclusive) đủ điều kiện miễn theo chính sách.
     */
    public function __construct(
        array $childAges,
        int $freeQuota = 0,
        int $freeAgeCap = 99,
    ) {
        $ages = array_map('intval', $childAges);
        // Sort DESC: bé lớn nhất tiêu thụ free-quota trước (đồng bộ business rule §3.4).
        rsort($ages);

        $usedPolicyFree = 0;
        $payableIndex   = 0;
        foreach ($ages as $age) {
            $isFree = false;
            if ($age <= $freeAgeCap && $usedPolicyFree < $freeQuota) {
                $usedPolicyFree++;
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
    }

    public function assessments(): array      { return $this->assessments; }
    public function freeChildrenCount(): int  { return $this->freeCount; }
    public function policyFreeCount(): int    { return $this->policyFreeCount; }
}
