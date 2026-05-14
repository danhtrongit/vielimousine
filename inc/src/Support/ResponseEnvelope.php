<?php
declare(strict_types=1);

namespace Vie\Support;

final class ResponseEnvelope
{
    public static function success(mixed $data, array $meta = [], int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge(self::baseMeta(), $meta),
            'errors'  => null,
        ], $status);
    }

    public static function error(array $errors, int $status = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'data'    => null,
            'meta'    => self::baseMeta(),
            'errors'  => $errors,
        ], $status);
    }

    public static function paginated(array $data, array $pagination, array $meta = []): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge(self::baseMeta(), ['pagination' => $pagination], $meta),
            'errors'  => null,
        ], 200);
    }

    public static function notFound(string $entity = 'Tài nguyên'): \WP_REST_Response
    {
        return self::error([
            ['code' => 'not_found', 'field' => null, 'message' => "{$entity} không tồn tại"],
        ], 404);
    }

    private static function baseMeta(): array
    {
        return [
            'request_id' => 'req_' . wp_generate_uuid4(),
            'timestamp'  => wp_date('c'),
        ];
    }
}
