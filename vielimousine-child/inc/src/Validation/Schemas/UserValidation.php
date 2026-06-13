<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

use Vie\Service\Auth\RoleInstaller;

final class UserValidation
{
    public const ALLOWED_ROLES = [
        RoleInstaller::ROLE_SALES,
        RoleInstaller::ROLE_HOTEL_MANAGER,
    ];

    public static function createRules(): array
    {
        return [
            'user_login'   => 'required|string|min:3|max:60',
            'email'        => 'required|email',
            'password'     => 'required|string|min:8|max:255',
            'display_name' => 'required|string|max:255',
            'role'         => 'required|string|in:' . implode(',', self::ALLOWED_ROLES),
        ];
    }

    public static function updateRules(): array
    {
        return [
            'email'        => 'nullable|email',
            'display_name' => 'nullable|string|max:255',
            'role'         => 'nullable|string|in:' . implode(',', self::ALLOWED_ROLES),
            'password'     => 'nullable|string|min:8|max:255',
        ];
    }
}
