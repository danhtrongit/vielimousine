<?php
declare(strict_types=1);

namespace Vie\Schema\Seeders;

final class RoomSeeder
{
    public static function run(\wpdb $wpdb): void
    {
        $hotelTable = $wpdb->prefix . 'vie_hotel';
        $roomTable  = $wpdb->prefix . 'vie_room';
        $priceTable = $wpdb->prefix . 'vie_room_price';

        $hotelId = (int) $wpdb->get_var("SELECT id FROM {$hotelTable} WHERE slug = 'the-cap-vung-tau' LIMIT 1");

        if (!$hotelId) {
            echo "Hotel not found. Run HotelSeeder first.\n";
            return;
        }

        $existingRoom = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$roomTable} WHERE hotel_id = %d LIMIT 1", $hotelId)
        );

        if ($existingRoom) {
            echo "Rooms already seeded for hotel {$hotelId}, skipping.\n";
            return;
        }

        $rooms = [
            [
                'name'                => 'Standard Double',
                'included_adults'     => 2,
                'max_adults'          => 2,
                'max_children'        => 1,
                'base_price'          => 1200000,
                'extra_adult_price'   => 300000,
                'free_children_count' => 1,
                'free_children_max_age' => 5,
                'bed_type'            => 'double',
                'bed_count'           => 1,
                'area'                => 28,
                'view'                => 'thành phố',
                'amenities'           => wp_json_encode(['wifi', 'tv', 'minibar', 'điều hòa'], JSON_UNESCAPED_UNICODE),
                'sort_order'          => 1,
            ],
            [
                'name'                => 'Deluxe Sea View',
                'included_adults'     => 2,
                'max_adults'          => 3,
                'max_children'        => 2,
                'base_price'          => 1800000,
                'extra_adult_price'   => 400000,
                'free_children_count' => 1,
                'free_children_max_age' => 5,
                'bed_type'            => 'king',
                'bed_count'           => 1,
                'area'                => 35,
                'view'                => 'biển',
                'amenities'           => wp_json_encode(['wifi', 'tv', 'minibar', 'điều hòa', 'bồn tắm', 'ban công'], JSON_UNESCAPED_UNICODE),
                'sort_order'          => 2,
            ],
            [
                'name'                => 'Family Suite',
                'included_adults'     => 4,
                'max_adults'          => 4,
                'max_children'        => 3,
                'base_price'          => 3000000,
                'extra_adult_price'   => 500000,
                'free_children_count' => 2,
                'free_children_max_age' => 5,
                'bed_type'            => 'twin',
                'bed_count'           => 2,
                'area'                => 55,
                'view'                => 'biển',
                'amenities'           => wp_json_encode(['wifi', 'tv', 'minibar', 'điều hòa', 'bồn tắm', 'ban công', 'phòng khách'], JSON_UNESCAPED_UNICODE),
                'sort_order'          => 3,
            ],
        ];

        foreach ($rooms as $room) {
            $wpdb->insert($roomTable, array_merge($room, [
                'hotel_id'  => $hotelId,
                'is_active' => 1,
            ]));

            $roomId    = (int) $wpdb->insert_id;
            $basePrice = $room['base_price'];
            $extraPrice = $room['extra_adult_price'];

            echo "Room seeded: {$room['name']} (ID={$roomId})\n";

            self::seedPrices($wpdb, $priceTable, $roomId, $basePrice, $extraPrice);
        }
    }

    private static function seedPrices(
        \wpdb $wpdb,
        string $table,
        int $roomId,
        int $basePrice,
        int $extraPrice,
    ): void {
        $today = new \DateTimeImmutable('today', wp_timezone());

        for ($i = 0; $i < 30; $i++) {
            $date    = $today->modify("+{$i} days");
            $dayOfWeek = (int) $date->format('N'); // 1=Mon ... 7=Sun
            $isWeekend = $dayOfWeek >= 5; // Fri, Sat, Sun

            $price      = $isWeekend ? (int) round($basePrice * 1.2 / 1000) * 1000 : $basePrice;
            $extraAP    = $isWeekend ? (int) round($extraPrice * 1.2 / 1000) * 1000 : $extraPrice;

            $wpdb->insert($table, [
                'room_id'           => $roomId,
                'date'              => $date->format('Y-m-d'),
                'price'             => $price,
                'extra_adult_price' => $extraAP,
                'stock'             => 5,
                'is_active'         => 1,
                'source'            => 'manual',
            ], ['%d', '%s', '%d', '%d', '%d', '%d', '%s']);
        }

        echo "  → 30 days of prices seeded for room {$roomId}\n";
    }
}
