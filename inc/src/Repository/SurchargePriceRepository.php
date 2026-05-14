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
}
