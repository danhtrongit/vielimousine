<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Payment\SepayWebhook;

final class SepayWebhookController
{
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params() ?? [];
        if (!is_array($payload)) {
            $payload = [];
        }
        $sig = (string) ($request->get_header('X-Sepay-Signature') ?? $request->get_header('X_SEPAY_SIGNATURE') ?? '');
        $ip  = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $webhook = Container::get(SepayWebhook::class);
            $webhook->handle($payload, $sig, $ip);
        } catch (\Throwable $e) {
            // Theo docs: webhook KHÔNG bao giờ throw ra ngoài — luôn 200
            error_log('[SePay webhook] ' . $e->getMessage());
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }
}
