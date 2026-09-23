<?php
declare(strict_types=1);

namespace Vie\Service\BookingQuote;

use Vie\Repository\BookingQuoteRepository;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\RepositoryException;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Service\Pricing\PriceCalculator;
use Vie\Repository\RoomRepository;
use Vie\Repository\HotelRepository;
use Vie\Repository\CustomerRepository;
use Vie\DTO\QuoteRequest;
use Vie\Support\Money;
use Vie\Support\CostVisibility;
use Vie\Validation\Schemas\BookingQuoteValidation;

final class BookingQuoteService
{
    public function __construct(
        private readonly BookingQuoteRepository $quotes,
        private readonly BookingQuotePolicy $policy,
        private readonly InvoiceSettings $invoiceSettings,
        private readonly ?ActivityLogRepository $activityLog = null,
        private readonly ?PriceCalculator $priceCalculator = null,
        private readonly ?RoomRepository $rooms = null,
        private readonly ?HotelRepository $hotels = null,
        private readonly ?CustomerRepository $customers = null,
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
            'customer_id' => null,
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
            'items' => [],
            'discount' => 0,
            'deposit_type' => 'percent',
            'deposit_value' => 0,
            'valid_until' => null,
        ], $clean);
        $base = array_merge($base, $this->customerSnapshot($base));
        $base = array_merge($base, $this->reprice($base), [
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
            $merged = array_merge($merged, $this->customerSnapshot($merged));
            $this->assertTripDates($merged);
            $patch = array_merge($clean, $this->customerSnapshot($merged), $this->reprice($merged));
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
            $calculated = $this->reprice($quote);
            $quote = array_merge($quote, $calculated);
            // Re-validate persisted media at the immutable publish boundary in case
            // a legacy/import path wrote a URL before this validation existed.
            BookingQuoteValidation::normalize(['image_url' => $quote['image_url'] ?? null]);
            BookingQuoteValidation::assertPublishable($quote);
            $snapshot = array_merge($calculated, [
                'lines' => $quote['lines'] ?? [],
                'items' => $quote['items'] ?? [],
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

    /**
     * Build the safe payload used when an operator continues a quote in the
     * Orders wizard. This creates an order draft only: stock, coupon and
     * payment side effects happen after the operator confirms the order.
     *
     * @throws BookingQuoteException
     */
    public function orderDraftPayload(int $id, int $userId): array
    {
        $quote = $this->getAdmin($id, $userId);
        if (($quote['status'] ?? '') === 'revoked') {
            throw new BookingQuoteException('Báo giá đã thu hồi, không thể tạo đơn', 'invalid_quote_state', 409);
        }

        $items = is_array($quote['items'] ?? null) ? array_values($quote['items']) : [];
        if ($items === []) {
            throw new BookingQuoteException('Báo giá chưa có lựa chọn phòng để tạo đơn', 'quote_items_required', 422, 'items');
        }

        $first = is_array($items[0] ?? null) ? $items[0] : [];
        $wizardItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || (int) ($item['room_id'] ?? 0) <= 0) {
                throw new BookingQuoteException('Báo giá có lựa chọn phòng không hợp lệ', 'quote_items_invalid', 422, 'items');
            }
            $wizardItems[] = [
                'room_id' => (int) $item['room_id'],
                'booking_type' => (string) ($item['booking_type'] ?? 'room'),
                'checkin' => (string) ($item['checkin'] ?? ''),
                'checkout' => (string) ($item['checkout'] ?? ''),
                'adults' => (int) ($item['adults'] ?? 1),
                'child_ages' => is_array($item['child_ages'] ?? null) ? array_values($item['child_ages']) : [],
                'user_rooms' => (int) ($item['user_rooms'] ?? 0),
            ];
        }

        $tripStart = (string) ($quote['trip_start'] ?? ($first['checkin'] ?? ''));
        $tripEnd = (string) ($quote['trip_end'] ?? ($first['checkout'] ?? ''));
        $startTs = $tripStart !== '' ? strtotime($tripStart) : false;
        $endTs = $tripEnd !== '' ? strtotime($tripEnd) : false;
        $nights = $startTs !== false && $endTs !== false ? max(0, (int) (($endTs - $startTs) / 86400)) : null;
        $childAges = is_array($first['child_ages'] ?? null) ? array_values($first['child_ages']) : [];
        $note = 'Tạo từ báo giá ' . (string) ($quote['code'] ?? '');

        return [
            'customer_phone' => (string) ($quote['customer_phone'] ?? ''),
            'customer_name' => (string) ($quote['customer_name'] ?? ''),
            'customer_email' => !empty($quote['customer_email']) ? (string) $quote['customer_email'] : null,
            'source' => 'booking_quote',
            'customer_note' => $note,
            'checkin' => $tripStart !== '' ? $tripStart : null,
            'checkout' => $tripEnd !== '' ? $tripEnd : null,
            'nights' => $nights,
            'adults' => (int) ($first['adults'] ?? 0),
            'children' => count($childAges),
            'child_ages' => $childAges,
            'subtotal' => (int) ($quote['subtotal'] ?? 0),
            'discount' => (int) ($quote['discount'] ?? 0),
            'total' => (int) ($quote['total'] ?? 0),
            'draft_payload' => [
                'quote_id' => (int) $quote['id'],
                'quote_code' => (string) ($quote['code'] ?? ''),
                'quote_public_id' => (string) ($quote['public_id'] ?? ''),
                'wizard' => [
                    'customer' => [
                        'phone' => (string) ($quote['customer_phone'] ?? ''),
                        'name' => (string) ($quote['customer_name'] ?? ''),
                        'email' => !empty($quote['customer_email']) ? (string) $quote['customer_email'] : '',
                    ],
                    'item' => $wizardItems[0],
                    'items' => $wizardItems,
                    'couponCode' => '',
                    'source' => 'booking_quote',
                    'customerNote' => $note,
                ],
            ],
        ];
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
        $view['items'] = $this->safeItems($quote['items'] ?? [], true);
        $view['lines'] = $this->safeLines($quote['lines'] ?? []);
        return $view;
    }

    public function publicView(array $quote): array
    {
        $allowed = [
            'public_id', 'code', 'customer_name', 'title', 'image_url', 'greeting',
            'trip_start', 'trip_end', 'description', 'inclusions', 'exclusions', 'terms',
            'contact_name', 'contact_phone', 'contact_zalo', 'lines', 'discount',
            'deposit_type', 'deposit_value', 'subtotal', 'total', 'deposit_amount',
            'paid_amount', 'brand', 'items',
        ];
        $view = array_intersect_key($quote, array_flip($allowed));
        $view['items'] = $this->safeItems($quote['items'] ?? [], false);
        $view['lines'] = $this->safeLines($quote['lines'] ?? []);
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

    /**
     * A selected customer is the same canonical record used by Orders. Store
     * the contact fields alongside the foreign key as a quote snapshot so a
     * later customer edit cannot silently rewrite an already issued quote.
     *
     * @return array{customer_id:int|null,customer_name?:string,customer_phone?:string,customer_email?:string|null}
     * @throws BookingQuoteException
     */
    private function customerSnapshot(array $data): array
    {
        $id = $data['customer_id'] ?? null;
        if ($id === null || $id === '') {
            return ['customer_id' => null];
        }
        $id = (int) $id;
        if ($id <= 0 || $this->customers === null) {
            throw new BookingQuoteException('Khách hàng không hợp lệ hoặc không còn tồn tại', 'customer_not_found', 422, 'customer_id');
        }
        $customer = $this->customers->find($id);
        if ($customer === null) {
            throw new BookingQuoteException('Khách hàng không tồn tại', 'customer_not_found', 422, 'customer_id');
        }
        return [
            'customer_id' => $id,
            'customer_name' => (string) ($customer['name'] ?? ''),
            'customer_phone' => (string) ($customer['phone'] ?? ''),
            'customer_email' => !empty($customer['email']) ? (string) $customer['email'] : null,
        ];
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
        $rawTotal = max(0, $subtotal - $discount);
        // Keep the domain helper as the source of truth; the fallback only
        // supports lightweight unit harnesses that do not load the autoloader.
        $total = class_exists(Money::class) ? Money::roundVND($rawTotal) : $rawTotal;
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

    /** Reprice room selections through the same server calculator used by Orders. */
    private function reprice(array $quote): array
    {
        $items = $quote['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return $this->calculate($quote); // historical/manual drafts remain readable
        }
        if ($this->priceCalculator === null || $this->rooms === null || $this->hotels === null) {
            throw new BookingQuoteException('Không thể định giá lựa chọn phòng', 'save_failed', 500);
        }
        $generated = [];
        $subtotal = 0;
        foreach ($items as $index => $input) {
            try {
                $request = QuoteRequest::fromArray($input);
                $breakdown = $this->priceCalculator->quote($request, false);
                if ($breakdown->requiresQuote) {
                    throw new BookingQuoteException(
                        'Lựa chọn phòng chưa có giá hoặc tồn kho phù hợp',
                        'room_requires_quote', 422, "items.{$index}"
                    );
                }
                $room = $this->rooms->findOrFail($request->roomId);
                $hotel = $this->hotels->findOrFail((int) $room['hotel_id']);
            } catch (BookingQuoteException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new BookingQuoteException('Phòng không tồn tại hoặc không thể định giá', 'validation_error', 422, "items.{$index}", $e);
            }
            $snapshot = $breakdown->toArray();
            $item = array_merge($input, [
                'hotel_id' => (int) ($room['hotel_id'] ?? 0),
                'hotel_name' => (string) ($hotel['name'] ?? ''),
                'room_name' => (string) ($room['name'] ?? ''),
                'num_rooms' => $breakdown->numRooms,
                'nights' => $breakdown->nights,
                'room_subtotal' => $breakdown->roomSubtotal,
                'extra_adult_total' => $breakdown->extraAdultSubtotal,
                'child_surcharge_total' => $breakdown->childSurchargeTotal,
                'ticket_count' => $breakdown->seatCount,
                'ticket_subtotal' => $breakdown->ticketSubtotal,
                'subtotal' => $breakdown->subtotal,
                'line_total' => $breakdown->subtotal,
                'pricing_snapshot' => $snapshot,
            ]);
            $generated[] = $item;
            $subtotal += $breakdown->subtotal;
        }
        $next = array_merge($quote, ['items' => $generated]);
        $discount = $next['discount'] ?? 0;
        if (!is_int($discount) || $discount < 0 || $discount > $subtotal) {
            throw new BookingQuoteException('Giảm giá không được vượt quá tạm tính', 'validation_error', 422, 'discount');
        }
        $lines = array_map(static function (array $item): array {
            return [
                'label' => trim((string) ($item['hotel_name'] ?? '') . ' - ' . (string) ($item['room_name'] ?? 'Phòng')),
                'unit' => 'gói lưu trú',
                'quantity' => 1,
                'unit_price' => (int) ($item['line_total'] ?? 0),
                'line_total' => (int) ($item['line_total'] ?? 0),
                'room_id' => (int) ($item['room_id'] ?? 0),
                'hotel_id' => (int) ($item['hotel_id'] ?? 0),
                'hotel_name' => (string) ($item['hotel_name'] ?? ''),
                'room_name' => (string) ($item['room_name'] ?? ''),
                'booking_type' => (string) ($item['booking_type'] ?? 'room'),
                'checkin' => (string) ($item['checkin'] ?? ''),
                'checkout' => (string) ($item['checkout'] ?? ''),
                'adults' => (int) ($item['adults'] ?? 0),
                'child_ages' => $item['child_ages'] ?? [],
                'user_rooms' => (int) ($item['user_rooms'] ?? 0),
                'num_rooms' => (int) ($item['num_rooms'] ?? 0),
                'nights' => (int) ($item['nights'] ?? 0),
                'pricing_snapshot' => $item['pricing_snapshot'] ?? [],
            ];
        }, $generated);
        $next['lines'] = $lines;
        $next['subtotal'] = $subtotal;
        return array_merge($this->calculate($next), [
            'items' => $generated,
            'lines' => $lines,
        ]);
    }

    private function safeItems(mixed $items, bool $admin): array
    {
        if (!is_array($items)) return [];
        return array_map(function (mixed $item) use ($admin): array {
            if (!is_array($item)) return [];
            $allowed = [
                'room_id','hotel_id','hotel_name','room_name','booking_type','checkin','checkout',
                'adults','child_ages','user_rooms','num_rooms','nights','room_subtotal',
                'extra_adult_total','child_surcharge_total','ticket_count','ticket_subtotal',
                'subtotal','line_total',
            ];
            if ($admin) $allowed[] = 'pricing_snapshot';
            $result = array_intersect_key($item, array_flip($allowed));
            if (isset($result['pricing_snapshot']) && !CostVisibility::canView()) {
                unset($result['pricing_snapshot']);
            }
            return $result;
        }, $items);
    }

    private function safeLines(mixed $lines): array
    {
        if (!is_array($lines)) return [];
        return array_map(static function (mixed $line): array {
            if (!is_array($line)) return [];
            $allowed = [
                'label','quantity','unit','unit_price','line_total','room_id','hotel_id',
                'hotel_name','room_name','booking_type','checkin','checkout','adults',
                'child_ages','user_rooms','num_rooms','nights',
            ];
            return array_intersect_key($line, array_flip($allowed));
        }, $lines);
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
