<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\Repository\TicketPriceRepository;

final class TicketCalculator
{
    private int $seatCount     = 0;
    private int $billableSeats = 0;
    private int $freeSeats     = 0;
    private int $ticketPrice   = 0;

    public function __construct(
        array $hotel,
        int $adults,
        array $childAges,
        string $checkinDate,
        int $routeId,
        TicketPriceRepository $ticketRepo,
    ) {
        $ticketAgeFloor = (int) ($hotel['ticket_free_children_max_age'] ?? 5) + 1;
        $freeQuota      = (int) ($hotel['ticket_free_children_count'] ?? 1);

        $under = 0;
        $over  = 0;
        foreach ($childAges as $age) {
            if ((int) $age < $ticketAgeFloor) {
                $under++;
            } else {
                $over++;
            }
        }

        $this->freeSeats     = min($under, $freeQuota);
        $this->seatCount     = $adults + $over + $under;
        $this->billableSeats = $adults + $over + max(0, $under - $this->freeSeats);
        $this->ticketPrice   = $this->lookupPrice($hotel, $checkinDate, $routeId, $ticketRepo);
    }

    public function applies(string $bookingType): bool
    {
        return $bookingType === 'combo';
    }

    public function seatCount(): int
    {
        return $this->seatCount;
    }

    public function billableSeats(): int
    {
        return $this->billableSeats;
    }

    public function freeSeats(): int
    {
        return $this->freeSeats;
    }

    public function ticketPrice(): int
    {
        return $this->ticketPrice;
    }

    public function ticketSubtotal(): int
    {
        return $this->billableSeats * $this->ticketPrice;
    }

    private function lookupPrice(array $hotel, string $date, int $routeId, TicketPriceRepository $repo): int
    {
        $result = $repo->all([
            'hotel_id'   => $hotel['id'],
            'date_from'  => $date,
            'date_to'    => $date,
            'is_active'  => 1,
            'per_page'   => 100,
        ]);

        foreach ($result['data'] as $row) {
            if ((int) $row['route_id'] === $routeId && $row['date'] === $date) {
                return (int) $row['ticket_price'];
            }
        }

        return (int) ($hotel['default_ticket_price'] ?? 0);
    }
}
