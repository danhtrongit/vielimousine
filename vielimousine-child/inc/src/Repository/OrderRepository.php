<?php
declare(strict_types=1);

namespace Vie\Repository;

final class OrderRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_order';
    }

    protected function fillable(): array
    {
        // SYSTEM-MANAGED FIELDS (KHÔNG được expose qua API validation rules):
        //   - 'code'             → sinh bởi OrderCodeGenerator
        //   - 'idempotency_key'  → từ header X-Idempotency-Key (OrderController::store)
        //   - 'paid_amount'      → cập nhật bởi PaymentLedger (không nhận từ client)
        //   - 'confirmed_at'     → set khi PaymentLedger auto-confirm
        //   - 'cancelled_at'     → set khi OrderService::cancel
        // Các fields này nằm trong fillable vì system code cần write được;
        // bảo vệ ở layer validation (OrderValidation / OrderCreateValidation KHÔNG list chúng).
        return [
            'code', 'idempotency_key', 'customer_id',
            'customer_phone', 'customer_name', 'customer_email', 'customer_vat',
            'sales_user_id', 'source',
            'checkin', 'checkout', 'nights', 'adults', 'children', 'child_ages',
            'customer_note', 'internal_note',
            'pickup', 'dropoff',
            'subtotal', 'discount', 'tax', 'total', 'cost_total', 'profit_total',
            'currency', 'coupon_id', 'coupon_code',
            'payment_status', 'paid_amount', 'paid_at',
            'partner_payment_status',
            'invoice_number', 'voucher_code',
            'checkin_code', 'checkin_code_sent_at',
            'status', 'confirmed_at', 'cancelled_at', 'cancel_reason', 'completed_at',
            'created_by', 'ip', 'user_agent',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'              => 'int',
            'customer_id'     => 'int',
            'sales_user_id'   => 'int',
            'nights'          => 'int',
            'adults'          => 'int',
            'children'        => 'int',
            'child_ages'      => 'json',
            'customer_vat'    => 'json',
            'pickup'          => 'json',
            'dropoff'         => 'json',
            'subtotal'        => 'float',
            'discount'        => 'float',
            'tax'             => 'float',
            'total'           => 'float',
            'cost_total'      => 'float',
            'profit_total'    => 'float',
            'hotel_subtotal'  => 'float',
            'ticket_subtotal' => 'float',
            'coupon_id'       => 'int',
            'paid_amount'     => 'float',
            'created_by'      => 'int',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['code', 'customer_phone', 'customer_name', 'customer_email'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'checkin', 'total', 'code', 'status'];
    }

    protected function filterConfig(): array
    {
        return [
            'status'         => ['type' => 'in',        'column' => 'status'],
            'payment_status' => ['type' => 'in',        'column' => 'payment_status'],
            'source'         => ['type' => 'in',        'column' => 'source'],
            'sales_user_id'  => ['type' => 'exact',     'column' => 'sales_user_id'],
            'customer_id'    => ['type' => 'exact',     'column' => 'customer_id'],
            'date_from'      => ['type' => 'date_from', 'column' => 'created_at'],
            'date_to'        => ['type' => 'date_to',   'column' => 'created_at'],
            'checkin_from'   => ['type' => 'date_from', 'column' => 'checkin'],
            'checkin_to'     => ['type' => 'date_to',   'column' => 'checkin'],
            'q'              => ['type' => 'search'],
            'id__in'         => ['type' => 'in',        'column' => 'id'],
        ];
    }

    public function all(array $params = []): array
    {
        $params = $this->applyUserScope($params);
        if (isset($params['__force_empty']) && $params['__force_empty'] === true) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => 1, 'per_page' => (int) ($params['per_page'] ?? 20),
                    'total' => 0, 'total_pages' => 0,
                    'has_next' => false, 'has_prev' => false,
                ],
                'sort' => ['field' => 'created_at', 'order' => 'desc'],
                'filters_applied' => [],
            ];
        }
        unset($params['__force_empty']);
        $result = parent::all($params);
        $result['data'] = $this->attachItemAggregates($result['data']);
        return $result;
    }

    /**
     * Gắn cột tổng hợp từ vie_order_item cho mỗi order row:
     * hotel_names, hotel_subtotal, ticket_subtotal — 1 grouped query cho cả page.
     */
    public function attachItemAggregates(array $rows): array
    {
        $orderIds = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $orderIds[] = (int) $row['id'];
            }
        }

        $aggregates = [];
        if ($orderIds !== []) {
            global $wpdb;
            $itemTbl  = $wpdb->prefix . 'vie_order_item';
            $hotelTbl = $wpdb->prefix . 'vie_hotel';
            $place    = implode(',', array_fill(0, count($orderIds), '%d'));
            $sql = "
                SELECT
                    i.order_id,
                    GROUP_CONCAT(DISTINCT h.name ORDER BY h.name SEPARATOR ', ') AS hotel_names,
                    SUM(i.room_subtotal + i.extra_adult_total + i.child_surcharge_total) AS hotel_subtotal,
                    SUM(i.ticket_subtotal) AS ticket_subtotal
                FROM {$itemTbl} i
                LEFT JOIN {$hotelTbl} h ON h.id = i.hotel_id
                WHERE i.order_id IN ({$place})
                GROUP BY i.order_id
            ";
            $aggRows = $wpdb->get_results($wpdb->prepare($sql, ...$orderIds), ARRAY_A) ?: [];
            foreach ($aggRows as $ar) {
                $aggregates[(int) $ar['order_id']] = [
                    'hotel_names'     => (string) ($ar['hotel_names'] ?? ''),
                    'hotel_subtotal'  => (float) $ar['hotel_subtotal'],
                    'ticket_subtotal' => (float) $ar['ticket_subtotal'],
                ];
            }
        }

        foreach ($rows as &$row) {
            $agg = $aggregates[(int) ($row['id'] ?? 0)] ?? null;
            $row['hotel_names']     = $agg['hotel_names'] ?? '';
            $row['hotel_subtotal']  = $agg['hotel_subtotal'] ?? 0.0;
            $row['ticket_subtotal'] = $agg['ticket_subtotal'] ?? 0.0;
        }
        unset($row);

        return $rows;
    }

    public function canUserViewOrder(int $userId, array $order): bool
    {
        if ($userId === 0) {
            return false;
        }
        if (user_can($userId, 'vie_view_all_orders')) {
            return true;
        }
        if (user_can($userId, 'vie_view_own_orders')) {
            return (int) ($order['sales_user_id'] ?? 0) === $userId;
        }
        return false;
    }

    protected function applyUserScope(array $params): array
    {
        $userId = (int) get_current_user_id();
        if ($userId === 0) {
            return $params;
        }

        if (user_can($userId, 'vie_view_all_orders')) {
            return $params;
        }

        if (user_can($userId, 'vie_view_own_orders')) {
            $params['sales_user_id'] = $userId;
            return $params;
        }

        $params['__force_empty'] = true;
        return $params;
    }

    public function findByCode(string $code): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE code = %s LIMIT 1", $code),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE idempotency_key = %s LIMIT 1", $key),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    /**
     * Compare-and-swap update: chỉ apply $patch khi `status` hiện tại trong DB
     * còn bằng $expectedStatus. Trả về true nếu update thành công, false nếu
     * status đã đổi (race).
     *
     * Áp dụng cho mọi state transition (confirm/complete/cancel/no_show) thay
     * thế pattern read-then-update truyền thống.
     *
     * $patch dùng fillable filter + cast giống AbstractRepository::update.
     */
    public function updateIfStatus(int $id, string $expectedStatus, array $patch): bool
    {
        global $wpdb;
        $fillable = $this->fillable();
        $safe = array_intersect_key($patch, array_flip($fillable));
        if ($safe === []) {
            return false;
        }
        $safe['updated_at'] = current_time('mysql');

        // Build SET clause với placeholders đúng kiểu cho mỗi cột.
        $setParts = [];
        $params   = [];
        foreach ($safe as $col => $val) {
            if ($val === null) {
                $setParts[] = "{$col} = NULL";
                continue;
            }
            $ph = is_int($val) ? '%d' : (is_float($val) ? '%f' : '%s');
            $setParts[] = "{$col} = {$ph}";
            $params[]   = $val;
        }
        $params[] = $id;
        $params[] = $expectedStatus;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = %%d AND status = %%s",
            $this->table(),
            implode(', ', $setParts)
        );
        $prepared = $wpdb->prepare($sql, ...$params);
        $affected = $wpdb->query($prepared);
        if ($affected === false) {
            throw new \RuntimeException('Order CAS update failed: ' . $wpdb->last_error);
        }
        return (int) $affected === 1;
    }
}
