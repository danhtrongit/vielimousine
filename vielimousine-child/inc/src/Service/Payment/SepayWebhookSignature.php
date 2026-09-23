<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

/**
 * Verifies SePay Webhook HMAC signatures against the original request bytes.
 */
final class SepayWebhookSignature
{
    /** @return array{valid:bool,reason:string} */
    public function verify(string $rawBody, string $signature, string $timestamp, string $secret, ?int $now = null): array
    {
        $secret = trim($secret);
        $signature = trim($signature);
        $timestamp = trim($timestamp);
        $timestampInt = filter_var($timestamp, FILTER_VALIDATE_INT);

        if ($secret === '') {
            return ['valid' => false, 'reason' => 'missing_secret'];
        }
        if ($timestampInt === false || $timestamp === '' || !preg_match('/^-?\d+$/', $timestamp)) {
            return ['valid' => false, 'reason' => 'invalid_timestamp'];
        }
        $clock = $now ?? time();
        if (abs($clock - (int) $timestampInt) > 300) {
            return ['valid' => false, 'reason' => 'expired_timestamp'];
        }
        if (!preg_match('/^sha256=([a-f0-9]{64})$/i', $signature, $matches)) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        if (!hash_equals(strtolower($expected), strtolower($signature))) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        return ['valid' => true, 'reason' => ''];
    }
}
