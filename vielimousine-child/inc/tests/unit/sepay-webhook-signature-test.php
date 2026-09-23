<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Service/Payment/SepayWebhookSignature.php';

use Vie\Service\Payment\SepayWebhookSignature;

$verifier = new SepayWebhookSignature();
$secret = 'unit-test-webhook-secret';
$timestamp = '1700000000';
$body = '{"id":42,"code":"VIE2401010001ABCD","transferAmount":100000}';
$signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
$assertions = 0;
$failures = 0;
$assert = static function (string $label, bool $ok) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$ok) {
        $failures++;
        echo "FAIL: {$label}\n";
    }
};

$assert('valid raw-body HMAC', $verifier->verify($body, $signature, $timestamp, $secret, 1700000000)['valid'] === true);
$assert('body mutation fails signature', $verifier->verify($body . ' ', $signature, $timestamp, $secret, 1700000000)['reason'] === 'invalid_signature');
$assert('wrong secret fails signature', $verifier->verify($body, $signature, $timestamp, 'wrong-secret', 1700000000)['reason'] === 'invalid_signature');
$assert('stale timestamp rejected', $verifier->verify($body, $signature, $timestamp, $secret, 1700000401)['reason'] === 'expired_timestamp');
$assert('malformed timestamp rejected', $verifier->verify($body, $signature, 'now', $secret, 1700000000)['reason'] === 'invalid_timestamp');
$assert('malformed signature rejected', $verifier->verify($body, 'sha256=bad', $timestamp, $secret, 1700000000)['reason'] === 'invalid_signature');

echo "SePay Webhook signature: {$assertions} assertions, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);
