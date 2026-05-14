<?php
declare(strict_types=1);

namespace Vie\Repository;

final class HotelRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_hotel';
    }

    protected function fillable(): array
    {
        return [
            'post_id', 'name', 'slug', 'description', 'address', 'city',
            'contact_phone', 'contact_email', 'star_rating',
            'default_checkin', 'default_checkout', 'default_ticket_price',
            'ticket_free_children_count', 'ticket_free_children_max_age',
            'pricing_policy', 'cancellation_policy',
            'thumbnail_id', 'gallery', 'is_active', 'sort_order',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'                         => 'int',
            'post_id'                    => 'int',
            'star_rating'                => 'int',
            'is_active'                  => 'bool',
            'sort_order'                 => 'int',
            'default_ticket_price'       => 'float',
            'ticket_free_children_count' => 'int',
            'ticket_free_children_max_age' => 'int',
            'thumbnail_id'               => 'int',
            'pricing_policy'             => 'json',
            'cancellation_policy'        => 'json',
            'gallery'                    => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name', 'slug', 'city', 'address'];
    }

    protected function defaultSort(): array
    {
        return ['sort_order' => 'ASC', 'id' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['sort_order', 'name', 'created_at', 'star_rating'];
    }

    protected function filterConfig(): array
    {
        return [
            'is_active'       => ['type' => 'bool',      'column' => 'is_active'],
            'city'            => ['type' => 'in',         'column' => 'city'],
            'star_rating_min' => ['type' => 'range_min',  'column' => 'star_rating'],
            'q'               => ['type' => 'search'],
        ];
    }

    public function create(array $data): array
    {
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        return parent::create($data);
    }
}
