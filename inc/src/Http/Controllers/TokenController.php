<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\TokenRepository;
use Vie\Support\ResponseEnvelope;

final class TokenController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(TokenRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(TokenRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Token');
        }

        return ResponseEnvelope::success($row);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(TokenRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Token');
        }

        $repo->delete($id);

        return new \WP_REST_Response(null, 204);
    }
}
