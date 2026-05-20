<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\Service\Settings\SepaySettings;

final class SepaySignature
{
    private const SIGN_FIELDS = [
        'merchant', 'operation', 'payment_method', 'order_amount',
        'currency', 'order_invoice_number', 'order_description',
        'customer_id', 'success_url', 'error_url', 'cancel_url',
    ];

    public function __construct(private readonly SepaySettings $settings)
    {
    }

    public function sign(array $params): string
    {
        $payload = implode('|', array_map(
            static fn($f) => (string) ($params[$f] ?? ''),
            self::SIGN_FIELDS
        ));
        return hash_hmac('sha256', $payload, $this->settings->secretKey());
    }

    public function verify(array $params, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }
        return hash_equals($this->sign($params), $signature);
    }

    /**
     * Webhook signing scope đã được mở rộng thêm `paid_at` và `timestamp` để:
     *
     *   1. Chống tampering `paid_at` (trước đây attacker có signature hợp lệ
     *      vẫn có thể đổi ngày thanh toán → lệch báo cáo).
     *   2. Hỗ trợ replay-protection: webhook hợp lệ cũ không pass khi
     *      timestamp đã ra ngoài cửa sổ 5 phút (kiểm tra ở SepayWebhook).
     *
     * Nếu SePay chưa gửi `timestamp` ở payload, trường trống "" được ký → vẫn
     * tương thích ngược, chỉ là replay-window không enforce.
     */
    public function signWebhook(array $payload): string
    {
        $payloadStr = implode('|', [
            (string) ($payload['order_invoice_number'] ?? ''),
            (string) ($payload['transaction_id']       ?? ''),
            (string) ($payload['amount']               ?? ''),
            (string) ($payload['status']               ?? ''),
            (string) ($payload['paid_at']              ?? ''),
            (string) ($payload['timestamp']            ?? ''),
        ]);
        return hash_hmac('sha256', $payloadStr, $this->settings->secretKey());
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }
        return hash_equals($this->signWebhook($payload), $signature);
    }
}
