<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Service\Auth\RoleInstaller;
use Vie\Support\ResponseEnvelope;

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
}
