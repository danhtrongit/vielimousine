<?php
/**
 * Single Hotel template (child theme override)
 *
 * Phase 10 — render booking shortcodes + pricing/cancellation policy
 * cho từng khách sạn từ bảng vie_hotel.
 */

defined('ABSPATH') || die;

get_header();
?>
<main class="vie-public-hotel">
<?php
while (have_posts()) :
    the_post();
    $postId = (int) get_the_ID();

    global $wpdb;
    $hotel = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, pricing_policy, cancellation_policy FROM {$wpdb->prefix}vie_hotel WHERE post_id = %d LIMIT 1",
            $postId
        ),
        ARRAY_A
    );
    ?>
    <article>
        <h1><?php the_title(); ?></h1>
        <?php if (has_post_thumbnail()) : ?>
            <div style="margin-bottom: 1rem;"><?php the_post_thumbnail('large'); ?></div>
        <?php endif; ?>

        <div class="vie-public-hotel__content"><?php the_content(); ?></div>

        <?php if ($hotel) :
            $hotelId = (int) $hotel['id'];
            $pricing = json_decode((string) ($hotel['pricing_policy'] ?? ''), true);
            $cancel  = json_decode((string) ($hotel['cancellation_policy'] ?? ''), true);
            $pricing = is_array($pricing) ? $pricing : [];
            $cancel  = is_array($cancel) ? $cancel : [];
            ?>
            <section class="vie-public-hotel__booking">
                <h2>Đặt phòng tại khách sạn này</h2>
                <?php echo do_shortcode('[vie_hotel_search hotel_id="' . $hotelId . '"]'); ?>
                <?php echo do_shortcode('[vie_hotel_rooms hotel_id="' . $hotelId . '"]'); ?>
            </section>

            <?php if (!empty($pricing) || !empty($cancel['rules'])) : ?>
                <section class="vie-public-hotel__policy">
                    <h2>Chính sách</h2>
                    <?php if (!empty($pricing['child_note'])) : ?>
                        <h3>Trẻ em</h3>
                        <p><?php echo esc_html((string) $pricing['child_note']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($pricing['extra_bed_note'])) : ?>
                        <h3>Giường phụ</h3>
                        <p><?php echo esc_html((string) $pricing['extra_bed_note']); ?></p>
                    <?php endif; ?>
                    <?php if (is_array($cancel['rules'] ?? null)) : ?>
                        <h3>Chính sách hủy phòng</h3>
                        <table>
                            <thead>
                                <tr><th>Thời điểm</th><th>Mức phạt</th><th>Mô tả</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cancel['rules'] as $rule) :
                                    if (!is_array($rule)) continue; ?>
                                    <tr>
                                        <td>Trước <?php echo (int) ($rule['hours_before_checkin'] ?? 0); ?> giờ</td>
                                        <td><?php echo (int) ($rule['penalty_percent'] ?? 0); ?>%</td>
                                        <td><?php echo esc_html((string) ($rule['description'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php else : ?>
            <p class="vie-public__muted">Khách sạn này chưa được cấu hình trong hệ thống đặt phòng.</p>
        <?php endif; ?>
    </article>
<?php
endwhile;
?>
</main>
<?php
get_footer();
