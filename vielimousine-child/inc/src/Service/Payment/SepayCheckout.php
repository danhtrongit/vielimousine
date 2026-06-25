<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\Order\OrderDescription;
use Vie\Service\Settings\SepaySettings;

final class SepayCheckout
{
    public function __construct(
        private readonly SepaySettings $settings,
        private readonly SepaySignature $signature,
        private readonly OrderRepository $orderRepo,
        private readonly OrderItemRepository $itemRepo,
        private readonly OrderDescription $description,
    ) {
    }

    /**
     * Dựng dữ liệu form checkout SePay (Cổng thanh toán). Client tạo <form method=POST>
     * ẩn rồi auto-submit sang `action` — KHÔNG dùng GET redirect (endpoint chỉ nhận POST form).
     *
     * @return array{action:string, fields:array<string,string>}|null
     */
    public function buildCheckoutForm(int $orderId): ?array
    {
        if (!$this->settings->enabled()
            || $this->settings->merchantId() === ''
            || $this->settings->secretKey() === ''
        ) {
            return null;
        }

        $order = $this->orderRepo->find($orderId);
        if ($order === null) {
            return null;
        }

        $items = $this->itemRepo->all([
            'order_id' => $orderId,
            'per_page' => 100,
        ])['data'] ?? [];

        $desc = $this->sanitizeDescription($this->description->build($order, $items));

        // Bỏ payment_method → để SePay hiển thị mọi cách (thẻ / QR Banking / NAPAS).
        $fields = [
            'merchant'             => $this->settings->merchantId(),
            'currency'             => (string) ($order['currency'] ?? 'VND'),
            'order_amount'         => (string) ((int) $order['total']),
            'operation'            => 'PURCHASE',
            'order_description'    => $desc,
            'order_invoice_number' => (string) $order['code'],
            'success_url'          => $this->callbackUrl($order, 'success'),
            'error_url'            => $this->callbackUrl($order, 'error'),
            'cancel_url'           => $this->callbackUrl($order, 'cancel'),
        ];
        if (!empty($order['customer_id'])) {
            $fields['customer_id'] = (string) $order['customer_id'];
        }
        $fields['signature'] = $this->signature->signCheckout($fields);

        return [
            'action' => $this->settings->checkoutUrl(),
            'fields' => $fields,
        ];
    }

    private function callbackUrl(array $order, string $kind): string
    {
        // Kèm cả phone để trang /dat-phong-thanh-cong/ tra cứu đơn được sau redirect.
        return add_query_arg([
            'code'  => (string) ($order['code'] ?? ''),
            'phone' => (string) ($order['customer_phone'] ?? ''),
            'state' => $kind,
        ], home_url('/dat-phong-thanh-cong/'));
    }

    private function sanitizeDescription(string $raw): string
    {
        // ASCII-safe, max 250 chars
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $raw) ?? '';
        $ascii = preg_replace('/\s+/', ' ', trim($ascii)) ?? '';
        return mb_substr($ascii, 0, 250);
    }
}
