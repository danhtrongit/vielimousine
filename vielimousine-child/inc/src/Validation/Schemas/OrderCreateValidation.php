<?php
declare(strict_types=1);

namespace Vie\Validation\Schemas;

final class OrderCreateValidation
{
    public static function rules(): array
    {
        return [
            'customer'              => 'required|array',
            'customer.phone'        => 'required|phone',
            'customer.name'         => 'required|string|max:255',
            'customer.email'        => 'nullable|email',
            'customer.vat'          => 'nullable|array',
            'source'                => 'required|string|max:50',
            'sales_user_id'         => 'nullable|int',
            'customer_note'         => 'nullable|string',
            'pickup'                => 'nullable|array',
            'dropoff'               => 'nullable|array',
            'items'                 => 'required|array|min_items:1|max_items:5',
            'items.*.room_id'       => 'required|int|exists:vie_room,id',
            'items.*.booking_type'  => 'required|string|in:room,combo',
            'items.*.checkin'       => 'required|date',
            'items.*.checkout'      => 'required|date',
            'items.*.adults'        => 'required|int|min:1|max:20',
            'items.*.child_ages'    => 'nullable|array|max_items:10',
            'items.*.user_rooms'    => 'nullable|int|min:0|max:10',
            'coupon_code'           => 'nullable|string|max:50',
            'voucher_code'          => 'nullable|string|max:100',
            'payment_method'        => 'nullable|string|max:30',
        ];
    }

    public static function crossValidate(array $data): array
    {
        $errors = [];
        $items  = $data['items'] ?? [];

        if (is_array($items)) {
            foreach ($items as $i => $item) {
                $checkin  = $item['checkin']  ?? null;
                $checkout = $item['checkout'] ?? null;
                if ($checkin !== null && $checkout !== null) {
                    $in  = strtotime((string) $checkin);
                    $out = strtotime((string) $checkout);
                    if ($in !== false && $out !== false) {
                        if ($out <= $in) {
                            $errors[] = [
                                'code'    => 'validation_error',
                                'field'   => "items.{$i}.checkout",
                                'message' => 'Checkout phải sau checkin',
                            ];
                            continue;
                        }
                        $nights = (int) (($out - $in) / 86400);
                        if ($nights > 30) {
                            $errors[] = [
                                'code'    => 'validation_error',
                                'field'   => "items.{$i}.checkout",
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
                                'field'   => "items.{$i}.child_ages.{$j}",
                                'message' => 'Tuổi trẻ em phải trong khoảng 0–17',
                            ];
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
