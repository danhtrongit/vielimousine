<?php
declare(strict_types=1);

namespace Vie\Repository;

final class ProductCodeRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_product_code';
    }

    protected function fillable(): array
    {
        return [
            'code', 'hotel_id', 'room_id', 'booking_type',
            'weekday_pattern', 'display_name', 'unit_label', 'is_active',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'       => 'int',
            'hotel_id' => 'int',
            'room_id'  => 'int',
            'is_active' => 'bool',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['code', 'display_name'];
    }

    protected function defaultSort(): array
    {
        return ['id' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['code', 'display_name', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'hotel_id'     => ['type' => 'exact', 'column' => 'hotel_id'],
            'room_id'      => ['type' => 'exact', 'column' => 'room_id'],
            'booking_type' => ['type' => 'exact', 'column' => 'booking_type'],
            'is_active'    => ['type' => 'bool',  'column' => 'is_active'],
            'q'            => ['type' => 'search'],
        ];
    }
}
