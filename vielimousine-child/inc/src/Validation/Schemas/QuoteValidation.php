<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class QuoteValidation
{
    public static function rules(): array
    {
        return [
            'room_id'      => 'required|int|exists:vie_room,id',
            'checkin'      => 'required|date',
            'checkout'     => 'required|date',
            'adults'       => 'required|int|min:1|max:20',
            'child_ages'   => 'nullable|array|max_items:10',
            'user_rooms'   => 'nullable|int|min:0|max:10',
            'booking_type' => 'required|string|in:room,combo',
            'coupon_code'  => 'nullable|string|max:50',
            'route_id'     => 'nullable|int|min:0',
        ];
    }

    public static function crossValidate(array $data): array
    {
        $errors = [];

        $checkin  = $data['checkin']  ?? null;
        $checkout = $data['checkout'] ?? null;
        if ($checkin !== null && $checkout !== null) {
            $in  = strtotime((string) $checkin);
            $out = strtotime((string) $checkout);
            if ($in !== false && $out !== false) {
                if ($out <= $in) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => 'checkout',
                        'message' => 'checkout phải sau checkin',
                    ];
                } else {
                    $nights = (int) (($out - $in) / 86400);
                    if ($nights > 30) {
                        $errors[] = [
                            'code'    => 'validation_error',
                            'field'   => 'checkout',
                            'message' => 'Số đêm không được vượt quá 30',
                        ];
                    }
                }
            }
        }

        $childAges = $data['child_ages'] ?? [];
        if (is_array($childAges)) {
            foreach ($childAges as $i => $age) {
                $age = (int) $age;
                if ($age < 0 || $age > 17) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => "child_ages.{$i}",
                        'message' => 'Tuổi trẻ em phải trong khoảng 0–17',
                    ];
                }
            }
        }

        return $errors;
    }
}
