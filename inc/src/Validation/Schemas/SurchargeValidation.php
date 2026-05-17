<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class SurchargeValidation
{
    public static function createRules(): array
    {
        return [
            'room_id'    => 'required|int|exists:vie_room,id',
            'guest_type' => 'required|string|in:adult,child',
            'label'      => 'required|string|max:100',
            'age_from'        => 'required|int|min:0',
            'age_to'          => 'required|int|min:0',
            'child_index_min' => 'nullable|int|min:1|max:20',
            'child_index_max' => 'nullable|int|min:1|max:20',
            'amount'          => 'required|float|min:0',
            'is_free'         => 'nullable|bool',
            'sort_order'      => 'nullable|int|min:0',
            'is_active'       => 'nullable|bool',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'room_id'         => 'nullable|int|exists:vie_room,id',
            'guest_type'      => 'nullable|string|in:adult,child',
            'label'           => 'nullable|string|max:100',
            'age_from'        => 'nullable|int|min:0',
            'age_to'          => 'nullable|int|min:0',
            'child_index_min' => 'nullable|int|min:1|max:20',
            'child_index_max' => 'nullable|int|min:1|max:20',
            'amount'          => 'nullable|float|min:0',
            'is_free'         => 'nullable|bool',
            'sort_order'      => 'nullable|int|min:0',
            'is_active'       => 'nullable|bool',
        ];
    }
}
