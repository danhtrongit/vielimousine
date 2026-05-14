<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class RoomPriceBulkValidation
{
    public static function rules(): array
    {
        return [
            'scope'                     => 'required|array',
            'scope.room_ids'            => 'required|array|min_items:1|max_items:100',
            'scope.room_ids.*'          => 'required|int|exists:vie_room,id',
            'scope.date_from'           => 'required|date',
            'scope.date_to'             => 'required|date',
            'scope.weekdays'            => 'nullable|array',
            'values'                    => 'required|array',
            'values.price'              => 'nullable|int|min:0',
            'values.extra_adult_price'  => 'nullable|int|min:0',
            'values.stock'              => 'nullable|int|min:0',
            'values.is_active'          => 'nullable|bool',
            'values.source'             => 'nullable|string|in:manual,weekday_rule,holiday_override,import',
        ];
    }

    public static function crossValidate(array $data): array
    {
        $errors   = [];
        $scope    = $data['scope']  ?? [];
        $values   = $data['values'] ?? [];
        $from     = $scope['date_from'] ?? null;
        $to       = $scope['date_to']   ?? null;
        $weekdays = $scope['weekdays']  ?? null;

        if ($from !== null && $to !== null) {
            $inTs  = strtotime((string) $from);
            $outTs = strtotime((string) $to);
            if ($inTs !== false && $outTs !== false) {
                if ($outTs < $inTs) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => 'scope.date_to',
                        'message' => 'date_to phải >= date_from',
                    ];
                } else {
                    $days = (int) (($outTs - $inTs) / 86400) + 1;
                    if ($days > 365) {
                        $errors[] = [
                            'code'    => 'validation_error',
                            'field'   => 'scope.date_to',
                            'message' => 'Khoảng ngày không được vượt quá 365',
                        ];
                    }
                }
            }
        }

        if (is_array($weekdays)) {
            foreach ($weekdays as $i => $w) {
                $w = (int) $w;
                if ($w < 1 || $w > 7) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => "scope.weekdays.{$i}",
                        'message' => 'Weekday phải trong 1..7 (1=T2, 7=CN)',
                    ];
                }
            }
        }

        $valueKeys = ['price', 'extra_adult_price', 'stock', 'is_active', 'source'];
        $hasValue  = false;
        foreach ($valueKeys as $k) {
            if (array_key_exists($k, $values)) {
                $hasValue = true;
                break;
            }
        }
        if (!$hasValue) {
            $errors[] = [
                'code'    => 'validation_error',
                'field'   => 'values',
                'message' => 'Phải có ít nhất 1 giá trị cần cập nhật',
            ];
        }

        return $errors;
    }
}
