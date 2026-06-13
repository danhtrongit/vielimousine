<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\ActivityLogRepository;
use Vie\Service\Auth\RoleInstaller;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\UserValidation;

final class UserController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $vieRoles = [
            'administrator',
            RoleInstaller::ROLE_HOTEL_MANAGER,
            RoleInstaller::ROLE_SALES,
        ];

        $users = get_users([
            'role__in' => $vieRoles,
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ]);

        $data = array_map(static function (\WP_User $u): array {
            return [
                'id'           => (int) $u->ID,
                'display_name' => (string) $u->display_name,
                'email'        => (string) $u->user_email,
                'roles'        => array_values(array_filter(
                    $u->roles,
                    static fn($r) => str_starts_with((string) $r, 'vie_') || $r === 'administrator'
                )),
            ];
        }, $users);

        return ResponseEnvelope::success($data);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $user = get_userdata($id);

        if ($user === false) {
            return ResponseEnvelope::notFound('Người dùng');
        }

        return ResponseEnvelope::success(self::transform($user));
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, UserValidation::createRules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }
        $clean = $v->validated();

        if (username_exists((string) $clean['user_login'])) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'user_login', 'message' => 'Tên đăng nhập đã tồn tại'],
            ], 422);
        }
        if (email_exists((string) $clean['email'])) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'email', 'message' => 'Email đã tồn tại'],
            ], 422);
        }

        $result = wp_insert_user([
            'user_login'   => (string) $clean['user_login'],
            'user_pass'    => (string) $clean['password'],
            'user_email'   => (string) $clean['email'],
            'display_name' => (string) $clean['display_name'],
            'role'         => (string) $clean['role'],
        ]);

        if (is_wp_error($result)) {
            return ResponseEnvelope::error([
                ['code' => (string) $result->get_error_code(), 'field' => null, 'message' => $result->get_error_message()],
            ], 422);
        }

        $user = get_userdata((int) $result);
        $row  = self::transform($user);

        Container::get(ActivityLogRepository::class)->create([
            'actor_user_id' => (int) get_current_user_id(),
            'entity_type'   => 'user',
            'entity_id'     => (int) $result,
            'action'        => 'create',
            'before_json'   => null,
            'after_json'    => $row,
            'ip'            => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return ResponseEnvelope::success($row, [], 201);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $user = get_userdata($id);

        if ($user === false) {
            return ResponseEnvelope::notFound('Người dùng');
        }
        if (in_array('administrator', (array) $user->roles, true)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Không thể chỉnh sửa tài khoản quản trị viên'],
            ], 403);
        }

        $data = $request->get_json_params();
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, UserValidation::updateRules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }
        $clean = $v->validated();

        if (isset($clean['role'])
            && $id === (int) get_current_user_id()
            && !in_array((string) $clean['role'], (array) $user->roles, true)
        ) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => 'role', 'message' => 'Không thể thay đổi vai trò của chính mình'],
            ], 403);
        }

        if (isset($clean['email'])) {
            $existing = email_exists((string) $clean['email']);
            if ($existing !== false && (int) $existing !== $id) {
                return ResponseEnvelope::error([
                    ['code' => 'validation_error', 'field' => 'email', 'message' => 'Email đã tồn tại'],
                ], 422);
            }
        }

        $before = self::transform($user);

        $args = ['ID' => $id];
        if (isset($clean['email'])) {
            $args['user_email'] = (string) $clean['email'];
        }
        if (isset($clean['display_name'])) {
            $args['display_name'] = (string) $clean['display_name'];
        }
        if (isset($clean['role'])) {
            $args['role'] = (string) $clean['role'];
        }
        if (isset($clean['password'])) {
            $args['user_pass'] = (string) $clean['password'];
        }

        $result = wp_update_user($args);
        if (is_wp_error($result)) {
            return ResponseEnvelope::error([
                ['code' => (string) $result->get_error_code(), 'field' => null, 'message' => $result->get_error_message()],
            ], 422);
        }

        $row = self::transform(get_userdata($id));

        Container::get(ActivityLogRepository::class)->create([
            'actor_user_id' => (int) get_current_user_id(),
            'entity_type'   => 'user',
            'entity_id'     => $id,
            'action'        => 'update',
            'before_json'   => $before,
            'after_json'    => $row,
            'ip'            => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return ResponseEnvelope::success($row);
    }

    public static function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        if ($id === (int) get_current_user_id()) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Không thể xóa tài khoản của chính mình'],
            ], 403);
        }

        $user = get_userdata($id);
        if ($user === false) {
            return ResponseEnvelope::notFound('Người dùng');
        }
        if (in_array('administrator', (array) $user->roles, true)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Không thể xóa tài khoản quản trị viên'],
            ], 403);
        }

        $before = self::transform($user);

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        if (!wp_delete_user($id)) {
            return ResponseEnvelope::error([
                ['code' => 'delete_failed', 'field' => null, 'message' => 'Không thể xóa người dùng'],
            ], 500);
        }

        Container::get(ActivityLogRepository::class)->create([
            'actor_user_id' => (int) get_current_user_id(),
            'entity_type'   => 'user',
            'entity_id'     => $id,
            'action'        => 'delete',
            'before_json'   => $before,
            'after_json'    => null,
            'ip'            => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return new \WP_REST_Response(null, 204);
    }

    private static function transform(\WP_User $user): array
    {
        $roles = array_values((array) $user->roles);

        return [
            'id'           => (int) $user->ID,
            'user_login'   => (string) $user->user_login,
            'email'        => (string) $user->user_email,
            'display_name' => (string) $user->display_name,
            'role'         => (string) ($roles[0] ?? ''),
            'created'      => (string) $user->user_registered,
        ];
    }
}
