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
