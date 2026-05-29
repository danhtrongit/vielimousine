<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

final class RoomAllocation
{
    private const MAX_AUTO_ROOMS = 10;

    private int   $numRooms;
    private int   $extraAdultBeds;
    private int   $spareAdultSlots;
    private bool  $roomsExpanded = false;
    private bool  $requiresQuote = false;
    private array $messages      = [];

    public function __construct(array $room, GuestComposition $g, int $userRooms)
    {
        $capacity     = (int) $room['max_adults'];
        $maxChildren  = (int) $room['max_children'];
        $included     = (int) $room['included_adults'];

        $effAdults    = $g->effectiveAdults();
        $effChildren  = $g->effectiveChildren();

        // Số phòng tối thiểu để chứa nhóm theo sức chứa từng phòng.
        $byAdults   = (int) ceil($effAdults / max(1, $capacity));
        $byChildren = $maxChildren > 0 ? (int) ceil($effChildren / $maxChildren) : 0;
        $needed     = max($byAdults, $byChildren, 1);

        // userRooms = số phòng tối thiểu khách muốn; tự tăng nếu thiếu chỗ.
        $requested      = $userRooms > 0 ? $userRooms : 0;
        $this->numRooms = max($requested, $needed, 1);
        $this->roomsExpanded = ($requested > 0 && $this->numRooms > $requested);

        $this->extraAdultBeds  = max(0, $effAdults - $this->numRooms * $included);
        $this->spareAdultSlots = max(0, $this->numRooms * $included - $effAdults);

        // requiresQuote chỉ khi không thể chứa dù đã tăng phòng.
        if ($maxChildren === 0 && $effChildren > 0) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Hạng phòng này không nhận trẻ em — Liên hệ báo giá';
        } elseif ($this->numRooms > self::MAX_AUTO_ROOMS) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Số khách vượt quá sức chứa — Liên hệ báo giá';
        } elseif ($this->roomsExpanded) {
            $this->messages[]    = "Đã tăng lên {$this->numRooms} phòng để đủ chỗ cho nhóm";
        }
    }

    public function numRooms(): int        { return $this->numRooms; }
    public function extraAdultBeds(): int  { return $this->extraAdultBeds; }
    public function spareAdultSlots(): int { return $this->spareAdultSlots; }
    public function roomsExpanded(): bool  { return $this->roomsExpanded; }
    public function requiresQuote(): bool  { return $this->requiresQuote; }
    public function messages(): array      { return $this->messages; }
}
