<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\Media\MediaRepository;
use Vie\Service\Media\MediaService;
use Vie\Support\MediaTransformer;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\MediaValidation;

final class MediaController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = Container::get(MediaRepository::class)->paginate($request->get_params());
        $data   = array_map([MediaTransformer::class, 'one'], $result['data']);

        return ResponseEnvelope::paginated($data, $result['pagination']);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $post = get_post($id);
        if ($post === null || $post->post_type !== 'attachment') {
            return ResponseEnvelope::notFound('Media');
        }

        $data            = MediaTransformer::one($post);
        $data['used_in'] = Container::get(MediaService::class)->isUsed($id);

        return ResponseEnvelope::success($data);
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return ResponseEnvelope::error([
                ['code' => 'no_file', 'field' => 'file', 'message' => 'Thiếu file upload (field key: file).'],
            ], 422);
        }

        try {
            $id = Container::get(MediaService::class)->upload($files['file']);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'upload_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 422);
        }

        return ResponseEnvelope::success(MediaTransformer::one(get_post($id)), [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $post = get_post($id);
        if ($post === null || $post->post_type !== 'attachment') {
            return ResponseEnvelope::notFound('Media');
        }

        $data = $request->get_json_params() ?? [];
        $v    = Validator::validate(is_array($data) ? $data : [], MediaValidation::updateRules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        Container::get(MediaService::class)->updateMeta($id, $v->validated());

        return ResponseEnvelope::success(MediaTransformer::one(get_post($id)));
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $post = get_post($id);
        if ($post === null || $post->post_type !== 'attachment') {
            return ResponseEnvelope::notFound('Media');
        }

        try {
            Container::get(MediaService::class)->delete($id);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'in_use', 'field' => null, 'message' => $e->getMessage()],
            ], 409);
        }

        return new \WP_REST_Response(null, 204);
    }
}
