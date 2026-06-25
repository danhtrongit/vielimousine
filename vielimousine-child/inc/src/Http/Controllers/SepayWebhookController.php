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
        // SePay Cổng thanh toán xác thực IPN theo cấu hình:
        //  - auth type = SECRET_KEY  -> header "X-Secret-Key: <secret>"
        //  - auth type = API Key (MẶC ĐỊNH) -> header "Authorization: Apikey <secret>"
        // Chấp nhận cả hai để chạy được dù merchant chọn kiểu nào.
        $authSecret = (string) ($request->get_header('X-Secret-Key') ?? $request->get_header('X_SECRET_KEY') ?? '');
        if ($authSecret === '') {
            $authz = trim((string) ($request->get_header('Authorization') ?? ''));
            if (stripos($authz, 'Apikey ') === 0) {
                $authSecret = trim(substr($authz, 7));
            } elseif (stripos($authz, 'Bearer ') === 0) {
                $authSecret = trim(substr($authz, 7));
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $webhook = Container::get(SepayWebhook::class);
            $webhook->handle($payload, $authSecret, $ip);
        } catch (\Throwable $e) {
            // IPN KHÔNG bao giờ throw ra ngoài — luôn 200 để SePay không hiểu nhầm là lỗi hạ tầng.
            error_log('[SePay IPN] ' . $e->getMessage());
        }

        // SePay yêu cầu body đúng {"success": true} mới coi là nhận thành công.
        return new \WP_REST_Response(['success' => true], 200);
    }
}
