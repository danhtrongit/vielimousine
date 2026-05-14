<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class HotelValidation
{
    public static function createRules(): array
    {
        return [
            'post_id'                      => 'required|int|unique:vie_hotel,post_id',
            'name'                         => 'required|string|max:255',
            'slug'                         => 'nullable|string|max:255|unique:vie_hotel,slug',
            'description'                  => 'nullable|string',
            'address'                      => 'nullable|string|max:500',
            'city'                         => 'nullable|string|max:100',
            'contact_phone'                => 'nullable|phone',
            'contact_email'                => 'nullable|email',
            'star_rating'                  => 'nullable|int|min:1|max:5',
            'default_checkin'              => 'nullable|string|max:5',
            'default_checkout'             => 'nullable|string|max:5',
            'default_ticket_price'         => 'nullable|float|min:0',
            'ticket_free_children_count'   => 'nullable|int|min:0',
            'ticket_free_children_max_age' => 'nullable|int|min:0',
            'pricing_policy'               => 'nullable|array',
            'cancellation_policy'          => 'nullable|array',
            'thumbnail_id'                 => 'nullable|int',
            'gallery'                      => 'nullable|array',
            'is_active'                    => 'nullable|bool',
            'sort_order'                   => 'nullable|int|min:0',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'post_id'                      => 'nullable|int|unique:vie_hotel,post_id,' . $id,
            'name'                         => 'nullable|string|max:255',
            'slug'                         => 'nullable|string|max:255|unique:vie_hotel,slug,' . $id,
            'description'                  => 'nullable|string',
            'address'                      => 'nullable|string|max:500',
            'city'                         => 'nullable|string|max:100',
            'contact_phone'                => 'nullable|phone',
            'contact_email'                => 'nullable|email',
            'star_rating'                  => 'nullable|int|min:1|max:5',
            'default_checkin'              => 'nullable|string|max:5',
            'default_checkout'             => 'nullable|string|max:5',
            'default_ticket_price'         => 'nullable|float|min:0',
            'ticket_free_children_count'   => 'nullable|int|min:0',
            'ticket_free_children_max_age' => 'nullable|int|min:0',
            'pricing_policy'               => 'nullable|array',
            'cancellation_policy'          => 'nullable|array',
            'thumbnail_id'                 => 'nullable|int',
            'gallery'                      => 'nullable|array',
            'is_active'                    => 'nullable|bool',
            'sort_order'                   => 'nullable|int|min:0',
        ];
    }
}
