<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\Service\Settings\SepaySettings;

final class SepaySignature
{
    /**
     * Thứ tự field để ký — đúng theo tài liệu Cổng thanh toán SePay
     * (form-thanh-toan). Chỉ field nào CÓ MẶT mới được đưa vào chuỗi ký.
     */
    private const SIGN_FIELDS = [
        'order_amount', 'merchant', 'currency', 'operation',
        'order_description', 'order_invoice_number', 'customer_id',
        'payment_method', 'success_url', 'error_url', 'cancel_url',
    ];

    public function __construct(private readonly SepaySettings $settings)
    {
    }

    /**
     * Ký form checkout: lọc field có mặt theo đúng thứ tự, ghép "field=value"
     * ngăn bằng dấu phẩy, HMAC-SHA256 (raw binary) rồi base64_encode.
     * Khớp 100% cách SePay verify ở phía họ.
     */
    public function signCheckout(array $fields): string
    {
        $parts = [];
        foreach (self::SIGN_FIELDS as $f) {
            if (array_key_exists($f, $fields) && $fields[$f] !== '' && $fields[$f] !== null) {
                $parts[] = $f . '=' . $fields[$f];
            }
        }
        return base64_encode(
            hash_hmac('sha256', implode(',', $parts), $this->settings->secretKey(), true)
        );
    }
}
