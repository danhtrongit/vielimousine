<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\DTO\PaymentRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Repository\PaymentLogRepository;
use Vie\Service\HookRegistry;
use Vie\Service\Settings\SepaySettings;

final class PaymentLedger
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly PaymentLogRepository $paymentRepo,
        private readonly ActivityLogRepository $activityRepo,
        private readonly SepaySettings $settings,
    ) {
    }

    /**
     * Public entry — mở TX riêng. Dùng cho REST / webhook.
     */
    public function record(PaymentRequest $req): array
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $result = $this->recordInTx($req);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        $this->fireHooksAfterCommit($result);
        return $result;
    }

    /**
     * Caller-managed TX. Hooks KHÔNG fire ở đây — caller phải fire sau commit
     * qua fireHooksAfterCommit($result).
     */
    public function recordInTx(PaymentRequest $req): array
    {
        // 1. Verify order exists
        $order = $this->orderRepo->find($req->orderId);
        if ($order === null) {
            throw new PaymentException("Đơn hàng #{$req->orderId} không tồn tại");
        }

        // 2. Idempotency pre-check
        if ($req->gateway !== null && $req->transactionId !== null) {
            $existing = $this->findByGatewayTxId($req->gateway, $req->transactionId);
            if ($existing !== null) {
                throw new IdempotencyConflictException((int) $existing['id']);
            }
        }

        // 3. Insert payment_log
        try {
            $payment = $this->paymentRepo->create($req->toRow());
        } catch (\Throwable $e) {
            // UNIQUE(gateway, transaction_id) safety net — race vượt pre-check
            if ($req->gateway !== null && $req->transactionId !== null) {
                $existing = $this->findByGatewayTxId($req->gateway, $req->transactionId);
                if ($existing !== null) {
                    throw new IdempotencyConflictException((int) $existing['id']);
                }
            }
            throw new PaymentException('Không thể ghi payment log: ' . $e->getMessage());
        }

        // 4. Recompute paid_amount from ledger
        $paidAmount = $this->computePaidAmount($req->orderId);

        // 5. Derive payment_status
        $total = (int) $order['total'];
        $paymentStatus = $this->derivePaymentStatus($paidAmount, $total);

        // 6. Auto-confirm
        $autoConfirmed = false;
        $update = [
            'paid_amount'    => max(0, $paidAmount),
            'payment_status' => $paymentStatus,
        ];
        if ($paymentStatus === 'paid' && $order['status'] === 'pending' && $this->settings->autoConfirmOnPaid()) {
            $update['status']       = 'confirmed';
            $update['confirmed_at'] = current_time('mysql');
            $autoConfirmed          = true;
        }
        // Also stamp paid_at if newly paid
        if ($paymentStatus === 'paid' && empty($order['paid_at'])) {
            $update['paid_at'] = current_time('mysql');
        }

        $updatedOrder = $this->orderRepo->update($req->orderId, $update);

        // 7. Activity log
        $this->activityRepo->create([
            'actor_user_id' => $req->createdBy ?? 0,
            'entity_type'   => 'payment_log',
            'entity_id'     => (int) $payment['id'],
            'action'        => 'create',
            'before_json'   => null,
            'after_json'    => [
                'type'           => $req->type,
                'amount'         => $req->amount,
                'method'         => $req->method,
                'gateway'        => $req->gateway,
                'transaction_id' => $req->transactionId,
                'paid_amount'    => max(0, $paidAmount),
                'payment_status' => $paymentStatus,
                'auto_confirmed' => $autoConfirmed,
            ],
        ]);

        return [
            'payment'        => $payment,
            'order'          => $updatedOrder,
            'paid_amount'    => max(0, $paidAmount),
            'payment_status' => $paymentStatus,
            'auto_confirmed' => $autoConfirmed,
        ];
    }

    /**
     * Caller phải gọi method này SAU khi outer TX commit.
     */
    public function fireHooksAfterCommit(array $result): void
    {
        do_action(
            HookRegistry::PAYMENT_LOGGED,
            (int) $result['payment']['id'],
            $result['payment'],
            $result['order'],
        );
        if (!empty($result['auto_confirmed'])) {
            do_action(
                HookRegistry::ORDER_CONFIRMED,
                (int) $result['order']['id'],
                $result['order'],
            );
        }
    }

    private function findByGatewayTxId(string $gateway, string $txId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_payment_log';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE gateway = %s AND transaction_id = %s LIMIT 1",
                $gateway,
                $txId
            ),
            ARRAY_A
        );
        return $row !== null ? $row : null;
    }

    private function computePaidAmount(int $orderId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_payment_log';
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CASE
                WHEN type IN ('deposit','payment') THEN amount
                WHEN type IN ('refund','void') THEN -amount
                ELSE 0
            END) FROM {$table} WHERE order_id = %d",
            $orderId
        ));
        return $sum === null ? 0 : (int) $sum;
    }

    private function derivePaymentStatus(int $paid, int $total): string
    {
        if ($paid <= 0) {
            return 'pending';
        }
        if ($paid < $total) {
            return 'partial';
        }
        return 'paid';
    }
}
