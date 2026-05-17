<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

final class PricingCellsService
{
    /**
     * Batch upsert mixed cell changes via INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * Each change: { kind, ... target keys ..., fields: { ... } }
     *   - kind=room_price:      room_id + date    + fields{price, stock, extra_adult_price, is_active, source}
     *   - kind=surcharge_price: surcharge_id+date + fields{amount, is_active}
     *   - kind=ticket_price:    hotel_id+date     + fields{ticket_price, route_id, is_active}
     *
     * @return array{ saved: int, errors: list<array{index:int, message:string}> }
     */
    public function save(array $changes): array
    {
        global $wpdb;

        $now    = current_time('mysql');
        $errors = [];
        $rooms  = [];  // [ [room_id, date, price, extra_adult, stock, is_active, source] ]
        $surs   = [];  // [ [surcharge_id, date, amount, is_active] ]
        $tix    = [];  // [ [hotel_id, route_id, date, ticket_price, is_active] ]

        foreach ($changes as $idx => $c) {
            $kind   = (string) ($c['kind'] ?? '');
            $date   = (string) ($c['date'] ?? '');
            $fields = is_array($c['fields'] ?? null) ? $c['fields'] : [];

            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = ['index' => $idx, 'message' => 'date invalid'];
                continue;
            }

            if ($kind === 'room_price') {
                $rid = (int) ($c['room_id'] ?? 0);
                if ($rid <= 0) { $errors[] = ['index' => $idx, 'message' => 'room_id required']; continue; }
                $rooms[] = [
                    'room_id' => $rid,
                    'date'    => $date,
                    'price'              => (int) ($fields['price']             ?? 0),
                    'extra_adult_price'  => (int) ($fields['extra_adult_price'] ?? 0),
                    'stock'              => (int) ($fields['stock']             ?? 0),
                    'is_active'          => isset($fields['is_active']) ? (int) (bool) $fields['is_active'] : 1,
                    'source'             => (string) ($fields['source'] ?? 'manual'),
                    'fields'             => $fields,
                ];
            } elseif ($kind === 'surcharge_price') {
                $sid = (int) ($c['surcharge_id'] ?? 0);
                if ($sid <= 0) { $errors[] = ['index' => $idx, 'message' => 'surcharge_id required']; continue; }
                $surs[] = [
                    'surcharge_id' => $sid,
                    'date'         => $date,
                    'amount'       => (int) ($fields['amount']    ?? 0),
                    'is_active'    => isset($fields['is_active']) ? (int) (bool) $fields['is_active'] : 1,
                ];
            } elseif ($kind === 'ticket_price') {
                $hid = (int) ($c['hotel_id'] ?? 0);
                if ($hid <= 0) { $errors[] = ['index' => $idx, 'message' => 'hotel_id required']; continue; }
                $tix[] = [
                    'hotel_id'     => $hid,
                    'route_id'     => (int) ($fields['route_id']     ?? 0),
                    'date'         => $date,
                    'ticket_price' => (int) ($fields['ticket_price'] ?? 0),
                    'is_active'    => isset($fields['is_active']) ? (int) (bool) $fields['is_active'] : 1,
                ];
            } else {
                $errors[] = ['index' => $idx, 'message' => 'unknown kind: ' . $kind];
            }
        }

        $saved = 0;
        $wpdb->query('START TRANSACTION');
        try {
            $saved += $this->upsertRooms($wpdb, $rooms, $now);
            $saved += $this->upsertSurcharges($wpdb, $surs, $now);
            $saved += $this->upsertTickets($wpdb, $tix, $now);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    private function upsertRooms(\wpdb $wpdb, array $rows, string $now): int
    {
        if ($rows === []) return 0;
        $table = $wpdb->prefix . 'vie_room_price';
        $placeholders = [];
        $params = [];
        foreach ($rows as $r) {
            $placeholders[] = '(%d, %s, %d, %d, %d, %d, %s, %s, %s)';
            array_push(
                $params,
                $r['room_id'], $r['date'],
                $r['price'], $r['extra_adult_price'], $r['stock'], $r['is_active'], $r['source'],
                $now, $now
            );
        }
        $sql = "INSERT INTO {$table}
                (room_id, date, price, extra_adult_price, stock, is_active, source, created_at, updated_at)
                VALUES " . implode(',', $placeholders) . "
                ON DUPLICATE KEY UPDATE
                    price = VALUES(price),
                    extra_adult_price = VALUES(extra_adult_price),
                    stock = VALUES(stock),
                    is_active = VALUES(is_active),
                    source = VALUES(source),
                    updated_at = VALUES(updated_at)";
        $prepared = $wpdb->prepare($sql, ...$params);
        $result   = $wpdb->query($prepared);
        if ($result === false) {
            throw new \RuntimeException('SQL upsert failed: ' . $wpdb->last_error);
        }
        return count($rows);
    }

    private function upsertSurcharges(\wpdb $wpdb, array $rows, string $now): int
    {
        if ($rows === []) return 0;
        $table = $wpdb->prefix . 'vie_surcharge_price';
        $placeholders = [];
        $params = [];
        foreach ($rows as $r) {
            $placeholders[] = '(%d, %s, %d, %d, %s, %s)';
            array_push($params, $r['surcharge_id'], $r['date'], $r['amount'], $r['is_active'], $now, $now);
        }
        $sql = "INSERT INTO {$table}
                (surcharge_id, date, amount, is_active, created_at, updated_at)
                VALUES " . implode(',', $placeholders) . "
                ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount),
                    is_active = VALUES(is_active),
                    updated_at = VALUES(updated_at)";
        $prepared = $wpdb->prepare($sql, ...$params);
        $result   = $wpdb->query($prepared);
        if ($result === false) {
            throw new \RuntimeException('SQL upsert failed: ' . $wpdb->last_error);
        }
        return count($rows);
    }

    private function upsertTickets(\wpdb $wpdb, array $rows, string $now): int
    {
        if ($rows === []) return 0;
        $table = $wpdb->prefix . 'vie_ticket_price';
        $placeholders = [];
        $params = [];
        foreach ($rows as $r) {
            $placeholders[] = '(%d, %d, %s, %d, %d, %s, %s)';
            array_push($params, $r['hotel_id'], $r['route_id'], $r['date'], $r['ticket_price'], $r['is_active'], $now, $now);
        }
        $sql = "INSERT INTO {$table}
                (hotel_id, route_id, date, ticket_price, is_active, created_at, updated_at)
                VALUES " . implode(',', $placeholders) . "
                ON DUPLICATE KEY UPDATE
                    ticket_price = VALUES(ticket_price),
                    is_active = VALUES(is_active),
                    updated_at = VALUES(updated_at)";
        $prepared = $wpdb->prepare($sql, ...$params);
        $result   = $wpdb->query($prepared);
        if ($result === false) {
            throw new \RuntimeException('SQL upsert failed: ' . $wpdb->last_error);
        }
        return count($rows);
    }
}
