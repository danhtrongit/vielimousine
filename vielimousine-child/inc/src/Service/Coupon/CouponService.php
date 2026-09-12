<?php
declare(strict_types=1);

namespace Vie\Service\Coupon;

use Vie\Repository\CouponRepository;
use Vie\Repository\CouponUsageRepository;

final class CouponService
{
    public function __construct(
        private readonly CouponRepository $couponRepo,
        private readonly CouponUsageRepository $usageRepo,
    ) {
    }

    /**
     * Validate coupon for a preview/checkout context. Never increments usage.
     *
     * @return array{valid: bool, coupon: ?array, discount: int, messages: string[]}
     */
    public function validate(
        string $code,
        int $subtotal,
        ?int $hotelId,
        ?int $roomId,
        ?string $bookingType,
        ?string $userEmail,
    ): array {
        $messages = [];
        $coupon = $this->couponRepo->findByCode($code);

        if ($coupon === null) {
            return [
                'valid'    => false,
                'coupon'   => null,
                'discount' => 0,
                'messages' => ['Mã giảm giá không tồn tại'],
            ];
        }

        if (!($coupon['is_active'] ?? false)) {
            $messages[] = 'Mã giảm giá đã bị vô hiệu hóa';
        }

        $now = current_time('mysql');
        if (!empty($coupon['valid_from']) && $coupon['valid_from'] > $now) {
            $messages[] = 'Mã giảm giá chưa đến thời gian áp dụng';
        }
        if (!empty($coupon['valid_to']) && $coupon['valid_to'] < $now) {
            $messages[] = 'Mã giảm giá đã hết hạn';
        }

        $minOrder = (int) ($coupon['min_order'] ?? 0);
        if ($minOrder > 0 && $subtotal < $minOrder) {
            $messages[] = sprintf('Đơn tối thiểu %sđ để áp mã', number_format($minOrder, 0, ',', '.'));
        }

        $usageLimit = $coupon['usage_limit'] ?? null;
        if ($usageLimit !== null && (int) $usageLimit > 0 && (int) ($coupon['used_count'] ?? 0) >= (int) $usageLimit) {
            $messages[] = 'Mã giảm giá đã hết lượt sử dụng';
        }

        $perUser = $coupon['usage_limit_per_user'] ?? null;
        if ($perUser !== null && (int) $perUser > 0 && $userEmail !== null && $userEmail !== '') {
            $used = $this->countUsageForEmail((int) $coupon['id'], $userEmail);
            if ($used >= (int) $perUser) {
                $messages[] = 'Bạn đã sử dụng hết lượt cho mã này';
            }
        }

        $hotelIds = $coupon['hotel_ids'] ?? null;
        if (is_array($hotelIds) && $hotelIds !== [] && $hotelId !== null && !in_array($hotelId, array_map('intval', $hotelIds), true)) {
            $messages[] = 'Mã giảm giá không áp dụng cho khách sạn này';
        }

        $roomIds = $coupon['room_ids'] ?? null;
        if (is_array($roomIds) && $roomIds !== [] && $roomId !== null && !in_array($roomId, array_map('intval', $roomIds), true)) {
            $messages[] = 'Mã giảm giá không áp dụng cho phòng này';
        }

        $bookingTypes = $coupon['booking_types'] ?? null;
        if (is_array($bookingTypes) && $bookingTypes !== [] && $bookingType !== null && !in_array($bookingType, $bookingTypes, true)) {
            $messages[] = 'Mã giảm giá không áp dụng cho loại đặt này';
        }

        if ($messages !== []) {
            return [
                'valid'    => false,
                'coupon'   => $coupon,
                'discount' => 0,
                'messages' => $messages,
            ];
        }

        $discount = $this->calc($coupon, $subtotal);

        return [
            'valid'    => true,
            'coupon'   => $coupon,
            'discount' => $discount,
            'messages' => ['Áp dụng thành công'],
        ];
    }

    public function calc(array $coupon, int $subtotal): int
    {
        $type  = (string) ($coupon['type'] ?? 'fixed');
        $value = (float) ($coupon['value'] ?? 0);

        if ($type === 'percent' || $type === 'percentage') {
            $discount = (int) floor($subtotal * $value / 100);
            $cap = $coupon['max_discount'] ?? null;
            if ($cap !== null && (int) $cap > 0) {
                $discount = min($discount, (int) $cap);
            }
            return max(0, min($discount, $subtotal));
        }

        return max(0, (int) min($value, $subtotal));
    }

    /**
     * Ghi nhận coupon được dùng cho đơn. Atomic increment kiểm tra `used_count <
     * usage_limit` tại DB level — chống race khi 2 đơn song song giành slot cuối.
     *
     * Order trong transaction: increment TRƯỚC (compare-and-swap), nếu fail throw
     * CouponException → outer TX rollback toàn bộ order. Insert usage record SAU.
     *
     * @throws CouponException khi đã đạt usage_limit.
     */
    public function recordUsage(int $couponId, int $orderId, ?string $email, int $discount): void
    {
        $claimed = $this->couponRepo->incrementUsedAtomic($couponId);
        if (!$claimed) {
            throw new CouponException(['Mã giảm giá đã hết lượt sử dụng.']);
        }
        $this->usageRepo->create([
            'coupon_id'  => $couponId,
            'order_id'   => $orderId,
            'user_email' => $email,
            'discount'   => $discount,
        ]);
    }

    private function countUsageForEmail(int $couponId, string $email): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_coupon_usage';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE coupon_id = %d AND user_email = %s",
            $couponId,
            $email
        ));
    }
}
