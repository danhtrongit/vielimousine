<?php
declare(strict_types=1);

namespace Vie\Repository;

final class SurchargeRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_surcharge';
    }

    protected function fillable(): array
    {
        return [
            'room_id', 'guest_type', 'label',
            'age_from', 'age_to',
            'child_index_min', 'child_index_max',
            'amount',
            'is_free', 'sort_order', 'is_active',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'              => 'int',
            'room_id'         => 'int',
            'age_from'        => 'int',
            'age_to'          => 'int',
            'child_index_min' => 'int',
            'child_index_max' => 'int',
            'amount'          => 'float',
            'is_free'         => 'bool',
            'sort_order'      => 'int',
            'is_active'       => 'bool',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['label'];
    }

    protected function defaultSort(): array
    {
        return ['sort_order' => 'ASC', 'id' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['sort_order', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'room_id'    => ['type' => 'exact', 'column' => 'room_id'],
            'guest_type' => ['type' => 'exact', 'column' => 'guest_type'],
            'is_active'  => ['type' => 'bool',  'column' => 'is_active'],
            'q'          => ['type' => 'search'],
        ];
    }
}
