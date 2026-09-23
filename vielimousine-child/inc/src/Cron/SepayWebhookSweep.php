<?php
declare(strict_types=1);

namespace Vie\Cron;

use Vie\Container;
use Vie\Service\Payment\SepayWebhook;

/** Retries events persisted before a transient dispatch/storage failure. */
final class SepayWebhookSweep
{
    public static function run(): void
    {
        try {
            Container::get(SepayWebhook::class)->retryPending(50);
        } catch (\Throwable $e) {
            error_log('[vie] SePay webhook sweep failed');
        }
    }
}
