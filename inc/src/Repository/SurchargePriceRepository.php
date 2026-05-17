<?php
declare(strict_types=1);

namespace Vie\Repository;

final class SurchargePriceRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_surcharge_price';
    }

    protected function fillable(): array
    {
        return [
            'surcharge_id', 'date', 'amount', 'is_active',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'           => 'int',
            'surcharge_id' => 'int',
            'amount'       => 'float',
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
        return ['date', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'surcharge_id' => ['type' => 'exact',     'column' => 'surcharge_id'],
            'is_active'    => ['type' => 'bool',      'column' => 'is_active'],
            'date_from'    => ['type' => 'date_from', 'column' => 'date'],
            'date_to'      => ['type' => 'date_to',   'column' => 'date'],
        ];
    }

    /**
     * Lấy override surcharge cho tập surcharge_ids cụ thể trong khoảng ngày —
     * không paginate. Tránh bug clamp 100 khi quote rule × nights vượt page.
     *
     * @param int[]    $surchargeIds
     * @param string[] $dates  YYYY-MM-DD (đã sort tăng dần, hoặc bất kỳ)
     * @return array<int,array<string,mixed>>
     */
    public function findOverridesByDateRange(array $surchargeIds, array $dates): array
    {
        if ($surchargeIds === [] || $dates === []) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . $this->tableName();
        $idPh   = implode(',', array_fill(0, count($surchargeIds), '%d'));
        $datePh = implode(',', array_fill(0, count($dates), '%s'));
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE is_active = 1
               AND surcharge_id IN ({$idPh})
               AND date IN ({$datePh})",
            array_merge($surchargeIds, $dates)
        );
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return array_map(fn(array $r) => $this->castRow($r), $rows);
    }
}
