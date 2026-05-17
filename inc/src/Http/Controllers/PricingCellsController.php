<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Pricing\PricingCellsService;
use Vie\Support\ResponseEnvelope;

final class PricingCellsController
{
    public static function save(\WP_REST_Request $request): \WP_REST_Response
    {
        $data    = $request->get_json_params() ?? [];
        $changes = is_array($data['changes'] ?? null) ? $data['changes'] : [];

        if ($changes === []) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'changes', 'message' => 'changes array required'],
            ], 422);
        }
        if (count($changes) > 500) {
            return ResponseEnvelope::error([
                ['code' => 'too_many', 'field' => 'changes', 'message' => 'tối đa 500 thay đổi / request'],
            ], 422);
        }

        try {
            $svc    = Container::get(PricingCellsService::class);
            $result = $svc->save($changes);
            return ResponseEnvelope::success($result);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'save_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 500);
        }
    }
}
