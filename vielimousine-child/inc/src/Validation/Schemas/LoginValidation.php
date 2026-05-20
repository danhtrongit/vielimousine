<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class LoginValidation
{
    public static function rules(): array
    {
        return [
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:1|max:255',
            'remember' => 'nullable|bool',
        ];
    }
}
