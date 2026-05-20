<?php
/**
 * Shortcode [vie_hotel_rooms]
 *
 * Render server-side từ bảng vie_room — không gọi REST API (rooms list yêu cầu auth).
 *
 * @var array $atts  Shortcode attributes (hotel_id).
 */

$hotelId = (int) ($atts['hotel_id'] ?? 0);

global $wpdb;
$rooms = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, name, description, base_price FROM {$wpdb->prefix}vie_room
         WHERE hotel_id = %d AND is_active = 1
         ORDER BY base_price ASC",
        $hotelId
    ),
    ARRAY_A
);

$checkoutUrl = home_url('/dat-phong/');
?>
<div class="vie-public" data-vie-rooms data-hotel-id="<?php echo (int) $hotelId; ?>">
    <h2>Danh sách phòng</h2>
    <?php if (empty($rooms)) : ?>
        <p class="vie-public__muted">Khách sạn chưa có phòng nào đang mở bán.</p>
    <?php else : ?>
        <div class="vie-public__rooms-list" data-rooms-list>
            <?php foreach ($rooms as $room) :
                $price = (int) $room['base_price'] > 0
                    ? number_format((int) $room['base_price'], 0, ',', '.') . 'đ / đêm'
                    : 'Liên hệ';
                $href  = $checkoutUrl . '?' . http_build_query(['room_id' => (int) $room['id']]);
                ?>
                <article class="vie-public__room-card">
                    <h3><?php echo esc_html((string) $room['name']); ?></h3>
                    <?php if (!empty($room['description'])) : ?>
                        <p class="vie-public__muted"><?php echo esc_html(wp_trim_words((string) $room['description'], 30)); ?></p>
                    <?php endif; ?>
                    <p class="vie-public__room-price"><?php echo esc_html($price); ?></p>
                    <a class="vie-public__btn vie-public__btn--primary" href="<?php echo esc_url($href); ?>">Đặt phòng này</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
