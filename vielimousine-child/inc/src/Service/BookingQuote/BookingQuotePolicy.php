<?php
declare(strict_types=1);

namespace Vie\Service\BookingQuote;

final class BookingQuotePolicy
{
    public function effectiveStatus(array $quote, ?int $now = null): string
    {
        $status = (string) ($quote['status'] ?? 'draft');
        if ($status !== 'published') {
            return in_array($status, ['draft', 'revoked'], true) ? $status : 'draft';
        }

        if ((int) ($quote['paid_amount'] ?? 0) <= 0) {
            $expiry = $this->expiryTimestamp($quote['valid_until'] ?? null);
            if ($expiry !== null && $expiry <= ($now ?? time())) {
                return 'expired';
            }
        }

        return 'published';
    }

    public function paymentStatus(array $quote): string
    {
        if (!empty($quote['payment_review'])) {
            return 'review_required';
        }

        $total = max(0, (int) ($quote['total'] ?? 0));
        $paid  = max(0, (int) ($quote['paid_amount'] ?? 0));
        if ($total > 0 && $paid >= $total) {
            return 'paid';
        }

        $deposit = max(0, (int) ($quote['deposit_amount'] ?? 0));
        if ($paid > 0 && $deposit > 0 && $paid >= $deposit) {
            return 'deposit_paid';
        }

        return 'unpaid';
    }

    public function remainingAmount(array $quote): int
    {
        return max(0, (int) ($quote['total'] ?? 0) - max(0, (int) ($quote['paid_amount'] ?? 0)));
    }

    public function dueAmount(array $quote): int
    {
        $remaining = $this->remainingAmount($quote);
        if ($remaining === 0) {
            return 0;
        }

        if ((int) ($quote['paid_amount'] ?? 0) > 0) {
            return $remaining;
        }

        return min($remaining, max(0, (int) ($quote['deposit_amount'] ?? 0)));
    }

    public function canCheckout(array $quote, ?int $now = null): bool
    {
        if ((string) ($quote['status'] ?? '') !== 'published' || !empty($quote['payment_review'])) {
            return false;
        }
        if ($this->dueAmount($quote) <= 0) {
            return false;
        }
        if ((int) ($quote['paid_amount'] ?? 0) > 0) {
            return true;
        }
        return $this->effectiveStatus($quote, $now) === 'published';
    }

    public function expiresAt(array $quote): ?string
    {
        $value = $quote['valid_until'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = $this->parseLocalDateTime($value);
        return $date?->format(DATE_ATOM);
    }

    private function expiryTimestamp(mixed $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return $this->parseLocalDateTime($value)?->getTimestamp();
    }

    private function parseLocalDateTime(string $value): ?\DateTimeImmutable
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }
}
