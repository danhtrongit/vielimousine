<?php
declare(strict_types=1);

namespace Vie\Schema\Seeders;

final class HotelSeeder
{
    public static function run(\wpdb $wpdb): void
    {
        $table = $wpdb->prefix . 'vie_hotel';

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s LIMIT 1", 'the-cap-vung-tau')
        );

        if ($exists) {
            echo "Hotel already seeded, skipping.\n";
            return;
        }

        $postId = wp_insert_post([
            'post_title'  => 'The Cap Hotel & Spa Vung Tau',
            'post_name'   => 'the-cap-vung-tau',
            'post_type'   => 'hotel',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($postId)) {
            echo "Failed to create hotel post: " . $postId->get_error_message() . "\n";
            return;
        }

        $pricingPolicy = wp_json_encode([
            'child_note'    => 'Trẻ dưới 6 tuổi miễn phí 01 bé (phòng và vé). Bé thứ 2 tính phí phụ thu.',
            'extra_bed_note' => 'Phụ thu giường phụ: 300.000 VND/đêm.',
            'ticket_note'   => 'Giá vé đã bao gồm xe khứ hồi Sài Gòn – Vũng Tàu.',
            'general_notes' => [
                'Giá không áp dụng ngày lễ, Tết.',
                'Giá có thể thay đổi theo mùa.',
            ],
        ], JSON_UNESCAPED_UNICODE);

        $cancellationPolicy = wp_json_encode([
            'rules' => [
                ['hours_before_checkin' => 72, 'penalty_percent' => 0,   'description' => 'Hủy trước 72h: miễn phí'],
                ['hours_before_checkin' => 24, 'penalty_percent' => 50,  'description' => 'Hủy trước 24–72h: phạt 50%'],
                ['hours_before_checkin' => 0,  'penalty_percent' => 100, 'description' => 'Hủy trong 24h hoặc no-show: mất 100%'],
            ],
            'refund_method' => 'Hoàn tiền qua chuyển khoản trong 5–7 ngày làm việc.',
            'notes'         => 'Không áp dụng hoàn tiền cho đặt phòng khuyến mãi.',
        ], JSON_UNESCAPED_UNICODE);

        $wpdb->insert($table, [
            'post_id'                    => $postId,
            'name'                       => 'The Cap Hotel & Spa Vung Tau',
            'slug'                       => 'the-cap-vung-tau',
            'description'                => 'Khách sạn 4 sao view biển tại Vũng Tàu.',
            'address'                    => '2 Trần Phú, Phường 1, Vũng Tàu',
            'city'                       => 'VT',
            'contact_phone'              => '0254 3511 888',
            'contact_email'              => 'booking@thecapvungtau.com',
            'star_rating'                => 4,
            'default_checkin'            => '14:00:00',
            'default_checkout'           => '12:00:00',
            'default_ticket_price'       => 350000,
            'ticket_free_children_count' => 1,
            'ticket_free_children_max_age' => 5,
            'pricing_policy'             => $pricingPolicy,
            'cancellation_policy'        => $cancellationPolicy,
            'is_active'                  => 1,
            'sort_order'                 => 1,
        ], [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%d', '%s', '%s', '%d', '%d', '%d',
            '%s', '%s', '%d', '%d',
        ]);

        echo "Hotel seeded: ID={$wpdb->insert_id}, post_id={$postId}\n";
    }
}
