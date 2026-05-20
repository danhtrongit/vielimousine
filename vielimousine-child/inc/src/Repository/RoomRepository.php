<?php
declare(strict_types=1);

namespace Vie\Repository;

final class RoomRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_room';
    }

    protected function fillable(): array
    {
        return [
            'hotel_id', 'name', 'description',
            'included_adults', 'max_adults', 'max_children',
            'base_price', 'extra_adult_price',
            'free_children_count', 'free_children_max_age',
            'area', 'bed_type', 'bed_count', 'view', 'floor',
            'amenities', 'thumbnail_id', 'gallery',
            'is_active', 'sort_order',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'                    => 'int',
            'hotel_id'              => 'int',
            'included_adults'       => 'int',
            'max_adults'            => 'int',
            'max_children'          => 'int',
            'base_price'            => 'float',
            'extra_adult_price'     => 'float',
            'free_children_count'   => 'int',
            'free_children_max_age' => 'int',
            'area'                  => 'int',
            'bed_count'             => 'int',
            'thumbnail_id'          => 'int',
            'amenities'             => 'json',
            'gallery'               => 'json',
            'is_active'             => 'bool',
            'sort_order'            => 'int',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function defaultSort(): array
    {
        return ['sort_order' => 'ASC', 'id' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['sort_order', 'name', 'created_at', 'base_price'];
    }

    protected function filterConfig(): array
    {
        return [
            'hotel_id'   => ['type' => 'exact', 'column' => 'hotel_id'],
            'is_active'  => ['type' => 'bool',  'column' => 'is_active'],
            'min_adults' => ['type' => 'range_min', 'column' => 'included_adults'],
            'q'          => ['type' => 'search'],
        ];
    }
}
