<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\DTO\OrderRequest;
use Vie\Repository\OrderRepository;
use Vie\Service\Coupon\CouponException;
use Vie\Service\Order\OrderNotFoundException;
use Vie\Service\Order\OrderService;
use Vie\Service\Order\RequiresQuoteException;
use Vie\Service\Order\StockUnavailableException;
use Vie\Service\Payment\SepayCheckout;
use Vie\Support\CostVisibility;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\OrderCreateValidation;
use Vie\Service\Order\OrderDraftService;
use Vie\Validation\Schemas\OrderDraftValidation;

final class OrderController
{
    /**
     * Object-level IDOR guard for any write/read of a single order.
     * Returns null when authorized; returns a 403/404 response when not.
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

    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(OrderRepository::class);
        $params = $request->get_params();

        // Cache pull báo cáo/bulk (per_page > 100); bảng tương tác (<=100) luôn tươi.
        // Key gồm user id để tôn trọng applyUserScope (sales chỉ thấy đơn của mình).
        $result = ((int) ($params['per_page'] ?? 0) > 100)
            ? \Vie\Support\QueryCache::remember(
                'orders_index|' . get_current_user_id() . '|' . wp_json_encode($params),
                \Vie\Support\QueryCache::TTL,
                static fn() => $repo->all($params)
            )
            : $repo->all($params);

        return ResponseEnvelope::paginated(CostVisibility::stripOrders($result['data']), $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        try {
            $orderSvc = Container::get(OrderService::class);
            $detail   = $orderSvc->buildDetail($id);

            $repo   = Container::get(OrderRepository::class);
            $userId = (int) get_current_user_id();
            if (!$repo->canUserViewOrder($userId, $detail)) {
                return ResponseEnvelope::error([
                    ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền xem đơn này'],
                ], 403);
            }

            return ResponseEnvelope::success(CostVisibility::stripOrder($detail));
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, OrderCreateValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $cross = OrderCreateValidation::crossValidate($v->validated());
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        $idemKey = $request->get_header('X-Idempotency-Key') ?: ($data['idempotency_key'] ?? null);
        $ip      = $_SERVER['REMOTE_ADDR']     ?? null;
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $req      = OrderRequest::fromArray($v->validated(), $idemKey, $ip, $ua);
            $orderSvc = Container::get(OrderService::class);
            $detail   = $orderSvc->create($req);

            // Build SePay checkout form (POST) nếu enabled
            try {
                $checkout = Container::get(SepayCheckout::class);
                $detail['checkout'] = $checkout->buildCheckoutForm((int) $detail['id']);
            } catch (\Throwable $e) {
                $detail['checkout'] = null;
            }

            return ResponseEnvelope::success(CostVisibility::stripOrder($detail), [], 201);
        } catch (StockUnavailableException $e) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'stock_unavailable',
                    'field'   => null,
                    'message' => $e->getMessage(),
                    'meta'    => ['unavailable_dates' => $e->unavailableDates],
                ],
            ], 409);
        } catch (RequiresQuoteException $e) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'requires_quote',
                    'field'   => null,
                    'message' => $e->getMessage(),
                    'meta'    => ['messages' => $e->messages],
                ],
            ], 422);
        } catch (CouponException $e) {
            $errs = [];
            foreach ($e->messages as $msg) {
                $errs[] = ['code' => 'coupon_invalid', 'field' => 'coupon_code', 'message' => $msg];
            }
            return ResponseEnvelope::error($errs, 422);
        }
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }

        $data = $request->get_json_params();
        $v    = Validator::validate($data, \Vie\Validation\Schemas\OrderValidation::updateRules($id));

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $repo = Container::get(OrderRepository::class);
        $row  = $repo->update($id, CostVisibility::stripWritable($v->validated()));
        return ResponseEnvelope::success(CostVisibility::stripOrder($row));
    }

    /**
     * DELETE /orders/{id} không xóa cứng — chuyển sang soft-cancel để bảo toàn
     * audit trail, restore stock, và release coupon usage qua OrderService::cancel.
     */
    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $orderSvc->cancel($id, 'Hủy qua DELETE /orders/{id}', (int) get_current_user_id(), 0);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        } catch (\Vie\Service\Order\IllegalTransitionException $e) {
            return ResponseEnvelope::error([
                ['code' => 'illegal_transition', 'field' => 'status', 'message' => $e->getMessage()],
            ], 409);
        }

        return new \WP_REST_Response(null, 204);
    }

    /**
     * Object-level guard cho thao tác đơn NHÁP: phải tồn tại, đúng status='draft'
     * và thuộc quyền xem của user (sales chỉ thấy nháp của mình).
     */
    private static function ensureCanAccessDraft(int $orderId): ?\WP_REST_Response
    {
        $repo  = Container::get(OrderRepository::class);
        $order = $repo->find($orderId);
        if ($order === null || ($order['status'] ?? '') !== 'draft') {
            return ResponseEnvelope::notFound('Đơn nháp');
        }
        $userId = (int) get_current_user_id();
        if (!$repo->canUserViewOrder($userId, $order)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền thao tác với đơn nháp này'],
            ], 403);
        }
        return null;
    }

    public static function storeDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, OrderDraftValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $svc   = Container::get(OrderDraftService::class);
        $draft = $svc->save($v->validated(), (int) get_current_user_id());
        return ResponseEnvelope::success(CostVisibility::stripOrder($draft), [], 201);
    }

    public static function updateDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccessDraft($id)) {
            return $denied;
        }

        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, OrderDraftValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        try {
            $svc   = Container::get(OrderDraftService::class);
            $draft = $svc->update($id, $v->validated(), (int) get_current_user_id());
            return ResponseEnvelope::success(CostVisibility::stripOrder($draft));
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn nháp');
        }
    }

    public static function showDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccessDraft($id)) {
            return $denied;
        }

        try {
            $svc = Container::get(OrderDraftService::class);
            return ResponseEnvelope::success(CostVisibility::stripOrder($svc->get($id, (int) get_current_user_id())));
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn nháp');
        }
    }

    public static function destroyDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccessDraft($id)) {
            return $denied;
        }

        try {
            Container::get(OrderDraftService::class)->delete($id);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn nháp');
        }
        return new \WP_REST_Response(null, 204);
    }
}
