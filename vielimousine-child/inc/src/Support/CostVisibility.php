<?php
declare(strict_types=1);

namespace Vie\Support;

/**
 * Controls visibility of financial fields (cost_total / profit_total) at the
 * REST boundary. Authorized = current user holds the vie_view_reports capability.
 * Strip helpers are no-ops for authorized users.
 */
final class CostVisibility
{
    /** @var string[] */
    public const FIELDS = ['cost_total', 'profit_total'];

    public static function canView(): bool
    {
        return current_user_can('vie_view_reports');
    }

    /** Strip cost/profit from one order row and its nested items[] (if any). */
    public static function stripOrder(array $order): array
    {
        if (self::canView()) {
            return $order;
        }
        foreach (self::FIELDS as $f) {
            unset($order[$f]);
        }
        if (isset($order['items']) && is_array($order['items'])) {
            $order['items'] = self::stripItemRows($order['items']);
        }
        return $order;
    }

    /** @param array<int,array> $orders */
    public static function stripOrders(array $orders): array
    {
        if (self::canView()) {
            return $orders;
        }
        return array_map([self::class, 'stripOrder'], $orders);
    }

    public static function stripItemRow(array $row): array
    {
        if (self::canView()) {
            return $row;
        }
        foreach (self::FIELDS as $f) {
            unset($row[$f]);
        }
        return $row;
    }

    /** @param array<int,array> $rows */
    public static function stripItemRows(array $rows): array
    {
        if (self::canView()) {
            return $rows;
        }
        return array_map([self::class, 'stripItemRow'], $rows);
    }

    /** Remove cost/profit from a validated write payload unless authorized. */
    public static function stripWritable(array $data): array
    {
        if (self::canView()) {
            return $data;
        }
        foreach (self::FIELDS as $f) {
            unset($data[$f]);
        }
        return $data;
    }
}
