<?php
declare(strict_types=1);

namespace Vie\Repository;

final class CouponRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_coupon';
    }

    protected function fillable(): array
    {
        return [
            'code', 'description', 'type', 'value',
            'min_order', 'max_discount',
            'usage_limit', 'usage_limit_per_user', 'used_count',
            'valid_from', 'valid_to',
            'hotel_ids', 'room_ids', 'booking_types',
            'is_active', 'sales_only', 'created_by',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'                    => 'int',
            'value'                 => 'float',
            'min_order'             => 'float',
            'max_discount'          => 'float',
            'usage_limit'           => 'int',
            'usage_limit_per_user'  => 'int',
            'used_count'            => 'int',
            'hotel_ids'             => 'json',
            'room_ids'              => 'json',
            'booking_types'         => 'json',
            'is_active'             => 'bool',
            'sales_only'            => 'bool',
            'created_by'            => 'int',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['code', 'description'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'code', 'valid_from', 'valid_to'];
    }

    protected function filterConfig(): array
    {
        return [
            'is_active'  => ['type' => 'bool',  'column' => 'is_active'],
            'sales_only' => ['type' => 'bool',  'column' => 'sales_only'],
            'q'          => ['type' => 'search'],
        ];
    }

    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE code = %s LIMIT 1", $code),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    public function incrementUsed(int $id): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET used_count = used_count + 1 WHERE id = %d",
            $id
        ));
    }
}
