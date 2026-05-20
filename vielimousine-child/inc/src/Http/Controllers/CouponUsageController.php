<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\CouponUsageRepository;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\CouponUsageValidation;

final class CouponUsageController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(CouponUsageRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $v    = Validator::validate($data, CouponUsageValidation::createRules());

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $repo = Container::get(CouponUsageRepository::class);
        $row  = $repo->create($v->validated());

        return ResponseEnvelope::success($row, [], 201);
    }
}
