<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class TokenValidation
{
    public static function createRules(): array
    {
        return [
            'user_id'    => 'required|int',
            'hash'       => 'required|string|max:64',
            'family'     => 'required|string|max:36',
            'ip'         => 'nullable|string|max:45',
            'ua'         => 'nullable|string|max:500',
            'expires_at' => 'required|string',
        ];
    }
}
