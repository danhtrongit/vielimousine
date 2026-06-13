<?php
declare(strict_types=1);

namespace Vie\Service\Order;

use Vie\DTO\OrderRequest;
use Vie\DTO\QuoteRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\CustomerRepository;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\PaymentLogRepository;
use Vie\Repository\RoomPriceRepository;
use Vie\Repository\RoomRepository;
use Vie\DTO\PaymentRequest;
use Vie\Service\Coupon\CouponService;
use Vie\Service\HookRegistry;
use Vie\Service\Payment\PaymentLedger;
use Vie\Service\Pricing\PriceCalculator;
use Vie\Support\Money;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly OrderItemRepository $itemRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly RoomRepository $roomRepo,
        private readonly RoomPriceRepository $roomPriceRepo,
        private readonly PaymentLogRepository $paymentRepo,
        private readonly ActivityLogRepository $activityRepo,
        private readonly PriceCalculator $priceCalc,
        private readonly CouponService $couponService,
        private readonly OrderCodeGenerator $codeGen,
        private readonly OrderStateMachine $stateMachine,
        private readonly PaymentLedger $paymentLedger,
    ) {
    }

    public function create(OrderRequest $req): array
    {
        // 1. Idempotency check (before TX)
        if ($req->idempotencyKey !== null) {
            $existing = $this->orderRepo->findByIdempotencyKey($req->idempotencyKey);
            if ($existing !== null) {
                return $this->buildDetail((int) $existing['id']);
            }
        }

        // 2. Pre-validate + price each item
        $itemQuotes = [];
        $subtotal   = 0;
        foreach ($req->items as $i => $itemInput) {
            $qr = QuoteRequest::fromArray([
                'room_id'      => (int) ($itemInput['room_id'] ?? 0),
                'checkin'      => (string) ($itemInput['checkin'] ?? ''),
                'checkout'     => (string) ($itemInput['checkout'] ?? ''),
                'adults'       => (int) ($itemInput['adults'] ?? 0),
                'child_ages'   => $itemInput['child_ages'] ?? [],
                'user_rooms'   => (int) ($itemInput['user_rooms'] ?? 0),
                'booking_type' => (string) ($itemInput['booking_type'] ?? 'room'),
            ]);
            $breakdown = $this->priceCalc->quote($qr);

            if ($breakdown->requiresQuote) {
                throw new RequiresQuoteException(
                    $breakdown->messages,
                    'Item #' . ($i + 1) . ' yêu cầu báo giá thủ công'
                );
            }

            $itemQuotes[] = [
                'request'   => $qr,
                'breakdown' => $breakdown,
                'input'     => $itemInput,
            ];
            $subtotal += $breakdown->subtotal;
        }

        if ($itemQuotes === []) {
            throw new OrderException('Đơn hàng phải có ít nhất 1 sản phẩm');
        }

        // 3. Coupon validation
        $couponData    = null;
        $totalDiscount = 0;
        if ($req->couponCode !== null) {
            $first         = $itemQuotes[0];
            $hotelId       = (int) $this->roomRepo->findOrFail($first['request']->roomId)['hotel_id'];
            $bookingType   = $first['request']->bookingType;
            $couponResult  = $this->couponService->validate(
                $req->couponCode,
                $subtotal,
                $hotelId,
                $first['request']->roomId,
                $bookingType,
                $req->customer['email'] ?? null,
            );
            if (!$couponResult['valid']) {
                throw new \Vie\Service\Coupon\CouponException($couponResult['messages']);
            }
            $couponData    = $couponResult['coupon'];
            $totalDiscount = (int) $couponResult['discount'];
        }

        $total = Money::roundVND(max(0, $subtotal - $totalDiscount));

        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // 4. Stock hold (FOR UPDATE)
            $stockTargets    = $this->collectStockTargets($itemQuotes);
            $unavailable     = $this->checkAndLockStock($stockTargets);
            if ($unavailable !== []) {
                throw new StockUnavailableException($unavailable, 'Hết phòng các ngày: ' . implode(', ', $unavailable));
            }

            // 5. Decrement stock
            $this->decrementStock($stockTargets);

            // 6. Upsert customer
            $customer = $this->customerRepo->findOrCreateByPhone(
                (string) ($req->customer['phone'] ?? ''),
                [
                    'name'  => $req->customer['name']  ?? '',
                    'email' => $req->customer['email'] ?? null,
                ]
            );

            // 7. Build order header
            $firstQ      = $itemQuotes[0];
            $firstReq    = $firstQ['request'];
            $checkinMin  = $firstReq->checkin;
            $checkoutMax = $firstReq->checkout;
            $totalAdults = 0;
            $totalChild  = 0;
            $childAgesAll = [];
            foreach ($itemQuotes as $iq) {
                /** @var QuoteRequest $r */
                $r = $iq['request'];
                if ($r->checkin < $checkinMin)   { $checkinMin = $r->checkin; }
                if ($r->checkout > $checkoutMax) { $checkoutMax = $r->checkout; }
                $totalAdults += $r->adults;
                $totalChild  += count($r->childAges);
                $childAgesAll = array_merge($childAgesAll, $r->childAges);
            }
            $nights = (new \DateTimeImmutable($checkoutMax))->diff(new \DateTimeImmutable($checkinMin))->days;

            // 8. Generate code + INSERT order
            $code = $this->codeGen->next();

            $orderData = [
                'code'                  => $code,
                'idempotency_key'       => $req->idempotencyKey,
                'customer_id'           => (int) $customer['id'],
                'customer_phone'        => (string) $customer['phone'],
                'customer_name'         => (string) ($req->customer['name'] ?? $customer['name'] ?? ''),
                'customer_email'        => $req->customer['email'] ?? ($customer['email'] ?? null),
                'customer_vat'          => $req->customer['vat'] ?? null,
                // Nếu client không truyền (vd tạo đơn từ admin wizard), mặc định
                // dùng user đang đăng nhập làm sales — khớp expectation "ai tạo
                // là người bán" cho mọi báo cáo.
                'sales_user_id'         => $req->salesUserId ?? (get_current_user_id() ?: null),
                'source'                => $req->source,
                'checkin'               => $checkinMin,
                'checkout'              => $checkoutMax,
                'nights'                => $nights,
                'adults'                => $totalAdults,
                'children'              => $totalChild,
                'child_ages'            => $childAgesAll,
                'customer_note'         => $req->customerNote,
                'pickup'                => $req->pickup,
                'dropoff'               => $req->dropoff,
                'subtotal'              => $subtotal,
                'discount'              => $totalDiscount,
                'tax'                   => 0,
                'total'                 => $total,
                'cost_total'            => 0,
                'profit_total'          => $total,
                'currency'              => 'VND',
                'coupon_id'             => $couponData !== null ? (int) $couponData['id'] : null,
                'coupon_code'           => $couponData !== null ? (string) $couponData['code'] : null,
                'payment_status'        => 'pending',
                'paid_amount'           => 0,
                'partner_payment_status'=> 'not_created',
                'voucher_code'          => $req->voucherCode,
                'status'                => 'pending',
                'created_by'            => get_current_user_id() ?: null,
                'ip'                    => $req->ip,
                'user_agent'            => $req->userAgent,
            ];

            $order = $this->orderRepo->create($orderData);
            $orderId = (int) $order['id'];

            // 9. INSERT order items với proportional discount
            $itemsCreated = [];
            $remainingDiscount = $totalDiscount;
            $lastIndex = count($itemQuotes) - 1;
            foreach ($itemQuotes as $idx => $iq) {
                $bd    = $iq['breakdown'];
                $r     = $iq['request'];
                $input = $iq['input'];
                $room  = $this->roomRepo->findOrFail($r->roomId);

                // Proportional split: last item soaks rounding error
                if ($idx === $lastIndex) {
                    $lineDiscount = $remainingDiscount;
                } else {
                    $lineDiscount = $subtotal > 0
                        ? (int) floor($totalDiscount * $bd->subtotal / $subtotal)
                        : 0;
                }
                $remainingDiscount -= $lineDiscount;

                $lineTotal = $bd->subtotal - $lineDiscount;

                $itemData = [
                    'order_id'              => $orderId,
                    'hotel_id'              => (int) $room['hotel_id'],
                    'room_id'               => $r->roomId,
                    'name'                  => (string) $room['name'],
                    'booking_type'          => $r->bookingType,
                    'unit_label'            => 'đêm',
                    'quantity'              => $bd->numRooms,
                    'checkin'               => $r->checkin,
                    'checkout'              => $r->checkout,
                    'nights'                => $bd->nights,
                    'adults'                => $r->adults,
                    'children'              => count($r->childAges),
                    'child_ages'            => $r->childAges,
                    'room_subtotal'         => $bd->roomSubtotal,
                    'extra_adult_total'     => $bd->extraAdultSubtotal,
                    'child_surcharge_total' => $bd->childSurchargeTotal,
                    'ticket_count'          => $bd->seatCount,
                    'ticket_subtotal'       => $bd->ticketSubtotal,
                    'line_discount'         => $lineDiscount,
                    'line_total'            => $lineTotal,
                    'cost_total'            => 0,
                    'profit_total'          => $lineTotal,
                    'pricing_snapshot'      => $bd->toArray(),
                    'status'                => 'active',
                ];

                $itemsCreated[] = $this->itemRepo->create($itemData);
            }

            // 10. Record coupon usage
            if ($couponData !== null) {
                $this->couponService->recordUsage(
                    (int) $couponData['id'],
                    $orderId,
                    $req->customer['email'] ?? null,
                    $totalDiscount,
                );
            }

            // 11. Cập nhật booking_count customer (recompute từ vie_order)
            $this->customerRepo->recomputeBookingCount((int) $customer['id']);

            // 12. Activity log
            $this->activityRepo->create([
                'actor_user_id' => get_current_user_id() ?: 0,
                'entity_type'   => 'order',
                'entity_id'     => $orderId,
                'action'        => 'create',
                'before_json'   => null,
                'after_json'    => [
                    'status'         => 'pending',
                    'total'          => $total,
                    'code'           => $code,
                    'coupon_code'    => $couponData['code'] ?? null,
                ],
                'ip'            => $req->ip,
                'user_agent'    => $req->userAgent,
            ]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        // 12. Fire hook after commit
        do_action(HookRegistry::ORDER_CREATED, $orderId, $this->orderRepo->find($orderId));

        // 13. Return detail
        return $this->buildDetail($orderId);
    }

    public function cancel(int $orderId, string $reason, int $actorUserId = 0, int $refundAmount = 0): array
    {
        $order = $this->orderRepo->find($orderId);
        if ($order === null) {
            throw new OrderNotFoundException("Đơn hàng #{$orderId} không tồn tại");
        }

        $this->stateMachine->assertTransition((string) $order['status'], 'cancelled');

        $items     = $this->loadItems($orderId);

        $paidAmount   = (int) ($order['paid_amount'] ?? 0);
        $refundAmount = max(0, min($refundAmount, $paidAmount));

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            // Restore stock for each active item
            foreach ($items as $item) {
                if (($item['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $snapshot       = is_array($item['pricing_snapshot'] ?? null) ? $item['pricing_snapshot'] : [];
                $roomsAllocated = (int) ($snapshot['num_rooms'] ?? 1);
                $nights         = $this->dateRange((string) $item['checkin'], (string) $item['checkout']);
                if ($nights === []) {
                    continue;
                }

                $tablePrice = $wpdb->prefix . 'vie_room_price';
                $placeholders = implode(',', array_fill(0, count($nights), '%s'));
                $params = array_merge([$roomsAllocated, (int) $item['room_id']], $nights);
                $sql = $wpdb->prepare(
                    "UPDATE {$tablePrice} SET stock = stock + %d WHERE room_id = %d AND date IN ({$placeholders})",
                    ...$params
                );
                $wpdb->query($sql);
            }

            // Compare-and-swap: chỉ cancel khi status hiện tại == status đã đọc.
            // Chống race với transition() và cron NoShowSweep.
            if (!$this->orderRepo->updateIfStatus($orderId, (string) $order['status'], [
                'status'        => 'cancelled',
                'cancelled_at'  => current_time('mysql'),
                'cancel_reason' => $reason,
            ])) {
                throw new IllegalTransitionException(
                    "Đơn đã được chuyển sang trạng thái khác trong lúc bạn thao tác. Vui lòng tải lại."
                );
            }

            // Recompute booking_count cho customer (đơn vừa cancel sẽ bị loại)
            if (!empty($order['customer_id'])) {
                $this->customerRepo->recomputeBookingCount((int) $order['customer_id']);
            }

            // Update each item
            foreach ($items as $item) {
                if (($item['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $this->itemRepo->update((int) $item['id'], [
                    'status'        => 'cancelled',
                    'cancelled_at'  => current_time('mysql'),
                    'cancel_reason' => $reason,
                ]);
            }

            // Refund via PaymentLedger (single source of truth)
            $ledgerResult = null;
            if ($refundAmount > 0) {
                $paymentReq = new PaymentRequest(
                    orderId:       $orderId,
                    type:          'refund',
                    amount:        $refundAmount,
                    method:        'manual',
                    gateway:       null,
                    transactionId: null,
                    note:          'Hoàn tiền thủ công khi hủy đơn: ' . $reason,
                    paidAt:        current_time('mysql'),
                    createdBy:     $actorUserId ?: null,
                    rawPayload:    null,
                );
                $ledgerResult = $this->paymentLedger->recordInTx($paymentReq);
            }

            // Activity log
            $this->activityRepo->create([
                'actor_user_id' => $actorUserId,
                'entity_type'   => 'order',
                'entity_id'     => $orderId,
                'action'        => 'cancel',
                'before_json'   => ['status' => $order['status'], 'paid_amount' => $paidAmount],
                'after_json'    => [
                    'status'        => 'cancelled',
                    'cancel_reason' => $reason,
                    'refund_amount' => $refundAmount,
                    'paid_amount'   => $paidAmount,
                ],
            ]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        // Fire ledger hooks AFTER outer commit
        if ($ledgerResult !== null) {
            $this->paymentLedger->fireHooksAfterCommit($ledgerResult);
        }

        $refundCtx = [
            'paid_amount'    => $paidAmount,
            'refund_amount'  => $refundAmount,
            'remaining_held' => $paidAmount - $refundAmount,
            'penalty_amount' => max(0, $paidAmount - $refundAmount),
            'reason'         => $reason,
        ];
        $cancelledOrder = $this->orderRepo->find($orderId);
        do_action(HookRegistry::ORDER_CANCELLED, $orderId, $cancelledOrder, $refundCtx);

        $detail = $this->buildDetail($orderId);
        $detail['refund'] = $refundCtx;
        return $detail;
    }

    /**
     * Generic status transition for orders (non-cancel paths).
     * Allowed: pending → confirmed | no_show
     *          confirmed → completed
     * Use cancel() for cancellation (it handles refund + stock restore separately).
     */
    public function transition(int $orderId, string $newStatus, int $actorUserId = 0): array
    {
        $order = $this->orderRepo->find($orderId);
        if ($order === null) {
            throw new OrderNotFoundException("Đơn hàng #{$orderId} không tồn tại");
        }

        $current = (string) $order['status'];
        $this->stateMachine->assertTransition($current, $newStatus);

        if ($newStatus === 'cancelled') {
            throw new \LogicException('Use cancel() instead of transition() for cancellations');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $patch = ['status' => $newStatus];
            if ($newStatus === 'confirmed') {
                $patch['confirmed_at'] = current_time('mysql');
            } elseif ($newStatus === 'completed') {
                $patch['completed_at'] = current_time('mysql');
            }
            // Compare-and-swap: chỉ update khi status hiện tại == $current.
            // Chống race với cron NoShowSweep và 2 admin cùng bấm 2 button.
            if (!$this->orderRepo->updateIfStatus($orderId, $current, $patch)) {
                throw new IllegalTransitionException(
                    "Đơn đã được chuyển sang trạng thái khác trong lúc bạn thao tác. Vui lòng tải lại."
                );
            }

            $this->activityRepo->create([
                'actor_user_id' => $actorUserId,
                'entity_type'   => 'order',
                'entity_id'     => $orderId,
                'action'        => 'transition',
                'before_json'   => ['status' => $current],
                'after_json'    => ['status' => $newStatus],
            ]);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }

        $updated = $this->orderRepo->find($orderId);
        $hookMap = [
            'confirmed' => HookRegistry::ORDER_CONFIRMED,
            'completed' => HookRegistry::ORDER_COMPLETED,
            'no_show'   => HookRegistry::ORDER_NO_SHOW,
        ];
        if (isset($hookMap[$newStatus])) {
            do_action($hookMap[$newStatus], $orderId, $updated);
        }

        return $this->buildDetail($orderId);
    }

    public function buildDetail(int $orderId): array
    {
        $order = $this->orderRepo->find($orderId);
        if ($order === null) {
            throw new OrderNotFoundException("Đơn hàng #{$orderId} không tồn tại");
        }
        $order = $this->orderRepo->attachItemAggregates([$order])[0];
        $order['items']    = $this->loadItems($orderId);
        $order['payments'] = $this->loadPayments($orderId);
        $order['customer'] = $this->customerRepo->find((int) $order['customer_id']);
        return $order;
    }

    private function loadItems(int $orderId): array
    {
        $result = $this->itemRepo->all([
            'order_id' => $orderId,
            'per_page' => 100,
        ]);
        return $result['data'] ?? [];
    }

    private function loadPayments(int $orderId): array
    {
        $result = $this->paymentRepo->all([
            'order_id' => $orderId,
            'per_page' => 100,
        ]);
        return $result['data'] ?? [];
    }

    /**
     * Returns: [room_id => [date => numRoomsNeeded]]
     */
    private function collectStockTargets(array $itemQuotes): array
    {
        $targets = [];
        foreach ($itemQuotes as $iq) {
            $r       = $iq['request'];
            $bd      = $iq['breakdown'];
            $dates   = $this->dateRange($r->checkin, $r->checkout);
            foreach ($dates as $d) {
                $targets[$r->roomId][$d] = ($targets[$r->roomId][$d] ?? 0) + $bd->numRooms;
            }
        }
        return $targets;
    }

    /**
     * @return string[] unavailable dates
     */
    private function checkAndLockStock(array $targets): array
    {
        if ($targets === []) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vie_room_price';

        $unavailable = [];
        foreach ($targets as $roomId => $dateMap) {
            $dates        = array_keys($dateMap);
            $placeholders = implode(',', array_fill(0, count($dates), '%s'));
            $params       = array_merge([(int) $roomId], $dates);
            $sql = $wpdb->prepare(
                "SELECT date, stock, is_active FROM {$table} WHERE room_id = %d AND date IN ({$placeholders}) FOR UPDATE",
                ...$params
            );
            $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
            $found = [];
            foreach ($rows as $row) {
                $found[$row['date']] = $row;
            }
            foreach ($dateMap as $date => $needed) {
                $row = $found[$date] ?? null;
                if ($row === null || (int) $row['is_active'] !== 1 || (int) $row['stock'] < $needed) {
                    $unavailable[] = (string) $date;
                }
            }
        }

        return array_values(array_unique($unavailable));
    }

    /**
     * Compare-and-swap stock decrement. Mỗi UPDATE chỉ thành công nếu
     * `stock >= $needed AND is_active = 1` ngay tại thời điểm execute.
     *
     * Nếu rows_affected != 1 → có ai đó (race) đã rút stock dưới ngưỡng giữa
     * checkAndLockStock (SELECT FOR UPDATE) và decrementStock (UPDATE).
     * Throw StockUnavailableException để TX outer rollback toàn bộ order.
     *
     * @throws \Vie\Service\Order\StockUnavailableException
     */
    private function decrementStock(array $targets): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_room_price';

        foreach ($targets as $roomId => $dateMap) {
            foreach ($dateMap as $date => $needed) {
                $needed = (int) $needed;
                $rid    = (int) $roomId;
                $d      = (string) $date;
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table}
                        SET stock = stock - %d, updated_at = %s
                      WHERE room_id = %d
                        AND date = %s
                        AND is_active = 1
                        AND stock >= %d",
                    $needed,
                    current_time('mysql'),
                    $rid,
                    $d,
                    $needed
                ));
                if ($affected === false) {
                    throw new \RuntimeException('Stock decrement SQL failed: ' . $wpdb->last_error);
                }
                if ((int) $affected !== 1) {
                    throw new \Vie\Service\Order\StockUnavailableException(
                        [$d],
                        sprintf('Phòng không đủ tồn kho cho ngày %s.', $d)
                    );
                }
            }
        }
    }

    /**
     * @return string[] dates in [checkin, checkout)
     */
    private function dateRange(string $checkin, string $checkout): array
    {
        $dates = [];
        $cursor = new \DateTimeImmutable($checkin);
        $end    = new \DateTimeImmutable($checkout);
        while ($cursor < $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor  = $cursor->modify('+1 day');
        }
        return $dates;
    }

    private function derivePaymentStatus(int $paid, int $total): string
    {
        if ($paid <= 0)        { return 'pending'; }
        if ($paid >= $total)   { return 'paid'; }
        return 'partial';
    }
}
