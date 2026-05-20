<?php
declare(strict_types=1);

namespace Vie\Schema\Seeders;

/**
 * Phase 12 — Seed 10k orders + 30k items + 50k payments cho perf test.
 * Idempotent: clear theo `internal_note LIKE 'perf:%'` trước khi insert.
 */
final class PerfSeeder
{
    public const ORDER_COUNT   = 10_000;
    public const ITEMS_PER_ORDER = 3;
    public const PAYMENT_COUNT = 50_000;
    public const BATCH         = 500;
    public const TAG_PREFIX    = 'perf:';

    /** @return array{orders:int,items:int,payments:int} */
    public static function run(\wpdb $wpdb): array
    {
        self::clear($wpdb);

        // Đảm bảo 1 hotel + 3 rooms tồn tại
        HotelSeeder::run($wpdb);
        RoomSeeder::run($wpdb);

        $hotelId = (int) $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}vie_hotel ORDER BY id ASC LIMIT 1"
        );
        if ($hotelId === 0) {
            throw new \RuntimeException('PerfSeeder: cần ít nhất 1 hotel');
        }

        $roomIds = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}vie_room WHERE hotel_id = %d ORDER BY id ASC",
            $hotelId
        )));
        if ($roomIds === []) {
            throw new \RuntimeException('PerfSeeder: cần ít nhất 1 room thuộc hotel');
        }

        $customerId = self::ensureCustomer($wpdb);

        $orderIds = self::seedOrders($wpdb, $customerId, $hotelId, self::ORDER_COUNT);
        $items    = self::seedItems($wpdb, $orderIds, $roomIds, $hotelId);
        $payments = self::seedPayments($wpdb, $orderIds, self::PAYMENT_COUNT);

        return [
            'orders'   => count($orderIds),
            'items'    => $items,
            'payments' => $payments,
        ];
    }

    public static function clear(\wpdb $wpdb): void
    {
        $orderTable   = $wpdb->prefix . 'vie_order';
        $itemTable    = $wpdb->prefix . 'vie_order_item';
        $paymentTable = $wpdb->prefix . 'vie_payment_log';

        $wpdb->query(
            "DELETE p FROM {$paymentTable} p
             JOIN {$orderTable} o ON p.order_id = o.id
             WHERE o.internal_note LIKE 'perf:%'"
        );
        $wpdb->query(
            "DELETE i FROM {$itemTable} i
             JOIN {$orderTable} o ON i.order_id = o.id
             WHERE o.internal_note LIKE 'perf:%'"
        );
        $wpdb->query(
            "DELETE FROM {$orderTable} WHERE internal_note LIKE 'perf:%'"
        );
    }

    private static function ensureCustomer(\wpdb $wpdb): int
    {
        $table = $wpdb->prefix . 'vie_customer';
        $existing = (int) $wpdb->get_var(
            "SELECT id FROM {$table} WHERE phone = '0900000000' LIMIT 1"
        );
        if ($existing > 0) {
            return $existing;
        }
        $wpdb->insert($table, [
            'phone'      => '0900000000',
            'name'       => 'Perf Test Customer',
            'email'      => 'perf@test.local',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** @return int[] list of inserted order IDs */
    private static function seedOrders(\wpdb $wpdb, int $customerId, int $hotelId, int $count): array
    {
        $orderTable = $wpdb->prefix . 'vie_order';
        $statuses   = ['pending', 'confirmed', 'paid', 'cancelled'];
        $sources    = ['website', 'admin', 'manual'];
        $paymentSt  = ['pending', 'partial', 'paid'];

        $cols = [
            'code','customer_id','customer_phone','customer_name','customer_email',
            'source','checkin','checkout','nights','adults','children',
            'subtotal','discount','tax','total','cost_total','profit_total',
            'currency','payment_status','paid_amount','status','internal_note',
            'created_at','updated_at',
        ];
        $colList = '`' . implode('`,`', $cols) . '`';

        $orderIds = [];
        $now = time();
        $batchSize = self::BATCH;

        for ($offset = 0; $offset < $count; $offset += $batchSize) {
            $rows = [];
            $params = [];
            $size = min($batchSize, $count - $offset);
            for ($i = 0; $i < $size; $i++) {
                $idx     = $offset + $i;
                $status  = $statuses[$idx % 4];
                $source  = $sources[$idx % 3];
                $payStat = $paymentSt[$idx % 3];
                $checkin = gmdate('Y-m-d', $now + ($idx % 90 - 30) * DAY_IN_SECONDS);
                $checkout = gmdate('Y-m-d', strtotime("{$checkin} +2 day"));
                $code    = sprintf('PERF-%07d-%05d', $offset + $i, mt_rand(0, 99999));
                $total   = 1_000_000 + ($idx % 50) * 100_000;
                $paid    = $payStat === 'paid' ? $total : ($payStat === 'partial' ? (int) ($total / 2) : 0);
                $cost    = (int) ($total * 0.7);
                $profit  = $total - $cost;
                $createdAt = gmdate('Y-m-d H:i:s', $now - ($idx % 365) * DAY_IN_SECONDS);

                $rows[] = '(' . implode(',', array_fill(0, count($cols), '%s')) . ')';
                array_push($params,
                    $code, $customerId, '0900000000', 'Perf Test Customer', 'perf@test.local',
                    $source, $checkin, $checkout, 2, 2, 0,
                    $total, 0, 0, $total, $cost, $profit,
                    'VND', $payStat, $paid, $status, self::TAG_PREFIX . $idx,
                    $createdAt, $createdAt,
                );
            }

            $sql = "INSERT INTO {$orderTable} ({$colList}) VALUES " . implode(',', $rows);
            $wpdb->query($wpdb->prepare($sql, $params));

            $firstId = (int) $wpdb->insert_id;
            for ($i = 0; $i < $size; $i++) {
                $orderIds[] = $firstId + $i;
            }
        }

        return $orderIds;
    }

    /** @param int[] $orderIds @param int[] $roomIds */
    private static function seedItems(\wpdb $wpdb, array $orderIds, array $roomIds, int $hotelId): int
    {
        $itemTable = $wpdb->prefix . 'vie_order_item';
        $bookingTypes = ['room', 'combo'];
        $totalItems = 0;

        $cols = [
            'order_id','hotel_id','room_id','name','booking_type','unit_label','quantity',
            'checkin','checkout','nights','adults','children',
            'room_subtotal','extra_adult_total','child_surcharge_total',
            'ticket_count','ticket_subtotal','line_discount','line_total',
            'cost_total','profit_total','pricing_snapshot','status',
            'created_at','updated_at',
        ];
        $colList = '`' . implode('`,`', $cols) . '`';
        $now = current_time('mysql');
        $snapshot = '{}';

        // Build flat list of items to insert (3 items × len(orderIds))
        $allRows = [];
        foreach ($orderIds as $idx => $orderId) {
            for ($k = 0; $k < self::ITEMS_PER_ORDER; $k++) {
                $allRows[] = [
                    'order_id'   => $orderId,
                    'room_id'    => $roomIds[($idx + $k) % count($roomIds)],
                    'bookingType'=> $bookingTypes[$k % 2],
                ];
            }
        }
        $totalItems = count($allRows);

        for ($offset = 0; $offset < $totalItems; $offset += self::BATCH) {
            $batch = array_slice($allRows, $offset, self::BATCH);
            $rows = [];
            $params = [];
            foreach ($batch as $i => $r) {
                $isCombo = $r['bookingType'] === 'combo';
                $lineTotal = 1_000_000;
                $cost = (int) ($lineTotal * 0.7);
                $profit = $lineTotal - $cost;

                $rows[] = '(' . implode(',', array_fill(0, count($cols), '%s')) . ')';
                array_push($params,
                    $r['order_id'], $hotelId, $r['room_id'], 'Perf Room', $r['bookingType'], 'phòng', 1,
                    gmdate('Y-m-d'), gmdate('Y-m-d', strtotime('+2 day')), 2, 2, 0,
                    800_000, 0, 0,
                    $isCombo ? 3 : 0, $isCombo ? 200_000 : 0, 0, $lineTotal,
                    $cost, $profit, $snapshot, 'active',
                    $now, $now,
                );
            }
            $sql = "INSERT INTO {$itemTable} ({$colList}) VALUES " . implode(',', $rows);
            $wpdb->query($wpdb->prepare($sql, $params));
        }

        return $totalItems;
    }

    /** @param int[] $orderIds */
    private static function seedPayments(\wpdb $wpdb, array $orderIds, int $count): int
    {
        if ($orderIds === []) return 0;
        $paymentTable = $wpdb->prefix . 'vie_payment_log';

        $types    = ['deposit', 'payment', 'refund', 'void'];
        $methods  = ['bank_transfer', 'sepay', 'cash', 'manual'];
        $gateways = ['sepay', 'manual', null];

        $cols = ['order_id','type','amount','method','gateway','transaction_id','note','created_at'];
        $colList = '`' . implode('`,`', $cols) . '`';
        $orderCount = count($orderIds);

        for ($offset = 0; $offset < $count; $offset += self::BATCH) {
            $size = min(self::BATCH, $count - $offset);
            $rows = [];
            $params = [];
            for ($i = 0; $i < $size; $i++) {
                $idx     = $offset + $i;
                $orderId = $orderIds[$idx % $orderCount];
                $type    = $types[$idx % 4];
                $method  = $methods[$idx % 4];
                $gateway = $gateways[$idx % 3];
                $amount  = 500_000 + ($idx % 50) * 10_000;
                $txnId   = sprintf('PERF-TXN-%08d', $idx);
                $created = gmdate('Y-m-d H:i:s', time() - ($idx % 365) * DAY_IN_SECONDS);

                $rows[] = '(' . implode(',', array_fill(0, count($cols), '%s')) . ')';
                array_push($params,
                    $orderId, $type, $amount, $method, $gateway, $txnId, 'perf seed', $created
                );
            }
            $sql = "INSERT INTO {$paymentTable} ({$colList}) VALUES " . implode(',', $rows);
            $wpdb->query($wpdb->prepare($sql, $params));
        }

        return $count;
    }
}
