<?php
declare(strict_types=1);

namespace Vie\Repository;

final class TicketPriceRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_ticket_price';
    }

    protected function fillable(): array
    {
        return [
            'hotel_id', 'route_id', 'date',
            'ticket_price', 'is_active',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'           => 'int',
            'hotel_id'     => 'int',
            'route_id'     => 'int',
            'ticket_price' => 'float',
            'is_active'    => 'bool',
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
        return ['date', 'ticket_price', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'hotel_id'  => ['type' => 'exact',     'column' => 'hotel_id'],
            'is_active' => ['type' => 'bool',      'column' => 'is_active'],
            'date_from' => ['type' => 'date_from', 'column' => 'date'],
            'date_to'   => ['type' => 'date_to',   'column' => 'date'],
        ];
    }

    /**
     * Lấy toàn bộ ticket_price trong khoảng ngày — không paginate, không clamp.
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
             ORDER BY date ASC, hotel_id ASC",
            $dateFrom,
            $dateTo
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn(array $r) => $this->castRow($r), $rows);
    }
}
