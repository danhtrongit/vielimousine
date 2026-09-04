<?php
declare(strict_types=1);

namespace Vie\Service\Coupon;

use Vie\Repository\ActivityLogRepository;
use Vie\Repository\CouponRepository;

final class CouponBulkService
{
    /** Trần số mã mỗi lần gọi — giữ request ngắn, client chạy nhiều lần nếu cần. */
    public const MAX_QUANTITY = 500;

    /** Số row mỗi lệnh INSERT — giữ packet nhỏ, tránh max_allowed_packet. */
    private const INSERT_CHUNK = 200;

    /** Số vòng sinh lại khi mã vừa sinh đã tồn tại trong DB. */
    private const MAX_ROUNDS = 8;

    /**
     * Thứ tự cột của lệnh INSERT hàng loạt. `code` PHẢI đứng đầu — buildRowTemplate()
     * dựa vào đó để xếp param mỗi row là [code, ...param dùng chung].
     */
    private const COLUMNS = [
        'code', 'description', 'type', 'value',
        'min_order', 'max_discount',
        'usage_limit', 'usage_limit_per_user',
        'valid_from', 'valid_to',
        'hotel_ids', 'room_ids', 'booking_types',
        'is_active', 'sales_only', 'created_by',
        'created_at', 'updated_at',
    ];

    public function __construct(
        private readonly CouponRepository $couponRepo,
        private readonly CouponCodeGenerator $generator,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    /**
     * Sinh N mã giảm giá cùng cấu hình (template) trong 1 transaction.
     *
     * Toàn bộ batch là all-or-nothing: nếu có mã trùng do race giữa lúc kiểm tra
     * và lúc INSERT, unique key `uniq_code` sẽ chặn và cả batch rollback — client
     * gọi lại là an toàn vì chưa mã nào được ghi.
     *
     * @return array{created_count: int, requested_count: int, coupons: array<int,array<string,mixed>>}
     */
    public function generateBatch(array $payload, int $actorUserId): array
    {
        $quantity = (int) ($payload['quantity'] ?? 0);
        $prefix   = CouponCodeGenerator::normalizeAffix($payload['prefix'] ?? '');
        $suffix   = CouponCodeGenerator::normalizeAffix($payload['suffix'] ?? '');
        $length   = (int) ($payload['random_length'] ?? 8);
        $template = is_array($payload['template'] ?? null) ? $payload['template'] : [];

        $codes  = $this->reserveCodes($quantity, $prefix, $suffix, $length);
        $shared = $this->buildSharedColumns($template, $actorUserId);

        $this->insertBatch($codes, $shared);

        $created = $this->couponRepo->findManyByCodes($codes);

        $this->activityRepo->create([
            'actor_user_id' => $actorUserId,
            'entity_type'   => 'coupon_bulk',
            'entity_id'     => 0,
            'action'        => 'bulk_generate',
            'before_json'   => null,
            'after_json'    => [
                'quantity'      => $quantity,
                'prefix'        => $prefix,
                'suffix'        => $suffix,
                'random_length' => $length,
                'template'      => $template,
                'codes'         => $codes,
            ],
        ]);

        return [
            'created_count'   => count($created),
            'requested_count' => $quantity,
            'coupons'         => $created,
        ];
    }

    /**
     * Sinh đủ $quantity mã chưa tồn tại trong DB. Mỗi vòng chỉ tốn 1 query
     * kiểm tra trùng cho cả lô ứng viên.
     *
     * @return string[]
     */
    private function reserveCodes(int $quantity, string $prefix, string $suffix, int $length): array
    {
        $codes = [];
        $seen  = [];

        for ($round = 0; $round < self::MAX_ROUNDS && count($codes) < $quantity; $round++) {
            $candidates = $this->generator->generate(
                $quantity - count($codes),
                $prefix,
                $suffix,
                $length,
                $seen
            );
            if ($candidates === []) {
                break;
            }

            foreach ($candidates as $code) {
                $seen[$code] = true;
            }

            $existing = $this->couponRepo->existingCodes($candidates);
            foreach ($candidates as $code) {
                if (!isset($existing[$code])) {
                    $codes[] = $code;
                }
            }
        }

        if (count($codes) < $quantity) {
            throw new CouponBulkException(sprintf(
                'Chỉ sinh được %d/%d mã duy nhất — tăng độ dài phần ngẫu nhiên hoặc đổi tiền tố.',
                count($codes),
                $quantity
            ));
        }

        return $codes;
    }

    /**
     * Giá trị cột dùng chung cho mọi mã trong batch. Giá trị `null` sẽ được ghi
     * thành NULL literal (không đi qua placeholder) — %d/%s của wpdb::prepare
     * biến null thành 0/'' nên không dùng được cho cột nullable.
     *
     * @return array<string,?string>
     */
    private function buildSharedColumns(array $template, int $actorUserId): array
    {
        $now = current_time('mysql');

        return [
            'description'          => self::nullableString($template['description'] ?? null),
            'type'                 => (string) ($template['type'] ?? 'percentage'),
            'value'                => (string) (float) ($template['value'] ?? 0),
            'min_order'            => (string) (int) ($template['min_order'] ?? 0),
            'max_discount'         => self::nullableInt($template['max_discount'] ?? null),
            'usage_limit'          => self::nullableInt($template['usage_limit'] ?? null),
            'usage_limit_per_user' => self::nullableInt($template['usage_limit_per_user'] ?? null),
            'valid_from'           => self::nullableString($template['valid_from'] ?? null),
            'valid_to'             => self::nullableString($template['valid_to'] ?? null),
            'hotel_ids'            => self::nullableIdJson($template['hotel_ids'] ?? null),
            'room_ids'             => self::nullableIdJson($template['room_ids'] ?? null),
            'booking_types'        => self::nullableStringJson($template['booking_types'] ?? null),
            'is_active'            => array_key_exists('is_active', $template)
                ? (((bool) $template['is_active']) ? '1' : '0')
                : '1',
            'sales_only'           => array_key_exists('sales_only', $template)
                ? (((bool) $template['sales_only']) ? '1' : '0')
                : '0',
            'created_by'           => $actorUserId > 0 ? (string) $actorUserId : null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /**
     * @param string[]              $codes
     * @param array<string,?string> $shared
     */
    private function insertBatch(array $codes, array $shared): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_coupon';

        // Template row + param dùng chung dựng 1 lần: mọi row chỉ khác cột `code`.
        $slots        = [];
        $sharedParams = [];
        foreach (self::COLUMNS as $column) {
            if ($column === 'code') {
                $slots[] = '%s';
                continue;
            }
            $value = $shared[$column] ?? null;
            if ($value === null) {
                $slots[] = 'NULL';
                continue;
            }
            $slots[]        = '%s';
            $sharedParams[] = $value;
        }

        $rowTemplate = '(' . implode(', ', $slots) . ')';
        $columnList  = implode(', ', self::COLUMNS);

        $wpdb->query('START TRANSACTION');
        try {
            foreach (array_chunk($codes, self::INSERT_CHUNK) as $chunk) {
                $params = [];
                foreach ($chunk as $code) {
                    $params[] = $code;
                    foreach ($sharedParams as $param) {
                        $params[] = $param;
                    }
                }

                $sql = "INSERT INTO {$table} ({$columnList}) VALUES "
                    . implode(', ', array_fill(0, count($chunk), $rowTemplate));

                if ($wpdb->query($wpdb->prepare($sql, ...$params)) === false) {
                    throw new CouponBulkException('Tạo mã hàng loạt thất bại: ' . $wpdb->last_error);
                }
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    private static function nullableInt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) (int) $value;
    }

    private static function nullableIdJson(mixed $value): ?string
    {
        if (!is_array($value) || $value === []) {
            return null;
        }
        return (string) wp_json_encode(array_values(array_map('intval', $value)));
    }

    private static function nullableStringJson(mixed $value): ?string
    {
        if (!is_array($value) || $value === []) {
            return null;
        }
        return (string) wp_json_encode(array_values(array_map('strval', $value)));
    }
}
