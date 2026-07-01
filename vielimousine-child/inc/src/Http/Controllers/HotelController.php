<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\HotelRepository;
use Vie\Repository\RepositoryException;
use Vie\Service\Hotel\HotelDeleteService;
use Vie\Service\Hotel\HotelInUseException;
use Vie\Service\Hotel\HotelSyncService;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\HotelValidation;

final class HotelController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(HotelRepository::class);
        $result = $repo->all($request->get_params());

        return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
            'sort'              => $result['sort'],
            'filters_applied'   => $result['filters_applied'],
            'available_sorts'   => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(HotelRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Hotel');
        }

        return ResponseEnvelope::success($row);
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $v    = Validator::validate($data, HotelValidation::createRules());

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $clean = $v->validated();

        // Không có quyền giá → không cho đặt giá vé/chính sách vé (dùng mặc định).
        if (!current_user_can('vie_manage_pricing')) {
            unset($clean['default_ticket_price'], $clean['ticket_free_children_count'], $clean['ticket_free_children_max_age']);
        }

        // Auto-create WP post nếu SPA không truyền post_id
        if (empty($clean['post_id'])) {
            $sync = Container::get(HotelSyncService::class);
            $clean['post_id'] = $sync->createPost(
                (string) ($clean['name'] ?? ''),
                isset($clean['slug']) ? (string) $clean['slug'] : null,
                isset($clean['description']) ? (string) $clean['description'] : null,
            );
        }

        $repo  = Container::get(HotelRepository::class);
        $hotel = $repo->create($clean);

        return ResponseEnvelope::success($hotel, [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(HotelRepository::class);
        $row  = $repo->find($id);

        if ($row === null) {
            return ResponseEnvelope::notFound('Hotel');
        }

        $data = $request->get_json_params();
        $v    = Validator::validate($data, HotelValidation::updateRules($id));

        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        // Không có quyền giá → bỏ qua thay đổi giá vé/chính sách vé.
        $clean = $v->validated();
        if (!current_user_can('vie_manage_pricing')) {
            unset($clean['default_ticket_price'], $clean['ticket_free_children_count'], $clean['ticket_free_children_max_age']);
        }

        $hotel = $repo->update($id, $clean);

        // Push lên WP post (no-op nếu post_id rỗng hoặc đang trong vòng sync)
        Container::get(HotelSyncService::class)->pushToPost($hotel);

        return ResponseEnvelope::success($hotel);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $repo = Container::get(HotelRepository::class);
        $row  = $repo->find($id);

        if ($row === null) {
            return ResponseEnvelope::notFound('Hotel');
        }

        try {
            Container::get(HotelDeleteService::class)
                ->delete($id, (int) get_current_user_id());
        } catch (HotelInUseException $e) {
            return ResponseEnvelope::error([
                ['code' => 'hotel_in_use', 'field' => null, 'message' => $e->getMessage()],
            ], 409);
        }

        return new \WP_REST_Response(null, 204);
    }
}
