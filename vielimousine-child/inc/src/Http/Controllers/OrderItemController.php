<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\OrderItemRepository;
use Vie\Support\CostVisibility;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\OrderItemValidation;

final class OrderItemController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(OrderItemRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated(CostVisibility::stripItemRows($result['data']), $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(OrderItemRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Order Item');
        }

        return ResponseEnvelope::success(CostVisibility::stripItemRow($row));
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $v    = Validator::validate($data, OrderItemValidation::createRules());

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $repo = Container::get(OrderItemRepository::class);
        $row  = $repo->create($v->validated());

        return ResponseEnvelope::success($row, [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(OrderItemRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Order Item');
        }

        $data = $request->get_json_params();
        $v    = Validator::validate($data, OrderItemValidation::updateRules($id));

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $row = $repo->update($id, $v->validated());

        return ResponseEnvelope::success($row);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(OrderItemRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Order Item');
        }

        $repo->delete($id);

        return new \WP_REST_Response(null, 204);
    }
}
