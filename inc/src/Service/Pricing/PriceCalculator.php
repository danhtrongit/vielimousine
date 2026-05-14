<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\PriceBreakdown;
use Vie\DTO\QuoteRequest;
use Vie\Repository\HotelRepository;
use Vie\Repository\RoomPriceRepository;
use Vie\Repository\RoomRepository;
use Vie\Repository\SurchargePriceRepository;
use Vie\Repository\SurchargeRepository;
use Vie\Repository\TicketPriceRepository;
use Vie\Support\Money;

final class PriceCalculator
{
    public function __construct(
        private readonly RoomRepository $roomRepo,
        private readonly HotelRepository $hotelRepo,
        private readonly RoomPriceRepository $roomPriceRepo,
        private readonly SurchargeRepository $surchargeRepo,
        private readonly SurchargePriceRepository $surchargePriceRepo,
        private readonly TicketPriceRepository $ticketPriceRepo,
    ) {
    }

    public function quote(QuoteRequest $req): PriceBreakdown
    {
        $room  = $this->roomRepo->findOrFail($req->roomId);
        $hotel = $this->hotelRepo->findOrFail((int) $room['hotel_id']);

        $nights        = $req->nightDates();
        $nightsCount   = count($nights);
        $roomAdultAge  = (int) ($room['free_children_max_age'] ?? 5) + 1;

        $guest        = new GuestComposition($req->adults, $req->childAges, $roomAdultAge);
        $allocation   = new RoomAllocation($room, $guest, $req->userRooms);
        $childPolicy  = new ChildPolicy(
            $guest->childrenUnderFloor(),
            $guest->convertedAdultAges(),
            (int) $room['free_children_count'] * $allocation->numRooms(),
        );

        $isCombo = $req->bookingType === 'combo';
        $ticket  = new TicketCalculator(
            $hotel,
            $req->adults,
            $req->childAges,
            $req->checkin,
            $req->routeId,
            $this->ticketPriceRepo,
        );

        $messages = $allocation->messages();
        if ($childPolicy->freeChildrenCount() > 0) {
            $messages[] = "Miễn phí {$childPolicy->freeChildrenCount()} bé dưới {$roomAdultAge} tuổi (theo chính sách phòng)";
        }
        if (count($guest->convertedAdultAges()) > 0) {
            $messages[] = 'Bé ≥ ' . $roomAdultAge . ' tuổi được tính như người lớn';
        }

        if ($allocation->requiresQuote()) {
            return $this->emptyBreakdown(
                $allocation->numRooms(),
                $nightsCount,
                $guest,
                $allocation,
                $ticket,
                $isCombo,
                $childPolicy->assessments(),
                $messages,
                null,
            );
        }

        $priceMap = $this->loadRoomPrices($req->roomId, $nights);

        $roomSubtotal       = 0;
        $extraAdultSubtotal = 0;
        $nightly            = [];
        $unavailableDate    = null;

        $surcharge = new SurchargeCalculator(
            $req->roomId,
            $nights,
            $childPolicy->assessments(),
            $this->surchargeRepo,
            $this->surchargePriceRepo,
        );
        $nightlySurcharges = $surcharge->nightlyBreakdown();

        foreach ($nights as $date) {
            $row = $priceMap[$date] ?? null;
            if ($row === null || (int) ($row['is_active'] ?? 0) !== 1) {
                $unavailableDate = $date;
                $messages[]      = "Hết phòng đêm {$date}";
                break;
            }

            $price            = (int) $row['price'];
            $extraAdultPrice  = (int) ($row['extra_adult_price'] !== null && $row['extra_adult_price'] !== ''
                ? $row['extra_adult_price']
                : ($room['extra_adult_price'] ?? 0));

            $roomSubtotal       += $price * $allocation->numRooms();
            $extraAdultSubtotal += $extraAdultPrice * $allocation->extraAdultBeds();

            $nightly[] = [
                'date'              => $date,
                'price'             => $price,
                'extra_adult_price' => $extraAdultPrice,
                'ticket_price'      => $isCombo ? $ticket->ticketPrice() : 0,
                'child_surcharges'  => $nightlySurcharges[$date] ?? [],
            ];
        }

        if ($unavailableDate !== null) {
            return $this->emptyBreakdown(
                $allocation->numRooms(),
                $nightsCount,
                $guest,
                $allocation,
                $ticket,
                $isCombo,
                $childPolicy->assessments(),
                $messages,
                $unavailableDate,
            );
        }

        $childSurchargeTotal = $surcharge->total();
        $ticketSubtotal      = $isCombo ? $ticket->ticketSubtotal() : 0;

        $subtotal = $roomSubtotal + $extraAdultSubtotal + $childSurchargeTotal + $ticketSubtotal;
        // Phase 3 stub: coupon discount luôn = 0. Phase 4 sẽ gọi CouponService::calc().
        $discount = 0;
        $total    = Money::roundVND(max(0, $subtotal - $discount));

        return new PriceBreakdown(
            numRooms:            $allocation->numRooms(),
            nights:              $nightsCount,
            effectiveAdults:     $guest->effectiveAdults(),
            effectiveChildren:   $guest->effectiveChildren(),
            extraAdultBeds:      $allocation->extraAdultBeds(),
            seatCount:           $isCombo ? $ticket->seatCount() : 0,
            billableSeats:       $isCombo ? $ticket->billableSeats() : 0,
            freeChildSeats:      $isCombo ? $ticket->freeSeats() : 0,
            nightly:             $nightly,
            childAssessments:    $childPolicy->assessments(),
            roomSubtotal:        $roomSubtotal,
            extraAdultSubtotal:  $extraAdultSubtotal,
            childSurchargeTotal: $childSurchargeTotal,
            ticketSubtotal:      $ticketSubtotal,
            subtotal:            $subtotal,
            discount:            $discount,
            total:               $total,
            costTotal:           0,
            requiresQuote:       false,
            messages:            $messages,
            unavailableDate:     null,
        );
    }

    private function loadRoomPrices(int $roomId, array $nights): array
    {
        if ($nights === []) {
            return [];
        }
        $sorted = $nights;
        sort($sorted);

        $result = $this->roomPriceRepo->all([
            'room_id'   => $roomId,
            'date_from' => $sorted[0],
            'date_to'   => $sorted[count($sorted) - 1],
            'per_page'  => 100,
        ]);

        $map = [];
        foreach ($result['data'] ?? [] as $row) {
            $map[$row['date']] = $row;
        }
        return $map;
    }

    private function emptyBreakdown(
        int $numRooms,
        int $nightsCount,
        GuestComposition $guest,
        RoomAllocation $allocation,
        TicketCalculator $ticket,
        bool $isCombo,
        array $assessments,
        array $messages,
        ?string $unavailableDate,
    ): PriceBreakdown {
        return new PriceBreakdown(
            numRooms:            $numRooms,
            nights:              $nightsCount,
            effectiveAdults:     $guest->effectiveAdults(),
            effectiveChildren:   $guest->effectiveChildren(),
            extraAdultBeds:      $allocation->extraAdultBeds(),
            seatCount:           $isCombo ? $ticket->seatCount() : 0,
            billableSeats:       $isCombo ? $ticket->billableSeats() : 0,
            freeChildSeats:      $isCombo ? $ticket->freeSeats() : 0,
            nightly:             [],
            childAssessments:    $assessments,
            roomSubtotal:        0,
            extraAdultSubtotal:  0,
            childSurchargeTotal: 0,
            ticketSubtotal:      0,
            subtotal:            0,
            discount:            0,
            total:               0,
            costTotal:           0,
            requiresQuote:       true,
            messages:            $messages,
            unavailableDate:     $unavailableDate,
        );
    }
}
