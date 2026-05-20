<?php
declare(strict_types=1);

namespace Vie\Repository;

final class RoomPriceRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_room_price';
    }

    protected function fillable(): array
    {
        return [
            'room_id', 'date', 'price', 'extra_adult_price',
            'stock', 'is_active', 'source',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'                => 'int',
            'room_id'           => 'int',
            'price'             => 'float',
            'extra_adult_price' => 'float',
            'stock'             => 'int',
            'is_active'         => 'bool',
        ];
    }

    protected function searchableColumns(): array
    {
        return [];
    }

    protected function defaultSort(): array
    {
        return ['date' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['date', 'price', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'room_id'   => ['type' => 'exact',     'column' => 'room_id'],
            'is_active'  => ['type' => 'bool',      'column' => 'is_active'],
            'source'    => ['type' => 'exact',     'column' => 'source'],
            'date_from' => ['type' => 'date_from', 'column' => 'date'],
            'date_to'   => ['type' => 'date_to',   'column' => 'date'],
        ];
    }

    /**
     * Lấy giá phòng cho danh sách ngày cụ thể — không paginate, không clamp.
     * Dùng cho PriceCalculator để tránh bug silently drop khi quote nhiều đêm.
     *
     * @param int      $roomId
     * @param string[] $dates  YYYY-MM-DD
     * @return array<int,array<string,mixed>> raw rows (đã cast theo casts())
     */
    public function findByDateRange(int $roomId, array $dates): array
    {
        if ($dates === []) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . $this->tableName();
        $placeholders = implode(',', array_fill(0, count($dates), '%s'));
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE room_id = %d AND date IN ({$placeholders})
             ORDER BY date ASC",
            array_merge([$roomId], $dates)
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn(array $r) => $this->castRow($r), $rows);
    }

    /**
     * Lấy toàn bộ override room_price trong khoảng ngày — không paginate, không clamp.
     * Dùng cho PricingMatrixController: matrix cần thấy mọi override để render UI đúng.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAllInDateRange(string $dateFrom, string $dateTo): array
    {
        global $wpdb;
        $table = $wpdb->prefix . $this->tableName();
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE date BETWEEN %s AND %s
             ORDER BY date ASC, room_id ASC",
            $dateFrom,
            $dateTo
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn(array $r) => $this->castRow($r), $rows);
    }
}
