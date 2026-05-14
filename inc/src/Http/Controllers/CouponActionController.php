<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Coupon\CouponService;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\CouponValidateValidation;

final class CouponActionController
{
    public static function validateCoupon(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, CouponValidateValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }
        $clean = $v->validated();

        $svc = Container::get(CouponService::class);
        $result = $svc->validate(
            (string) $clean['code'],
            (int) $clean['order_subtotal'],
            isset($clean['hotel_id']) ? (int) $clean['hotel_id'] : null,
            isset($clean['room_id'])  ? (int) $clean['room_id']  : null,
            isset($clean['booking_type']) ? (string) $clean['booking_type'] : null,
            isset($clean['user_email'])   ? (string) $clean['user_email']   : null,
        );

        return ResponseEnvelope::success($result);
    }
}
