<?php
declare(strict_types=1);

namespace Vie\Repository;

final class QuoteInquiryRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_quote_inquiry';
    }

    protected function fillable(): array
    {
        return [
            'room_id', 'hotel_id', 'booking_type',
            'checkin', 'checkout',
            'adults', 'children', 'child_ages', 'user_rooms',
            'customer_name', 'customer_phone', 'customer_email', 'note',
            'status', 'assigned_user_id', 'admin_note',
            'ip', 'user_agent',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'               => 'int',
            'room_id'          => 'int',
            'hotel_id'         => 'int',
            'adults'           => 'int',
            'children'         => 'int',
            'user_rooms'       => 'int',
            'assigned_user_id' => 'int',
            'child_ages'       => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['customer_name', 'customer_phone', 'customer_email'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'status'];
    }

    protected function filterConfig(): array
    {
        return [
            'status'           => ['type' => 'exact',     'column' => 'status'],
            'hotel_id'         => ['type' => 'exact',     'column' => 'hotel_id'],
            'room_id'          => ['type' => 'exact',     'column' => 'room_id'],
            'assigned_user_id' => ['type' => 'exact',     'column' => 'assigned_user_id'],
            'date_from'        => ['type' => 'date_from', 'column' => 'created_at'],
            'date_to'          => ['type' => 'date_to',   'column' => 'created_at'],
        ];
    }
}
