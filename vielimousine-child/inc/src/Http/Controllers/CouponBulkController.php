<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Coupon\CouponBulkException;
use Vie\Service\Coupon\CouponBulkService;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\CouponBulkValidation;

final class CouponBulkController
{
    public static function generate(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, CouponBulkValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $clean = $v->validated();
        $cross = CouponBulkValidation::crossValidate($clean);
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        try {
            $svc    = Container::get(CouponBulkService::class);
            $result = $svc->generateBatch($clean, (int) get_current_user_id());

            return ResponseEnvelope::success($result, [], 201);
        } catch (CouponBulkException $e) {
            return ResponseEnvelope::error([
                ['code' => 'bulk_generate_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 422);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'bulk_generate_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
