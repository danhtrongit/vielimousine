<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Pricing\TicketPriceBulkService;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\TicketPriceBulkValidation;

final class TicketPriceBulkController
{
    public static function bulk(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) $data = [];

        $v = Validator::validate($data, TicketPriceBulkValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }
        $cross = TicketPriceBulkValidation::crossValidate($v->validated());
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        try {
            $svc    = Container::get(TicketPriceBulkService::class);
            $result = $svc->bulkUpsert(
                $v->validated()['scope'],
                $v->validated()['values'],
                (int) get_current_user_id(),
            );
            return ResponseEnvelope::success($result);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'bulk_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
