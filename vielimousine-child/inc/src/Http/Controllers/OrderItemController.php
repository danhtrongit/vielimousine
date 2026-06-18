<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Support\CostVisibility;
use Vie\Support\ResponseEnvelope;

/**
 * Order items là READ-ONLY qua REST. Việc tạo/sửa/xoá item phải đi qua OrderService
 * (trừ/hoàn tồn kho + tính lại tổng tiền đơn trong transaction) — không cho thao tác
 * trực tiếp ở repository layer (xem RestRouter::readOnlyWithCaps('order-items')).
 *
 * Đọc bị giới hạn theo quyền: user chỉ có vie_view_own_orders chỉ xem được item thuộc
 * đơn của chính họ (chống IDOR); vie_view_all_orders xem được tất cả.
 */
final class OrderItemController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        if (!user_can($userId, 'vie_view_all_orders')) {
            // Chỉ xem đơn của mình: bắt buộc lọc theo 1 đơn mà user sở hữu.
            $orderId   = (int) ($request->get_param('order_id') ?? 0);
            $orderRepo = Container::get(OrderRepository::class);
            $order     = $orderId > 0 ? $orderRepo->find($orderId) : null;
            if ($order === null || !$orderRepo->canUserViewOrder($userId, $order)) {
                return self::forbidden();
            }
        }

        $repo   = Container::get(OrderItemRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated(CostVisibility::stripItemRows($result['data']), $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(OrderItemRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Order Item');
        }

        if ($denied = self::ensureItemAccessible($row)) {
            return $denied;
        }

        return ResponseEnvelope::success(CostVisibility::stripItemRow($row));
    }

    /** Chặn nếu user không được phép xem đơn cha của item (chống IDOR cho vie_sales). */
    private static function ensureItemAccessible(array $item): ?\WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        if (user_can($userId, 'vie_view_all_orders')) {
            return null;
        }
        $orderRepo = Container::get(OrderRepository::class);
        $order     = $orderRepo->find((int) ($item['order_id'] ?? 0));
        if ($order !== null && $orderRepo->canUserViewOrder($userId, $order)) {
            return null;
        }
        return self::forbidden();
    }

    private static function forbidden(): \WP_REST_Response
    {
        return ResponseEnvelope::error([
            ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền xem mục đơn này'],
        ], 403);
    }
}
