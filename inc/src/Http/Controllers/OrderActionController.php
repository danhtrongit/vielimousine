<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\OrderRepository;
use Vie\Service\Order\IllegalTransitionException;
use Vie\Service\Order\OrderNotFoundException;
use Vie\Service\Order\OrderService;
use Vie\Support\ResponseEnvelope;

final class OrderActionController
{
    private const ALLOWED_TARGETS = ['confirmed', 'completed', 'no_show'];

    /**
     * Object-level IDOR guard. Same logic as OrderController::ensureCanAccess.
     */
    private static function ensureCanAccess(int $orderId): ?\WP_REST_Response
    {
        $repo  = Container::get(OrderRepository::class);
        $order = $repo->find($orderId);
        if ($order === null) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }
        $userId = (int) get_current_user_id();
        if (!$repo->canUserViewOrder($userId, $order)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền thao tác với đơn này'],
            ], 403);
        }
        return null;
    }

    public static function transition(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = [];
        $target = (string) ($body['status'] ?? '');

        if (!in_array($target, self::ALLOWED_TARGETS, true)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'status', 'message' => 'Trạng thái không hợp lệ. Dùng /cancel để hủy đơn.'],
            ], 422);
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $result   = $orderSvc->transition($id, $target, (int) get_current_user_id());
            return ResponseEnvelope::success($result);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        } catch (IllegalTransitionException $e) {
            return ResponseEnvelope::error([
                ['code' => 'illegal_transition', 'field' => 'status', 'message' => $e->getMessage()],
            ], 409);
        }
    }

    public static function cancel(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $reason       = trim((string) ($body['reason'] ?? ''));
        $refundAmount = (int) ($body['refund_amount'] ?? 0);

        if ($reason === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'reason', 'message' => 'Vui lòng nhập lý do hủy'],
            ], 422);
        }

        if ($refundAmount < 0) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'refund_amount', 'message' => 'Số tiền hoàn không hợp lệ'],
            ], 422);
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $result   = $orderSvc->cancel($id, $reason, (int) get_current_user_id(), $refundAmount);
            return ResponseEnvelope::success($result);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        } catch (IllegalTransitionException $e) {
            return ResponseEnvelope::error([
                ['code' => 'illegal_transition', 'field' => 'status', 'message' => $e->getMessage()],
            ], 409);
        }
    }
}
