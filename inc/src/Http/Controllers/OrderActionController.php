<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Order\IllegalTransitionException;
use Vie\Service\Order\OrderNotFoundException;
use Vie\Service\Order\OrderService;
use Vie\Support\ResponseEnvelope;

final class OrderActionController
{
    public static function cancel(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $reason = trim((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'reason', 'message' => 'Vui lòng nhập lý do hủy'],
            ], 422);
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $result   = $orderSvc->cancel($id, $reason, (int) get_current_user_id());
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
