<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\DTO\OrderRequest;
use Vie\Http\RateLimiter;
use Vie\Repository\CustomerRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\Coupon\CouponException;
use Vie\Service\Order\OrderService;
use Vie\Service\Order\RequiresQuoteException;
use Vie\Service\Order\StockUnavailableException;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\PublicOrderValidation;

final class PublicOrderController
{
    private const RATE_LIMIT_MAX    = 5;
    private const RATE_LIMIT_WINDOW = 5 * MINUTE_IN_SECONDS;

    public static function create(\WP_REST_Request $request): \WP_REST_Response
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (self::rateLimitExceeded($ip)) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'rate_limited',
                    'field'   => null,
                    'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau 5 phút.',
                ],
            ], 429);
        }

        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, PublicOrderValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $cross = PublicOrderValidation::crossValidate($v->validated());
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        $clean = $v->validated();
        $clean['source']         = 'website';
        $clean['sales_user_id']  = null;
        $clean['voucher_code']   = null;
        // `sepay` remains accepted as a legacy request alias; the stored method
        // now describes the actual flow (bank transfer confirmed by webhook).
        $clean['payment_method'] = 'bank_transfer';

        $idemKey = $request->get_header('X-Idempotency-Key');
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $req      = OrderRequest::fromArray($clean, $idemKey, $ip, $ua);
            $orderSvc = Container::get(OrderService::class);
            $detail   = $orderSvc->create($req);

            return ResponseEnvelope::success(self::publicView($detail), [], 201);
        } catch (StockUnavailableException $e) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'stock_unavailable',
                    'field'   => null,
                    'message' => $e->getMessage(),
                    'meta'    => ['unavailable_dates' => $e->unavailableDates],
                ],
            ], 409);
        } catch (CouponException $e) {
            $errs = [];
            foreach ($e->messages as $msg) {
                $errs[] = ['code' => 'coupon_invalid', 'field' => 'coupon_code', 'message' => $msg];
            }
            return ResponseEnvelope::error($errs, 422);
        } catch (RequiresQuoteException $e) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'requires_quote',
                    'field'   => null,
                    'message' => $e->getMessage(),
                    'meta'    => ['messages' => $e->messages],
                ],
            ], 422);
        }
    }

    /** Returns bank-transfer instructions for the remaining order balance. */
    public static function checkout(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($denied = RateLimiter::check('order_recheckout', 10, 300)) {
            return $denied;
        }

        $data  = $request->get_json_params() ?? [];
        $code  = trim((string) ($data['code'] ?? ''));
        $phone = CustomerRepository::normalizePhone(trim((string) ($data['phone'] ?? '')));

        if ($code === '' || $phone === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => null, 'message' => 'Thiếu mã đơn hoặc số điện thoại'],
            ], 422);
        }

        $orderRepo = Container::get(OrderRepository::class);
        $order     = $orderRepo->findByCode($code);
        if ($order === null || $order['customer_phone'] !== $phone) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }

        if (($order['payment_status'] ?? '') === 'paid' || ($order['status'] ?? '') === 'cancelled') {
            return ResponseEnvelope::error([
                ['code' => 'invalid_state', 'field' => null, 'message' => 'Đơn không ở trạng thái cần thanh toán.'],
            ], 409);
        }

        $amount = max(0, (int) $order['total'] - (int) $order['paid_amount']);
        if ($amount <= 0) {
            return ResponseEnvelope::error([
                ['code' => 'invalid_state', 'field' => null, 'message' => 'Đơn không còn số dư cần thanh toán.'],
            ], 409);
        }
        try {
            $transfer = Container::get(\Vie\Service\Payment\BankTransferInstructions::class)
                ->build($amount, (string) $order['code']);
        } catch (\Throwable) {
            return ResponseEnvelope::error([
                ['code' => 'bank_unconfigured', 'field' => null, 'message' => 'Thông tin nhận chuyển khoản chưa sẵn sàng.'],
            ], 503);
        }

        return ResponseEnvelope::success(['amount' => $amount, 'purpose' => 'balance', 'transfer' => $transfer]);
    }

    private static function rateLimitExceeded(string $ip): bool
    {
        $key   = 'vie_public_order_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT_MAX) {
            return true;
        }
        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
        return false;
    }

    /**
     * Loại bỏ các field cost/profit/internal khỏi response public.
     */
    private static function publicView(array $detail): array
    {
        unset($detail['cost_total'], $detail['profit_total'], $detail['internal_note']);

        if (isset($detail['items']) && is_array($detail['items'])) {
            foreach ($detail['items'] as &$item) {
                if (is_array($item)) {
                    unset($item['cost_total'], $item['profit_total']);
                }
            }
            unset($item);
        }

        if (isset($detail['payments']) && is_array($detail['payments'])) {
            foreach ($detail['payments'] as &$payment) {
                if (is_array($payment)) {
                    unset($payment['raw_payload'], $payment['created_by']);
                }
            }
            unset($payment);
        }

        return $detail;
    }
}
