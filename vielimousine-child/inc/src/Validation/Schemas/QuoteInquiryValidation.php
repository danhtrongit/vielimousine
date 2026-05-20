<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class QuoteInquiryValidation
{
    public static function rules(): array
    {
        return [
            'customer'         => 'required|array',
            'customer.phone'   => 'required|phone',
            'customer.name'    => 'required|string|max:255',
            'customer.email'   => 'nullable|email',
            'note'             => 'nullable|string|max:2000',
            'item'             => 'required|array',
            'item.room_id'     => 'required|int|exists:vie_room,id',
            'item.booking_type'=> 'required|string|in:room,combo',
            'item.checkin'     => 'required|date',
            'item.checkout'    => 'required|date',
            'item.adults'      => 'required|int|min:1|max:20',
            'item.child_ages'  => 'nullable|array|max_items:10',
            'item.user_rooms'  => 'nullable|int|min:0|max:10',
        ];
    }

    public static function crossValidate(array $data): array
    {
        $errors = [];
        $item   = $data['item'] ?? [];
        if (!is_array($item)) {
            return $errors;
        }

        $checkin  = $item['checkin']  ?? null;
        $checkout = $item['checkout'] ?? null;
        if ($checkin !== null && $checkout !== null) {
            $in  = strtotime((string) $checkin);
            $out = strtotime((string) $checkout);
            if ($in !== false && $out !== false) {
                if ($out <= $in) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => 'item.checkout',
                        'message' => 'Checkout phải sau checkin',
                    ];
                } elseif ((int) (($out - $in) / 86400) > 30) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => 'item.checkout',
                        'message' => 'Số đêm không được vượt quá 30',
                    ];
                }
            }
        }

        $ages = $item['child_ages'] ?? [];
        if (is_array($ages)) {
            foreach ($ages as $j => $age) {
                $age = (int) $age;
                if ($age < 0 || $age > 17) {
                    $errors[] = [
                        'code'    => 'validation_error',
                        'field'   => "item.child_ages.{$j}",
                        'message' => 'Tuổi trẻ em phải trong khoảng 0–17',
                    ];
                }
            }
        }

        return $errors;
    }
}
