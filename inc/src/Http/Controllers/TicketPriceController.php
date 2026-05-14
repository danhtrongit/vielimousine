<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\TicketPriceRepository;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\TicketPriceValidation;

final class TicketPriceController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(TicketPriceRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(TicketPriceRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Ticket Price');
        }

        return ResponseEnvelope::success($row);
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $v    = Validator::validate($data, TicketPriceValidation::createRules());

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $repo = Container::get(TicketPriceRepository::class);
        $row  = $repo->create($v->validated());

        return ResponseEnvelope::success($row, [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(TicketPriceRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Ticket Price');
        }

        $data = $request->get_json_params();
        $v    = Validator::validate($data, TicketPriceValidation::updateRules($id));

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $row = $repo->update($id, $v->validated());

        return ResponseEnvelope::success($row);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(TicketPriceRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Ticket Price');
        }

        $repo->delete($id);

        return new \WP_REST_Response(null, 204);
    }
}
