<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class MediaValidation
{
    public static function updateRules(): array
    {
        return [
            'title'   => 'nullable|string|max:255',
            'alt'     => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
        ];
    }
}
