<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\RoomRepository;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\RoomValidation;

final class RoomController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(RoomRepository::class);
        $result = $repo->all($request->get_params());

        $data = array_map([self::class, 'enrichThumbnail'], $result['data']);

        return ResponseEnvelope::paginated($data, $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(RoomRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Room');
        }

        return ResponseEnvelope::success(self::enrichThumbnail($row));
    }

    /** Add `thumbnail_url` resolved from WP attachment for client display. */
    private static function enrichThumbnail(array $room): array
    {
        $id = (int) ($room['thumbnail_id'] ?? 0);
        $room['thumbnail_url'] = $id > 0
            ? (string) wp_get_attachment_image_url($id, 'thumbnail')
            : null;
        return $room;
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $v    = Validator::validate($data, RoomValidation::createRules());

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $repo = Container::get(RoomRepository::class);
        $row  = $repo->create($v->validated());

        return ResponseEnvelope::success($row, [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(RoomRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Room');
        }

        $data = $request->get_json_params();
        $v    = Validator::validate($data, RoomValidation::updateRules($id));

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $row = $repo->update($id, $v->validated());

        return ResponseEnvelope::success($row);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(RoomRepository::class);

        if ($repo->find($id) === null) {
            return ResponseEnvelope::notFound('Room');
        }

        $repo->delete($id);

        return new \WP_REST_Response(null, 204);
    }
}
