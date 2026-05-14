<?php
declare(strict_types=1);

namespace Vie\Email;

use Vie\Service\HookRegistry;

final class OrderEmailListener
{
    public static function register(): void
    {
        add_action(HookRegistry::ORDER_CREATED, [self::class, 'onOrderCreated'], 10, 2);
    }

    public static function onOrderCreated(int $orderId, ?array $order): void
    {
        if (!is_array($order)) {
            return;
        }
        if (($order['source'] ?? '') !== 'website') {
            return;
        }

        self::sendCustomerEmail($order);
        self::sendAdminEmail($order);
    }

    private static function sendCustomerEmail(array $order): void
    {
        $email = $order['customer_email'] ?? null;
        if (!$email) {
            return;
        }

        $code  = (string) ($order['code'] ?? '');
        $name  = (string) ($order['customer_name'] ?? 'Quý khách');
        $phone = (string) ($order['customer_phone'] ?? '');
        $total = number_format((int) ($order['total'] ?? 0), 0, ',', '.');

        $lookupUrl = home_url('/dat-phong-thanh-cong/') . '?' . http_build_query([
            'code'  => $code,
            'phone' => $phone,
        ]);

        $subject = "[Vielimousine] Đặt phòng #{$code} - Chờ thanh toán";
        $message = "Xin chào {$name},\n\n"
                 . "Cảm ơn bạn đã đặt phòng tại Vielimousine.\n\n"
                 . "Thông tin đơn:\n"
                 . "- Mã đơn: {$code}\n"
                 . "- Tổng: {$total} VND\n"
                 . "- Trạng thái: Chờ thanh toán\n\n"
                 . "Xem chi tiết đơn: {$lookupUrl}\n\n"
                 . "Vielimousine team";

        wp_mail($email, $subject, $message);
    }

    private static function sendAdminEmail(array $order): void
    {
        $adminEmail = get_option('admin_email');
        if (!$adminEmail) {
            return;
        }

        $code     = (string) ($order['code'] ?? '');
        $name     = (string) ($order['customer_name'] ?? '');
        $phone    = (string) ($order['customer_phone'] ?? '');
        $checkin  = (string) ($order['checkin'] ?? '');
        $checkout = (string) ($order['checkout'] ?? '');
        $total    = number_format((int) ($order['total'] ?? 0), 0, ',', '.');
        $detailUrl = home_url('/vie-admin/orders/' . (int) ($order['id'] ?? 0));

        $subject = "[ĐẶT PHÒNG MỚI] #{$code} - {$name}";
        $message = "Đơn hàng mới từ website:\n\n"
                 . "- Mã đơn: {$code}\n"
                 . "- Khách: {$name} ({$phone})\n"
                 . "- Tổng: {$total} VND\n"
                 . "- Check-in: {$checkin}\n"
                 . "- Check-out: {$checkout}\n\n"
                 . "Xem trong admin: {$detailUrl}";

        wp_mail($adminEmail, $subject, $message);
    }
}
