<?php
declare(strict_types=1);

namespace Vie\Service\Hotel;

use Vie\Repository\ActivityLogRepository;
use Vie\Repository\HotelRepository;

/**
 * Cascade delete cho hotel: clean child tables theo thứ tự FK conceptually
 * (`vie_room_price` → `vie_surcharge_price` → `vie_ticket_price` → `vie_surcharge`
 * → `vie_room` → `vie_hotel`).
 *
 * - Chặn xóa nếu còn order_item active (đơn `pending/confirmed`) tham chiếu
 *   hotel — để bảo toàn audit trail và tránh báo cáo broken.
 * - Tất cả trong 1 transaction; rollback nếu bất kỳ DELETE nào fail.
 */
final class HotelDeleteService
{
    public function __construct(
        private readonly HotelRepository $hotelRepo,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    /**
     * @throws HotelInUseException khi còn order active.
     * @throws \RuntimeException khi SQL fail.
     */
    public function delete(int $hotelId, int $actorUserId = 0): void
    {
        global $wpdb;

        $hotel = $this->hotelRepo->find($hotelId);
        if ($hotel === null) {
            return;
        }

        // Guard: không cho xóa khi còn đơn còn hiệu lực gắn vào hotel này.
        $active = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vie_order_item oi
             INNER JOIN {$wpdb->prefix}vie_order o ON o.id = oi.order_id
             WHERE oi.hotel_id = %d
               AND o.status NOT IN ('cancelled', 'no_show', 'draft')
               AND oi.status = 'active'",
            $hotelId
        ));
        if ($active > 0) {
            throw new HotelInUseException(sprintf(
                'Không thể xóa khách sạn — còn %d đơn đang hoạt động tham chiếu.',
                $active
            ));
        }

        $wpdb->query('START TRANSACTION');
        try {
            // 1. room_price (qua room_id IN ...)
            $this->execOrFail($wpdb->prepare(
                "DELETE rp FROM {$wpdb->prefix}vie_room_price rp
                 INNER JOIN {$wpdb->prefix}vie_room r ON r.id = rp.room_id
                 WHERE r.hotel_id = %d",
                $hotelId
            ));
            // 2. surcharge_price (qua surcharge.room_id → room.hotel_id)
            $this->execOrFail($wpdb->prepare(
                "DELETE sp FROM {$wpdb->prefix}vie_surcharge_price sp
                 INNER JOIN {$wpdb->prefix}vie_surcharge s ON s.id = sp.surcharge_id
                 INNER JOIN {$wpdb->prefix}vie_room r      ON r.id = s.room_id
                 WHERE r.hotel_id = %d",
                $hotelId
            ));
            // 3. ticket_price (cột hotel_id trực tiếp)
            $this->execOrFail($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}vie_ticket_price WHERE hotel_id = %d",
                $hotelId
            ));
            // 4. surcharge (qua room.hotel_id)
            $this->execOrFail($wpdb->prepare(
                "DELETE s FROM {$wpdb->prefix}vie_surcharge s
                 INNER JOIN {$wpdb->prefix}vie_room r ON r.id = s.room_id
                 WHERE r.hotel_id = %d",
                $hotelId
            ));
            // 5. room
            $this->execOrFail($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}vie_room WHERE hotel_id = %d",
                $hotelId
            ));
            // 6. hotel
            $this->execOrFail($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}vie_hotel WHERE id = %d",
                $hotelId
            ));

            $this->activityRepo->create([
                'actor_user_id' => $actorUserId,
                'entity_type'   => 'hotel',
                'entity_id'     => $hotelId,
                'action'        => 'cascade_delete',
                'before_json'   => $hotel,
                'after_json'    => null,
            ]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    private function execOrFail(string $sql): void
    {
        global $wpdb;
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new \RuntimeException('Hotel cascade DELETE failed: ' . $wpdb->last_error);
        }
    }
}
