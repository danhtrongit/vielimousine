<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Payment\SepayWebhook;

/** Receives SePay bank-account Webhooks (flat payload + HMAC). */
final class SepayWebhookController
{
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // WP_REST_Request::get_body() retains the exact bytes signed by SePay.
        $rawBody = (string) $request->get_body();
        $signature = trim((string) $request->get_header('X-SePay-Signature'));
        $timestamp = trim((string) $request->get_header('X-SePay-Timestamp'));
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;

        try {
            $result = Container::get(SepayWebhook::class)->handleRaw(
                $rawBody,
                $signature,
                $timestamp,
                $ip
            );
        } catch (\Throwable $e) {
            // Storage/transaction failures must be retried by SePay.
            error_log('[SePay Webhook] processing failure');
            return new \WP_REST_Response(['success' => false], 500);
        }

        $reason = (string) ($result['reason'] ?? '');
        if (in_array($reason, ['missing_secret', 'invalid_timestamp', 'expired_timestamp', 'invalid_signature'], true)) {
            return new \WP_REST_Response(['success' => false], 401);
        }
        if (in_array($reason, ['invalid_json', 'missing_id'], true)) {
            return new \WP_REST_Response(['success' => false], 400);
        }
        if (($result['retryable'] ?? false) === true) {
            return new \WP_REST_Response(['success' => false], 500);
        }

        // SePay accepts only this exact success response for acknowledged,
        // reviewed and duplicate events.
        return new \WP_REST_Response(['success' => true], 200);
    }
}
