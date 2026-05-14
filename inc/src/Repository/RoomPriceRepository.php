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
}
