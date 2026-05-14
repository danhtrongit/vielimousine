<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class ActivityLogValidation
{
    public static function createRules(): array
    {
        return [
            'actor_user_id' => 'required|int',
            'entity_type'   => 'required|string|max:50',
            'entity_id'     => 'required|int',
            'action'        => 'required|string|max:50',
            'before_json'   => 'nullable|array',
            'after_json'    => 'nullable|array',
            'ip'            => 'nullable|string|max:45',
            'user_agent'    => 'nullable|string|max:500',
        ];
    }
}
