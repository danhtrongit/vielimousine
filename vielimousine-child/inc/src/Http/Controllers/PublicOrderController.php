<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\DTO\OrderRequest;
use Vie\Service\Coupon\CouponException;
use Vie\Service\Order\OrderService;
use Vie\Service\Order\RequiresQuoteException;
use Vie\Service\Order\StockUnavailableException;
use Vie\Service\Payment\SepayCheckout;
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
        $clean['source']        = 'website';
        $clean['sales_user_id'] = null;
        $clean['voucher_code']  = null;
        $clean['payment_method'] = null;

        $idemKey = $request->get_header('X-Idempotency-Key');
        $ua      = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $req      = OrderRequest::fromArray($clean, $idemKey, $ip, $ua);
            $orderSvc = Container::get(OrderService::class);
            $detail   = $orderSvc->create($req);

            try {
                $checkout              = Container::get(SepayCheckout::class);
                $detail['redirect_url'] = $checkout->buildRedirectUrl((int) $detail['id']);
            } catch (\Throwable) {
                $detail['redirect_url'] = null;
            }

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
