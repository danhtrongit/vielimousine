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
            'text' => "- Giá đã bao gồm ăn sáng và thuế VAT.\n- Trẻ dưới 6 tuổi miễn phí 01 bé (phòng và vé), bé thứ 2 tính phụ thu.\n- Phụ thu giường phụ: 300.000 VND/đêm.\n- Giá không áp dụng ngày lễ, Tết.",
        ], JSON_UNESCAPED_UNICODE);

        $cancellationPolicy = wp_json_encode([
            'text' => "- Hủy trước ≥ 48 tiếng so với giờ khởi hành: phí hủy 0%.\n- Hủy trong vòng 48–24 tiếng trước giờ khởi hành: phí hủy 50%.\n- Hủy dưới 24 tiếng hoặc no-show: phí hủy 100%.\n- Hoàn tiền qua chuyển khoản trong 5–7 ngày làm việc.",
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
