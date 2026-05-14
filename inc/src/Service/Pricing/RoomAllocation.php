<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

final class RoomAllocation
{
    private int   $numRooms;
    private int   $extraAdultBeds;
    private bool  $requiresQuote = false;
    private array $messages      = [];

    public function __construct(array $room, GuestComposition $g, int $userRooms)
    {
        $capacity     = (int) $room['max_adults'];
        $maxChildren  = (int) $room['max_children'];
        $included     = (int) $room['included_adults'];

        $effAdults    = $g->effectiveAdults();
        $effChildren  = $g->effectiveChildren();

        if ($userRooms > 0) {
            $this->numRooms = $userRooms;
        } else {
            $byAdults   = (int) ceil($effAdults / max(1, $capacity));
            $byChildren = $maxChildren > 0
                ? (int) ceil($effChildren / $maxChildren)
                : 1;
            $this->numRooms = max($byAdults, $byChildren, 1);
        }

        $this->extraAdultBeds = max(0, $effAdults - $this->numRooms * $included);

        $totalCapacityAdults   = $this->numRooms * $capacity;
        $totalCapacityChildren = $this->numRooms * $maxChildren;
        $extraBedsAllowed      = $this->numRooms * max(0, $capacity - $included);

        if ($effAdults > $totalCapacityAdults) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Số người lớn vượt quá sức chứa — Liên hệ báo giá';
        }
        if ($effChildren > $totalCapacityChildren) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Số trẻ em vượt quá sức chứa — Liên hệ báo giá';
        }
        if ($this->extraAdultBeds > $extraBedsAllowed) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Số giường phụ vượt giới hạn — Liên hệ báo giá';
        }
    }

    public function numRooms(): int
    {
        return $this->numRooms;
    }

    public function extraAdultBeds(): int
    {
        return $this->extraAdultBeds;
    }

    public function requiresQuote(): bool
    {
        return $this->requiresQuote;
    }

    public function messages(): array
    {
        return $this->messages;
    }
}
