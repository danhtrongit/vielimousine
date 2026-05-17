<?php
declare(strict_types=1);

namespace Vie\Repository;

final class OrderItemRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_order_item';
    }

    protected function fillable(): array
    {
        return [
            'order_id', 'hotel_id', 'room_id', 'name',
            'booking_type', 'unit_label', 'quantity',
            'checkin', 'checkout', 'nights', 'adults', 'children', 'child_ages',
            'room_subtotal', 'extra_adult_total', 'child_surcharge_total',
            'ticket_count', 'ticket_subtotal',
            'line_discount', 'line_total', 'cost_total', 'profit_total',
            'partner_name', 'hotel_area', 'supplier_booking_code',
            'pricing_snapshot',
            'status', 'cancelled_at', 'cancel_reason',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'                     => 'int',
            'order_id'               => 'int',
            'hotel_id'               => 'int',
            'room_id'                => 'int',
            'quantity'               => 'int',
            'nights'                 => 'int',
            'adults'                 => 'int',
            'children'               => 'int',
            'child_ages'             => 'json',
            'room_subtotal'          => 'float',
            'extra_adult_total'      => 'float',
            'child_surcharge_total'  => 'float',
            'ticket_count'           => 'int',
            'ticket_subtotal'        => 'float',
            'line_discount'          => 'float',
            'line_total'             => 'float',
            'cost_total'             => 'float',
            'profit_total'           => 'float',
            'pricing_snapshot'       => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function defaultSort(): array
    {
        return ['id' => 'ASC'];
    }

    public function availableSorts(): array
    {
        return ['id', 'checkin', 'line_total', 'created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'order_id' => ['type' => 'exact',     'column' => 'order_id'],
            'hotel_id' => ['type' => 'exact',     'column' => 'hotel_id'],
            'room_id'  => ['type' => 'exact',     'column' => 'room_id'],
            'status'   => ['type' => 'in',        'column' => 'status'],
            'checkin'  => ['type' => 'date_from', 'column' => 'checkin'],
            'q'        => ['type' => 'search'],
        ];
    }
}
