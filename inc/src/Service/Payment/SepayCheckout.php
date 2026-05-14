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

    public function buildRedirectUrl(int $orderId): ?string
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

        $descRaw = $this->description->build($order, $items);
        $desc    = $this->sanitizeDescription($descRaw);

        $params = [
            'merchant'             => $this->settings->merchantId(),
            'operation'            => 'order_pay',
            'payment_method'       => 'auto',
            'order_amount'         => (string) ((int) $order['total']),
            'currency'             => (string) ($order['currency'] ?? 'VND'),
            'order_invoice_number' => (string) $order['code'],
            'order_description'    => $desc,
            'customer_id'          => (string) ($order['customer_id'] ?? ''),
            'success_url'          => $this->callbackUrl($order['code'], 'success'),
            'error_url'            => $this->callbackUrl($order['code'], 'error'),
            'cancel_url'           => $this->callbackUrl($order['code'], 'cancel'),
        ];
        $params['signature'] = $this->signature->sign($params);

        return $this->settings->checkoutUrl() . '?' . http_build_query($params);
    }

    private function callbackUrl(string $code, string $kind): string
    {
        $base = home_url('/dat-phong-thanh-cong/');
        return add_query_arg(['code' => $code, 'state' => $kind], $base);
    }

    private function sanitizeDescription(string $raw): string
    {
        // ASCII-safe, max 250 chars
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $raw) ?? '';
        $ascii = preg_replace('/\s+/', ' ', trim($ascii)) ?? '';
        return mb_substr($ascii, 0, 250);
    }
}
