<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

use Vie\Service\Coupon\CouponBulkService;
use Vie\Service\Coupon\CouponCodeGenerator;

final class CouponBulkValidation
{
    public static function rules(): array
    {
        return [
            'quantity'                       => 'required|int|min:1|max:' . CouponBulkService::MAX_QUANTITY,
            'prefix'                         => 'nullable|string|max:20',
            'suffix'                         => 'nullable|string|max:20',
            'random_length'                  => 'nullable|int|min:' . CouponCodeGenerator::MIN_RANDOM_LENGTH
                . '|max:' . CouponCodeGenerator::MAX_RANDOM_LENGTH,

            'template'                       => 'required|array',
            'template.description'           => 'nullable|string',
            'template.type'                  => 'required|string|in:percentage,fixed',
            'template.value'                 => 'required|float|min:0',
            'template.min_order'             => 'nullable|float|min:0',
            'template.max_discount'          => 'nullable|float|min:0',
            'template.usage_limit'           => 'nullable|int|min:0',
            'template.usage_limit_per_user'  => 'nullable|int|min:0',
            'template.valid_from'            => 'nullable|string',
            'template.valid_to'              => 'nullable|string',
            'template.hotel_ids'             => 'nullable|array',
            'template.hotel_ids.*'           => 'required|int|exists:vie_hotel,id',
            'template.room_ids'              => 'nullable|array',
            'template.room_ids.*'            => 'required|int|exists:vie_room,id',
            'template.booking_types'         => 'nullable|array',
            'template.booking_types.*'       => 'required|string|in:room,combo',
            'template.is_active'             => 'nullable|bool',
            'template.sales_only'            => 'nullable|bool',
        ];
    }

    /**
     * Ràng buộc liên trường — chạy sau rules() vì cần nhiều field cùng lúc.
     *
     * @return array<int,array{code: string, field: ?string, message: string}>
     */
    public static function crossValidate(array $data): array
    {
        $errors   = [];
        $template = is_array($data['template'] ?? null) ? $data['template'] : [];

        $prefix = CouponCodeGenerator::normalizeAffix($data['prefix'] ?? '');
        $suffix = CouponCodeGenerator::normalizeAffix($data['suffix'] ?? '');
        $length = (int) ($data['random_length'] ?? 8);

        foreach (['prefix' => $prefix, 'suffix' => $suffix] as $field => $affix) {
            if (!preg_match(CouponCodeGenerator::AFFIX_PATTERN, $affix)) {
                $errors[] = [
                    'code'    => 'validation_error',
                    'field'   => $field,
                    'message' => 'Chỉ cho phép chữ, số, gạch ngang và gạch dưới',
                ];
            }
        }

        $codeLength = strlen($prefix) + $length + strlen($suffix);
        if ($codeLength > CouponCodeGenerator::CODE_MAX_LENGTH) {
            $errors[] = [
                'code'    => 'validation_error',
                'field'   => 'random_length',
                'message' => sprintf(
                    'Tổng độ dài mã (%d) vượt %d ký tự — rút ngắn tiền tố/hậu tố.',
                    $codeLength,
                    CouponCodeGenerator::CODE_MAX_LENGTH
                ),
            ];
        }

        if (($template['type'] ?? '') === 'percentage' && (float) ($template['value'] ?? 0) > 100) {
            $errors[] = [
                'code'    => 'validation_error',
                'field'   => 'template.value',
                'message' => 'Giảm theo phần trăm không được vượt 100',
            ];
        }

        $from = $template['valid_from'] ?? null;
        $to   = $template['valid_to']   ?? null;
        if (is_string($from) && is_string($to) && $from !== '' && $to !== '') {
            $fromTs = strtotime($from);
            $toTs   = strtotime($to);
            if ($fromTs !== false && $toTs !== false && $toTs < $fromTs) {
                $errors[] = [
                    'code'    => 'validation_error',
                    'field'   => 'template.valid_to',
                    'message' => 'Hiệu lực đến phải sau hiệu lực từ',
                ];
            }
        }

        return $errors;
    }
}
