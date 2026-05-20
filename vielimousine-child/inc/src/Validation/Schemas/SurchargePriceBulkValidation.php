<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class SurchargePriceBulkValidation
{
    public static function rules(): array
    {
        return [
            'scope'                  => 'required|array',
            'scope.surcharge_ids'    => 'required|array|min_items:1|max_items:200',
            'scope.surcharge_ids.*'  => 'required|int|exists:vie_surcharge,id',
            'scope.date_from'        => 'required|date',
            'scope.date_to'          => 'required|date',
            'scope.weekdays'         => 'nullable|array',
            'values'                 => 'required|array',
            'values.amount'          => 'nullable|int|min:0',
            'values.is_active'       => 'nullable|bool',
        ];
    }

    public static function crossValidate(array $data): array
    {
        $errors   = [];
        $scope    = $data['scope']  ?? [];
        $values   = $data['values'] ?? [];
        $from     = $scope['date_from'] ?? null;
        $to       = $scope['date_to']   ?? null;

        if ($from !== null && $to !== null) {
            $inTs  = strtotime((string) $from);
            $outTs = strtotime((string) $to);
            if ($inTs !== false && $outTs !== false) {
                if ($outTs < $inTs) {
                    $errors[] = ['code' => 'validation_error', 'field' => 'scope.date_to', 'message' => 'date_to phải >= date_from'];
                } elseif ((($outTs - $inTs) / 86400) + 1 > 365) {
                    $errors[] = ['code' => 'validation_error', 'field' => 'scope.date_to', 'message' => 'Khoảng ngày không vượt quá 365'];
                }
            }
        }

        if (!array_key_exists('amount', $values) && !array_key_exists('is_active', $values)) {
            $errors[] = ['code' => 'validation_error', 'field' => 'values', 'message' => 'Phải có amount hoặc is_active'];
        }

        return $errors;
    }
}
