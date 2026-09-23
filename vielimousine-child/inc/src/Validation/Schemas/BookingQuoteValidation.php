<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

use Vie\Service\BookingQuote\BookingQuoteException;

final class BookingQuoteValidation
{
    public const MAX_MONEY = 999_999_999_999;
    public const MAX_LINES = 50;
    public const MAX_ITEMS = 5;

    private const SINGLE_TEXT = [
        'customer_name' => 255,
        'customer_phone' => 50,
        'customer_email' => 255,
        'title' => 255,
        'contact_name' => 255,
        'contact_phone' => 50,
        'contact_zalo' => 50,
    ];

    private const LONG_TEXT = [
        'greeting' => 2000,
        'description' => 20000,
        'inclusions' => 20000,
        'exclusions' => 20000,
        'terms' => 20000,
    ];

    /** Rules are supplied for route documentation; normalize() performs strict type checks. */
    public static function rules(): array
    {
        return [
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'title' => 'nullable|string|max:255',
            'image_url' => 'nullable|string',
            'greeting' => 'nullable|string',
            'trip_start' => 'nullable|date',
            'trip_end' => 'nullable|date',
            'description' => 'nullable|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'terms' => 'nullable|string',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_zalo' => 'nullable|string|max:50',
            'lines' => 'nullable|array|max_items:50',
            'items' => 'nullable|array|max_items:5',
            'discount' => 'nullable|int|min:0|max:999999999999',
            'deposit_type' => 'nullable|string|in:percent,fixed',
            'deposit_value' => 'nullable|int|min:0|max:999999999999',
            'valid_until' => 'nullable|string',
        ];
    }

    /**
     * Return only editable fields. System-owned IDs, state and derived totals are ignored.
     *
     * @throws BookingQuoteException
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach (self::SINGLE_TEXT as $field => $max) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if ($value === null && in_array($field, ['customer_email', 'contact_name', 'contact_phone', 'contact_zalo'], true)) {
                $out[$field] = null;
                continue;
            }
            if (!is_string($value)) {
                self::invalid($field, 'phải là chuỗi ký tự');
            }
            $value = self::plainSingleLine($value);
            if (mb_strlen($value) > $max) {
                self::invalid($field, "không được vượt quá {$max} ký tự");
            }
            if ($field === 'customer_email' && $value !== '' && !self::isEmail($value)) {
                self::invalid($field, 'không đúng định dạng email');
            }
            if (in_array($field, ['customer_phone', 'contact_phone', 'contact_zalo'], true)
                && $value !== '' && !preg_match('/^[0-9+\-\s().]{7,20}$/', $value)) {
                self::invalid($field, 'không đúng định dạng số điện thoại');
            }
            $out[$field] = $value === '' && in_array($field, ['customer_email', 'contact_name', 'contact_phone', 'contact_zalo'], true)
                ? null
                : $value;
        }

        foreach (self::LONG_TEXT as $field => $max) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if ($input[$field] !== null && !is_string($input[$field])) {
                self::invalid($field, 'phải là chuỗi ký tự');
            }
            $value = self::plainMultiline((string) ($input[$field] ?? ''));
            if (mb_strlen($value) > $max) {
                self::invalid($field, "không được vượt quá {$max} ký tự");
            }
            $out[$field] = $value === '' ? null : $value;
        }

        foreach (['trip_start', 'trip_end'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if ($value === null || $value === '') {
                $out[$field] = null;
                continue;
            }
            if (!is_string($value) || !self::isDate($value)) {
                self::invalid($field, 'không đúng định dạng ngày YYYY-MM-DD');
            }
            $out[$field] = $value;
        }

        if (isset($out['trip_start'], $out['trip_end']) && $out['trip_end'] < $out['trip_start']) {
            self::invalid('trip_end', 'không được trước ngày bắt đầu');
        }

        if (array_key_exists('image_url', $input)) {
            if ($input['image_url'] !== null && !is_string($input['image_url'])) {
                self::invalid('image_url', 'phải là URL ảnh hợp lệ');
            }
            $out['image_url'] = self::normalizeImageUrl((string) ($input['image_url'] ?? ''));
        }

        if (array_key_exists('lines', $input)) {
            $out['lines'] = self::normalizeLines($input['lines']);
        }

        if (array_key_exists('items', $input)) {
            $out['items'] = self::normalizeItems($input['items']);
        }

        foreach (['discount', 'deposit_value'] as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if (!is_int($value) || $value < 0 || $value > self::MAX_MONEY) {
                self::invalid($field, 'phải là số nguyên VND hợp lệ');
            }
            $out[$field] = $value;
        }

        if (array_key_exists('deposit_type', $input)) {
            if (!is_string($input['deposit_type']) || !in_array($input['deposit_type'], ['percent', 'fixed'], true)) {
                self::invalid('deposit_type', 'phải là percent hoặc fixed');
            }
            $out['deposit_type'] = $input['deposit_type'];
        }

        if (array_key_exists('valid_until', $input)) {
            $out['valid_until'] = self::normalizeLocalDateTime($input['valid_until']);
        }

        return $out;
    }

    /** @throws BookingQuoteException */
    public static function assertPublishable(array $quote, ?int $now = null): void
    {
        if (trim((string) ($quote['customer_name'] ?? '')) === '') {
            self::invalid('customer_name', 'là bắt buộc khi phát hành');
        }
        if (trim((string) ($quote['title'] ?? '')) === '') {
            self::invalid('title', 'là bắt buộc khi phát hành');
        }

        $items = is_array($quote['items'] ?? null) ? $quote['items'] : [];
        if ($items === []) {
            // Manual lines are retained for historical published quotes, but a
            // draft created in the room-pricing workflow must select rooms first.
            if (($quote['status'] ?? 'draft') === 'draft' && empty($quote['published_at'])) {
                self::invalid('items', 'phải có ít nhất một lựa chọn phòng khi phát hành');
            }
        }
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];
        if ($lines === [] || count($lines) > self::MAX_LINES) {
            self::invalid('lines', 'phải có từ 1 đến 50 dòng');
        }
        $hasPositive = false;
        foreach ($lines as $index => $line) {
            if (trim((string) ($line['label'] ?? '')) === '') {
                self::invalid("lines.{$index}.label", 'là bắt buộc khi phát hành');
            }
            if ((int) ($line['line_total'] ?? 0) > 0) {
                $hasPositive = true;
            }
        }
        if (!$hasPositive) {
            self::invalid('lines', 'phải có ít nhất một dòng có giá lớn hơn 0');
        }

        $total   = (int) ($quote['total'] ?? 0);
        $deposit = (int) ($quote['deposit_amount'] ?? 0);
        if ($total <= 0 || $total > self::MAX_MONEY) {
            self::invalid('total', 'phải lớn hơn 0');
        }
        if ($deposit <= 0 || $deposit > $total) {
            self::invalid('deposit_value', 'tiền cọc phải lớn hơn 0 và không vượt tổng tiền');
        }

        $validUntil = $quote['valid_until'] ?? null;
        if (!is_string($validUntil) || $validUntil === '') {
            self::invalid('valid_until', 'là bắt buộc khi phát hành');
        }
        $expires = self::localDateTime($validUntil);
        if ($expires === null || $expires->getTimestamp() <= ($now ?? time())) {
            self::invalid('valid_until', 'phải là thời điểm trong tương lai');
        }
    }

    /** @throws BookingQuoteException */
    private static function normalizeLines(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_LINES) {
            self::invalid('lines', 'phải là danh sách tối đa 50 dòng');
        }
        $lines = [];
        foreach ($value as $index => $line) {
            if (!is_array($line)) {
                self::invalid("lines.{$index}", 'phải là một dòng báo giá');
            }
            $label = $line['label'] ?? '';
            $unit  = $line['unit'] ?? '';
            if (!is_string($label) || !is_string($unit)) {
                self::invalid("lines.{$index}", 'nhãn và đơn vị phải là chuỗi');
            }
            $label = self::plainSingleLine($label);
            $unit  = self::plainSingleLine($unit);
            if (mb_strlen($label) > 255 || mb_strlen($unit) > 100) {
                self::invalid("lines.{$index}", 'nhãn hoặc đơn vị quá dài');
            }
            $quantity  = $line['quantity'] ?? 1;
            $unitPrice = $line['unit_price'] ?? 0;
            if (!is_int($quantity) || $quantity < 1 || $quantity > 1000) {
                self::invalid("lines.{$index}.quantity", 'phải là số nguyên từ 1 đến 1000');
            }
            if (!is_int($unitPrice) || $unitPrice < 0 || $unitPrice > self::MAX_MONEY) {
                self::invalid("lines.{$index}.unit_price", 'phải là số nguyên VND hợp lệ');
            }
            if ($unitPrice > intdiv(self::MAX_MONEY, $quantity)) {
                self::invalid("lines.{$index}.unit_price", 'làm tổng dòng vượt giới hạn');
            }
            $lines[] = [
                'label' => $label,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        }
        return $lines;
    }

    /** @throws BookingQuoteException */
    private static function normalizeItems(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_ITEMS) {
            self::invalid('items', 'phải là danh sách tối đa 5 lựa chọn phòng');
        }
        $items = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                self::invalid("items.{$index}", 'phải là một lựa chọn phòng');
            }
            $roomId = $item['room_id'] ?? null;
            $type = $item['booking_type'] ?? null;
            if (!is_int($roomId) || $roomId <= 0) {
                self::invalid("items.{$index}.room_id", 'phải là số nguyên dương');
            }
            if (!is_string($type) || !in_array($type, ['room', 'combo'], true)) {
                self::invalid("items.{$index}.booking_type", 'phải là room hoặc combo');
            }
            foreach (['checkin', 'checkout'] as $field) {
                if (!is_string($item[$field] ?? null) || !self::isDate($item[$field])) {
                    self::invalid("items.{$index}.{$field}", 'không đúng định dạng ngày YYYY-MM-DD');
                }
            }
            if ($item['checkout'] <= $item['checkin']) {
                self::invalid("items.{$index}.checkout", 'phải sau checkin');
            }
            $adults = $item['adults'] ?? null;
            if (!is_int($adults) || $adults < 1 || $adults > 20) {
                self::invalid("items.{$index}.adults", 'phải là số nguyên từ 1 đến 20');
            }
            $ages = $item['child_ages'] ?? [];
            if (!is_array($ages) || !array_is_list($ages) || count($ages) > 10) {
                self::invalid("items.{$index}.child_ages", 'phải là danh sách tối đa 10 tuổi');
            }
            $cleanAges = [];
            foreach ($ages as $j => $age) {
                if (!is_int($age) || $age < 0 || $age > 17) {
                    self::invalid("items.{$index}.child_ages.{$j}", 'tuổi phải trong khoảng 0–17');
                }
                $cleanAges[] = $age;
            }
            $rooms = $item['user_rooms'] ?? 0;
            if (!is_int($rooms) || $rooms < 0 || $rooms > 10) {
                self::invalid("items.{$index}.user_rooms", 'phải là số nguyên từ 0 đến 10');
            }
            $items[] = [
                'room_id' => $roomId,
                'booking_type' => $type,
                'checkin' => $item['checkin'],
                'checkout' => $item['checkout'],
                'adults' => $adults,
                'child_ages' => $cleanAges,
                'user_rooms' => $rooms,
            ];
        }
        return $items;
    }

    /** @throws BookingQuoteException */
    private static function normalizeImageUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        $site  = parse_url(function_exists('home_url') ? home_url('/') : 'https://localhost/');
        if (!is_array($parts) || !is_array($site)) {
            self::invalid('image_url', 'chỉ chấp nhận ảnh HTTPS cùng tên miền hoặc đường dẫn tương đối');
        }
        $urlPort  = (int) ($parts['port'] ?? 443);
        $sitePort = (int) ($site['port'] ?? (($site['scheme'] ?? '') === 'https' ? 443 : 80));
        if (
            strtolower((string) ($site['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($site['host'] ?? ''))
            || $urlPort !== $sitePort
            || isset($parts['user']) || isset($parts['pass'])) {
            self::invalid('image_url', 'chỉ chấp nhận ảnh HTTPS cùng tên miền hoặc đường dẫn tương đối');
        }
        return $url;
    }

    /** @throws BookingQuoteException */
    private static function normalizeLocalDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            self::invalid('valid_until', 'phải là ngày giờ hợp lệ');
        }
        $normalized = str_replace('T', ' ', trim($value));
        if (strlen($normalized) === 16) {
            $normalized .= ':00';
        }
        $date = self::localDateTime($normalized);
        if ($date === null) {
            self::invalid('valid_until', 'không đúng định dạng ngày giờ địa phương');
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function localDateTime(string $value): ?\DateTimeImmutable
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(date_default_timezone_get());
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false && !(is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0));
    }

    private static function isEmail(string $value): bool
    {
        return function_exists('is_email') ? is_email($value) !== false : filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function plainSingleLine(string $value): string
    {
        $value = function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value));
        return trim(preg_replace('/[\r\n\t]+/u', ' ', $value) ?? $value);
    }

    private static function plainMultiline(string $value): string
    {
        if (function_exists('sanitize_textarea_field')) {
            return trim(sanitize_textarea_field($value));
        }
        return trim(strip_tags(str_replace(["\r\n", "\r"], "\n", $value)));
    }

    /** @throws BookingQuoteException */
    private static function invalid(string $field, string $message): never
    {
        throw new BookingQuoteException("{$field} {$message}", 'validation_error', 422, $field);
    }
}
