<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class QuoteRequest
{
    public function __construct(
        public int     $roomId,
        public string  $checkin,
        public string  $checkout,
        public int     $adults,
        public array   $childAges,
        public int     $userRooms,
        public string  $bookingType,
        public ?string $couponCode = null,
        public int     $routeId = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $childAges = $data['child_ages'] ?? [];
        $childAges = array_map(static fn($age) => (int) $age, $childAges);

        return new self(
            roomId:      (int) $data['room_id'],
            checkin:     (string) $data['checkin'],
            checkout:    (string) $data['checkout'],
            adults:      (int) $data['adults'],
            childAges:   $childAges,
            userRooms:   (int) ($data['user_rooms'] ?? 0),
            bookingType: (string) $data['booking_type'],
            couponCode:  isset($data['coupon_code']) && $data['coupon_code'] !== '' ? (string) $data['coupon_code'] : null,
            routeId:     (int) ($data['route_id'] ?? 0),
        );
    }

    public function nights(): int
    {
        $in  = new \DateTimeImmutable($this->checkin);
        $out = new \DateTimeImmutable($this->checkout);
        return (int) $out->diff($in)->days;
    }

    public function nightDates(): array
    {
        $dates = [];
        $cursor = new \DateTimeImmutable($this->checkin);
        $end    = new \DateTimeImmutable($this->checkout);
        while ($cursor < $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor  = $cursor->modify('+1 day');
        }
        return $dates;
    }
}
