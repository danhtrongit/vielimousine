<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Support\ResponseEnvelope;

final class HealthController
{
    public static function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        return ResponseEnvelope::success([
            'version' => VIE_CHILD_VERSION,
        ]);
    }
}
