<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class SurchargePriceValidation
{
    public static function createRules(): array
    {
        return [
            'surcharge_id' => 'required|int|exists:vie_surcharge,id',
            'date'         => 'required|date',
            'amount'       => 'required|float|min:0',
            'is_active'    => 'nullable|bool',
        ];
    }

    public static function updateRules(int $id): array
    {
        return [
            'surcharge_id' => 'nullable|int|exists:vie_surcharge,id',
            'date'         => 'nullable|date',
            'amount'       => 'nullable|float|min:0',
            'is_active'    => 'nullable|bool',
        ];
    }
}
