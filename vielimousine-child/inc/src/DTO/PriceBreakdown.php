<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class PriceBreakdown
{
    public function __construct(
        public int     $numRooms,
        public int     $nights,
        public int     $effectiveAdults,
        public int     $effectiveChildren,
        public int     $extraAdultBeds,
        public int     $seatCount,
        public int     $billableSeats,
        public int     $freeChildSeats,
        public array   $nightly,
        public array   $childAssessments,
        public int     $roomSubtotal,
        public int     $extraAdultSubtotal,
        public int     $childSurchargeTotal,
        public int     $ticketSubtotal,
        public int     $childTicketSubtotal,
        public int     $subtotal,
        public int     $discount,
        public int     $total,
        public int     $costTotal,
        public bool    $requiresQuote,
        public bool    $roomsExpanded,
        public array   $messages,
        public ?string $unavailableDate = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'num_rooms'             => $this->numRooms,
            'nights'                => $this->nights,
            'effective_adults'      => $this->effectiveAdults,
            'effective_children'    => $this->effectiveChildren,
            'extra_adult_beds'      => $this->extraAdultBeds,
            'seat_count'            => $this->seatCount,
            'billable_seats'        => $this->billableSeats,
            'free_child_seats'      => $this->freeChildSeats,
            'nightly'               => $this->nightly,
            'child_assessments'     => array_map(
                static fn(ChildAssessment $c) => $c->toArray(),
                $this->childAssessments
            ),
            'room_subtotal'         => $this->roomSubtotal,
            'extra_adult_subtotal'  => $this->extraAdultSubtotal,
            'child_surcharge_total' => $this->childSurchargeTotal,
            'ticket_subtotal'       => $this->ticketSubtotal,
            'child_ticket_subtotal' => $this->childTicketSubtotal,
            'subtotal'              => $this->subtotal,
            'discount'              => $this->discount,
            'total'                 => $this->total,
            'cost_total'            => $this->costTotal,
            'requires_quote'        => $this->requiresQuote,
            'rooms_expanded'        => $this->roomsExpanded,
            'messages'              => $this->messages,
            'unavailable_date'      => $this->unavailableDate,
        ];
    }

    /**
     * Phiên bản an toàn cho client công khai: bỏ các trường nội bộ (giá vốn/biên lợi nhuận).
     * Dùng cho endpoint /quote public. Bản đầy đủ toArray() chỉ dùng nội bộ (pricing_snapshot).
     */
    public function toPublicArray(): array
    {
        $data = $this->toArray();
        unset($data['cost_total']);
        return $data;
    }
}
