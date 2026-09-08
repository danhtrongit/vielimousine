<?php
declare(strict_types=1);

namespace Vie\Frontend;

final class ShortcodeRegistry
{
    public static function register(): void
    {
        add_shortcode('vie_hotel_search',  [self::class, 'hotelSearch']);
        add_shortcode('vie_hotel_rooms',   [self::class, 'hotelRooms']);
        add_shortcode('vie_checkout',      [self::class, 'checkout']);
        add_shortcode('vie_order_success', [self::class, 'success']);
        add_filter('the_title', [self::class, 'neutralSuccessTitle'], 10, 2);
    }

    /**
     * Trang chứa [vie_order_success] được đặt tên "Đặt phòng thành công" trong WP, nhưng
     * server render chưa biết đơn đã trả tiền hay chưa → khách thanh toán lỗi vẫn thấy
     * "THÀNH CÔNG". Dùng tiêu đề trung tính; trạng thái thật do SuccessApp hiển thị.
     */
    public static function neutralSuccessTitle(mixed $title, mixed $postId = 0): mixed
    {
        // Plugin bên thứ ba có thể gọi filter thiếu id / id dạng chuỗi → không được TypeError.
        $postId = (int) $postId;
        if (is_admin() || $postId === 0 || !is_page($postId)) {
            return $title;
        }
        if (!has_shortcode((string) get_post_field('post_content', $postId), 'vie_order_success')) {
            return $title;
        }
        return 'Thông tin đơn đặt phòng';
    }

    public static function hotelSearch($atts): string
    {
        $atts = shortcode_atts(['hotel_id' => 0], (array) $atts, 'vie_hotel_search');
        return self::render('search-form', $atts);
    }

    public static function hotelRooms($atts): string
    {
        $atts = shortcode_atts(['hotel_id' => 0], (array) $atts, 'vie_hotel_rooms');
        return self::render('room-card', $atts);
    }

    public static function checkout($atts): string
    {
        $atts = shortcode_atts([], (array) $atts, 'vie_checkout');
        return self::render('checkout', $atts);
    }

    public static function success($atts): string
    {
        $atts = shortcode_atts([], (array) $atts, 'vie_order_success');
        return self::render('success', $atts);
    }

    private static function render(string $template, array $atts): string
    {
        $file = VIE_CHILD_PATH . "/inc/templates/frontend/{$template}.php";
        if (!is_file($file)) {
            return '';
        }
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
