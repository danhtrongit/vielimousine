<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class OrderRequest
{
    public function __construct(
        public array   $customer,
        public string  $source,
        public ?int    $salesUserId,
        public ?string $customerNote,
        public ?array  $pickup,
        public ?array  $dropoff,
        public array   $items,
        public ?string $couponCode,
        public ?string $voucherCode,
        public ?string $paymentMethod,
        public ?string $idempotencyKey,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }

    public static function fromArray(array $data, ?string $idemKey, ?string $ip, ?string $ua): self
    {
        return new self(
            customer:       is_array($data['customer'] ?? null) ? $data['customer'] : [],
            source:         (string) ($data['source'] ?? 'website'),
            salesUserId:    isset($data['sales_user_id']) ? (int) $data['sales_user_id'] : null,
            customerNote:   isset($data['customer_note']) && $data['customer_note'] !== '' ? (string) $data['customer_note'] : null,
            pickup:         is_array($data['pickup'] ?? null) ? $data['pickup'] : null,
            dropoff:        is_array($data['dropoff'] ?? null) ? $data['dropoff'] : null,
            items:          is_array($data['items'] ?? null) ? $data['items'] : [],
            couponCode:     isset($data['coupon_code']) && $data['coupon_code'] !== '' ? (string) $data['coupon_code'] : null,
            voucherCode:    isset($data['voucher_code']) && $data['voucher_code'] !== '' ? (string) $data['voucher_code'] : null,
            paymentMethod:  isset($data['payment_method']) && $data['payment_method'] !== '' ? (string) $data['payment_method'] : null,
            idempotencyKey: $idemKey !== null && $idemKey !== '' ? $idemKey : null,
            ip:             $ip !== null && $ip !== '' ? $ip : null,
            userAgent:      $ua !== null && $ua !== '' ? $ua : null,
        );
    }
}
