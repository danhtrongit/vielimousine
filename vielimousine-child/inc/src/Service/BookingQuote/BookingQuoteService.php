<?php
declare(strict_types=1);

namespace Vie\Service\BookingQuote;

use Vie\Repository\BookingQuoteRepository;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\RepositoryException;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Validation\Schemas\BookingQuoteValidation;

final class BookingQuoteService
{
    public function __construct(
        private readonly BookingQuoteRepository $quotes,
        private readonly BookingQuotePolicy $policy,
        private readonly InvoiceSettings $invoiceSettings,
        private readonly ?ActivityLogRepository $activityLog = null,
    ) {
    }

    public function list(array $params): array
    {
        $result = $this->quotes->all($params);
        $result['data'] = array_map(fn(array $quote): array => $this->adminView($quote), $result['data']);
        return $result;
    }

    /** @throws BookingQuoteException */
    public function getAdmin(int $id, int $userId): array
    {
        $quote = $this->quotes->find($id);
        if ($quote === null) {
            throw new BookingQuoteException('Báo giá không tồn tại', 'not_found', 404);
        }
        $this->assertAccess($userId, $quote);
        return $quote;
    }

    /** @throws BookingQuoteException */
    public function createDraft(array $input, int $userId): array
    {
        $this->assertCanCreate($userId);
        $clean = BookingQuoteValidation::normalize($input);
        $base = array_merge([
            'customer_name' => '',
            'customer_phone' => '',
            'customer_email' => null,
            'title' => '',
            'image_url' => null,
            'greeting' => null,
            'trip_start' => null,
            'trip_end' => null,
            'description' => null,
            'inclusions' => null,
            'exclusions' => null,
            'terms' => null,
            'contact_name' => null,
            'contact_phone' => null,
            'contact_zalo' => null,
            'lines' => [],
            'discount' => 0,
            'deposit_type' => 'percent',
            'deposit_value' => 0,
            'valid_until' => null,
        ], $clean);
        $base = array_merge($base, $this->calculate($base), [
            'sales_user_id' => $userId,
            'status' => 'draft',
            'paid_amount' => 0,
            'payment_review' => false,
            'brand' => null,
            'published_at' => null,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $base['public_id'] = bin2hex(random_bytes(16));
            $base['code'] = $this->newDisplayCode();
            try {
                return $this->adminView($this->quotes->create($base));
            } catch (RepositoryException $e) {
                if ($attempt === 4) {
                    throw new BookingQuoteException('Không thể tạo báo giá lúc này', 'save_failed', 500, null, $e);
                }
            }
        }

        throw new BookingQuoteException('Không thể tạo báo giá lúc này', 'save_failed', 500);
    }

    /** @throws BookingQuoteException */
    public function updateDraft(int $id, array $input, int $userId): array
    {
        $this->assertCanCreate($userId);
        $clean = BookingQuoteValidation::normalize($input);
        return $this->transaction(function () use ($id, $userId, $clean): array {
            $quote = $this->lockAccessible($id, $userId);
            if (($quote['status'] ?? '') !== 'draft') {
                throw new BookingQuoteException('Báo giá đã phát hành hoặc thu hồi không thể chỉnh sửa', 'quote_immutable', 409);
            }
            $merged = array_merge($quote, $clean);
            $this->assertTripDates($merged);
            $patch = array_merge($clean, $this->calculate($merged));
            try {
                return $this->adminView($this->quotes->updateDraftChecked($id, $patch));
            } catch (RepositoryException $e) {
                throw new BookingQuoteException('Không thể cập nhật báo giá', 'save_failed', 500, null, $e);
            }
        });
    }

    /** @throws BookingQuoteException */
    public function publish(int $id, int $userId): array
    {
        $this->assertCanCreate($userId);
        $view = $this->transaction(function () use ($id, $userId): array {
            $quote = $this->lockAccessible($id, $userId);
            if (($quote['status'] ?? '') !== 'draft') {
                throw new BookingQuoteException('Chỉ báo giá nháp mới có thể phát hành', 'invalid_quote_state', 409);
            }
            $this->assertTripDates($quote);
            $calculated = $this->calculate($quote);
            $quote = array_merge($quote, $calculated);
            // Re-validate persisted media at the immutable publish boundary in case
            // a legacy/import path wrote a URL before this validation existed.
            BookingQuoteValidation::normalize(['image_url' => $quote['image_url'] ?? null]);
            BookingQuoteValidation::assertPublishable($quote);
            $snapshot = array_merge($calculated, [
                'brand' => $this->brandSnapshot(),
                'published_at' => current_time('mysql'),
            ]);
            try {
                return $this->adminView($this->quotes->publishChecked($id, $snapshot));
            } catch (RepositoryException $e) {
                throw new BookingQuoteException('Trạng thái báo giá vừa thay đổi, vui lòng tải lại', 'quote_conflict', 409, null, $e);
            }
        });
        $this->audit($view, 'booking_quote_published', $userId, 'draft', 'published');
        return $view;
    }

    /** @throws BookingQuoteException */
    public function revoke(int $id, int $userId): array
    {
        $this->assertCanCreate($userId);
        $view = $this->transaction(function () use ($id, $userId): array {
            $quote = $this->lockAccessible($id, $userId);
            if (($quote['status'] ?? '') !== 'published') {
                throw new BookingQuoteException('Chỉ báo giá đang phát hành mới có thể thu hồi', 'invalid_quote_state', 409);
            }
            try {
                return $this->adminView($this->quotes->revokeChecked($id));
            } catch (RepositoryException $e) {
                throw new BookingQuoteException('Trạng thái báo giá vừa thay đổi, vui lòng tải lại', 'quote_conflict', 409, null, $e);
            }
        });
        $this->audit($view, 'booking_quote_revoked', $userId, 'published', 'revoked');
        return $view;
    }

    /** @throws BookingQuoteException */
    public function duplicate(int $id, int $userId): array
    {
        $this->assertCanCreate($userId);
        $source = $this->getAdmin($id, $userId);
        $editable = [];
        foreach (array_keys(BookingQuoteValidation::rules()) as $field) {
            if (array_key_exists($field, $source)) {
                $editable[$field] = $source[$field];
            }
        }
        return $this->createDraft($editable, $userId);
    }

    /** Generic public lookup: draft/revoked/unknown are intentionally indistinguishable. */
    public function getPublic(string $publicId): array
    {
        $quote = $this->quotes->findByPublicId(strtolower(trim($publicId)));
        if ($quote === null || !in_array(($quote['status'] ?? ''), ['published'], true)) {
            throw new BookingQuoteException('Báo giá không tồn tại', 'not_found', 404);
        }
        return $this->publicView($quote);
    }

    public function adminView(array $quote): array
    {
        $view = $quote;
        $view['effective_status'] = $this->policy->effectiveStatus($quote);
        $view['payment_status'] = $this->policy->paymentStatus($quote);
        $view['remaining_amount'] = $this->policy->remainingAmount($quote);
        $view['due_amount'] = $this->policy->dueAmount($quote);
        $view['can_checkout'] = $this->policy->canCheckout($quote);
        $view['expires_at'] = $this->policy->expiresAt($quote);
        $view['public_url'] = $this->publicUrl((string) ($quote['public_id'] ?? ''));
        $view['currency'] = 'VND';
        return $view;
    }

    public function publicView(array $quote): array
    {
        $allowed = [
            'public_id', 'code', 'customer_name', 'title', 'image_url', 'greeting',
            'trip_start', 'trip_end', 'description', 'inclusions', 'exclusions', 'terms',
            'contact_name', 'contact_phone', 'contact_zalo', 'lines', 'discount',
            'deposit_type', 'deposit_value', 'subtotal', 'total', 'deposit_amount',
            'paid_amount', 'brand',
        ];
        $view = array_intersect_key($quote, array_flip($allowed));
        $view['effective_status'] = $this->policy->effectiveStatus($quote);
        $view['payment_status'] = $this->policy->paymentStatus($quote);
        $view['remaining_amount'] = $this->policy->remainingAmount($quote);
        $view['due_amount'] = $this->policy->dueAmount($quote);
        $view['can_checkout'] = $this->policy->canCheckout($quote);
        $view['expires_at'] = $this->policy->expiresAt($quote);
        $view['public_url'] = $this->publicUrl((string) ($quote['public_id'] ?? ''));
        $view['currency'] = 'VND';
        return $view;
    }

    /** @return array{subtotal:int,total:int,deposit_amount:int} */
    private function calculate(array $quote): array
    {
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];
        $subtotal = 0;
        foreach ($lines as $line) {
            $lineTotal = (int) ($line['line_total'] ?? 0);
            if ($lineTotal < 0 || $lineTotal > BookingQuoteValidation::MAX_MONEY - $subtotal) {
                throw new BookingQuoteException('Tổng báo giá vượt giới hạn cho phép', 'validation_error', 422, 'lines');
            }
            $subtotal += $lineTotal;
        }
        $discount = $quote['discount'] ?? 0;
        if (!is_int($discount) || $discount < 0 || $discount > $subtotal) {
            throw new BookingQuoteException('Giảm giá không được vượt quá tạm tính', 'validation_error', 422, 'discount');
        }
        $total = $subtotal - $discount;
        $type = (string) ($quote['deposit_type'] ?? 'percent');
        $value = $quote['deposit_value'] ?? 0;
        if (!is_int($value) || $value < 0) {
            throw new BookingQuoteException('Giá trị tiền cọc không hợp lệ', 'validation_error', 422, 'deposit_value');
        }
        if ($type === 'percent') {
            if ($value > 100) {
                throw new BookingQuoteException('Tỷ lệ cọc không được vượt quá 100%', 'validation_error', 422, 'deposit_value');
            }
            // Round up to the nearest VND so a positive percentage never loses a fractional đồng.
            $deposit = $value === 0 ? 0 : intdiv($total * $value + 99, 100);
        } elseif ($type === 'fixed') {
            $deposit = $value;
        } else {
            throw new BookingQuoteException('Loại tiền cọc không hợp lệ', 'validation_error', 422, 'deposit_type');
        }
        if ($deposit > $total || $deposit > BookingQuoteValidation::MAX_MONEY) {
            throw new BookingQuoteException('Tiền cọc không được vượt tổng tiền', 'validation_error', 422, 'deposit_value');
        }
        return ['subtotal' => $subtotal, 'total' => $total, 'deposit_amount' => $deposit];
    }

    private function assertTripDates(array $quote): void
    {
        $start = $quote['trip_start'] ?? null;
        $end = $quote['trip_end'] ?? null;
        if (is_string($start) && $start !== '' && is_string($end) && $end !== '' && $end < $start) {
            throw new BookingQuoteException('Ngày kết thúc không được trước ngày bắt đầu', 'validation_error', 422, 'trip_end');
        }
    }

    private function brandSnapshot(): array
    {
        $all = $this->invoiceSettings->all();
        $raw = get_option('vie_invoice_settings', '');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $raw = is_array($raw) ? $raw : [];

        $logo = (string) ($all['logo_url'] ?? '');
        if ($logo !== '') {
            try {
                $logo = (string) (BookingQuoteValidation::normalize(['image_url' => $logo])['image_url'] ?? '');
            } catch (BookingQuoteException) {
                $logo = '';
            }
        }

        $plain = static function (mixed $value): string {
            return trim(function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $value)
                : strip_tags((string) $value));
        };
        return [
            'company_name' => $plain($all['company_name'] ?? ''),
            'company_phone' => $plain($all['company_phone'] ?? ''),
            // InvoiceSettings defaults to admin_email; expose it only when explicitly saved.
            'company_email' => array_key_exists('company_email', $raw) ? $plain($raw['company_email']) : '',
            'company_address' => $plain($all['company_address'] ?? ''),
            'company_tax_id' => $plain($all['company_tax_id'] ?? ''),
            'logo_url' => $logo,
        ];
    }

    private function newDisplayCode(): string
    {
        $date = function_exists('wp_date') ? wp_date('ymd') : date('ymd');
        return 'BQ-' . $date . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function publicUrl(string $publicId): string
    {
        $path = '/booking/' . rawurlencode($publicId);
        return function_exists('home_url') ? home_url($path) : $path;
    }

    private function assertCanCreate(int $userId): void
    {
        if ($userId <= 0 || !user_can($userId, 'vie_create_booking_quotes')) {
            throw new BookingQuoteException('Bạn không có quyền thao tác báo giá', 'forbidden', 403);
        }
    }

    private function audit(array $view, string $action, int $userId, string $before, string $after): void
    {
        if ($this->activityLog === null) {
            return;
        }
        try {
            $this->activityLog->create([
                'actor_user_id' => $userId,
                'entity_type' => 'booking_quote',
                'entity_id' => (int) ($view['id'] ?? 0),
                'action' => $action,
                'before_json' => ['status' => $before],
                'after_json' => ['status' => $after, 'code' => (string) ($view['code'] ?? '')],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable) {
            // Audit must not turn a committed commercial state transition into a 500.
        }
    }

    private function assertAccess(int $userId, array $quote): void
    {
        if (!$this->quotes->canUserAccess($userId, $quote)) {
            throw new BookingQuoteException('Bạn không có quyền thao tác báo giá này', 'forbidden', 403);
        }
    }

    private function lockAccessible(int $id, int $userId): array
    {
        $quote = $this->quotes->lockForUpdate($id);
        if ($quote === null) {
            throw new BookingQuoteException('Báo giá không tồn tại', 'not_found', 404);
        }
        $this->assertAccess($userId, $quote);
        return $quote;
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new BookingQuoteException('Không thể bắt đầu giao dịch dữ liệu', 'save_failed', 500);
        }
        try {
            $result = $callback();
            if ($wpdb->query('COMMIT') === false) {
                throw new BookingQuoteException('Không thể hoàn tất giao dịch dữ liệu', 'save_failed', 500);
            }
            return $result;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            if ($e instanceof BookingQuoteException) {
                throw $e;
            }
            throw new BookingQuoteException('Không thể lưu báo giá lúc này', 'save_failed', 500, null, $e);
        }
    }
}
