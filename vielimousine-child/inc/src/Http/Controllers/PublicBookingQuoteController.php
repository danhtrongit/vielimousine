<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Http\RateLimiter;
use Vie\Service\BookingQuote\BookingQuoteException;
use Vie\Service\BookingQuote\BookingQuotePaymentService;
use Vie\Service\BookingQuote\BookingQuoteService;
use Vie\Support\ResponseEnvelope;

final class PublicBookingQuoteController
{
    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($denied = RateLimiter::check('public_booking_quote_view_15s', 30, 15)) {
            return $denied;
        }

        try {
            $view = Container::get(BookingQuoteService::class)->getPublic(
                strtolower(trim((string) $request->get_param('public_id')))
            );
            return ResponseEnvelope::success($view);
        } catch (BookingQuoteException $e) {
            if ($e->httpStatus === 404) {
                return ResponseEnvelope::notFound('Báo giá');
            }
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    public static function checkout(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($denied = RateLimiter::check('public_booking_quote_checkout_15s', 10, 15)) {
            return $denied;
        }

        $body = $request->get_json_params();
        if (is_array($body) && array_intersect(['amount', 'stage', 'purpose'], array_keys($body)) !== []) {
            return ResponseEnvelope::error([[
                'code' => 'invalid_checkout_request',
                'field' => null,
                'message' => 'Số tiền và đợt thanh toán được hệ thống tự xác định.',
            ]], 422);
        }

        try {
            $result = Container::get(BookingQuotePaymentService::class)->checkout(
                strtolower(trim((string) $request->get_param('public_id')))
            );
            return ResponseEnvelope::success($result);
        } catch (BookingQuoteException $e) {
            if ($e->httpStatus === 404) {
                return ResponseEnvelope::notFound('Báo giá');
            }
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    private static function exception(BookingQuoteException $e): \WP_REST_Response
    {
        return ResponseEnvelope::error([[
            'code' => $e->errorCode,
            'field' => $e->field,
            'message' => $e->getMessage(),
        ]], $e->httpStatus);
    }

    private static function internalError(): \WP_REST_Response
    {
        return ResponseEnvelope::error([[
            'code' => 'booking_quote_error',
            'field' => null,
            'message' => 'Không thể xử lý báo giá lúc này. Vui lòng thử lại.',
        ]], 500);
    }
}
