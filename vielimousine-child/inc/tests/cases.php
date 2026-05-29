<?php
declare(strict_types=1);

/**
 * Test cases khớp workbook §13.2 — pricing engine.
 *
 * Mapping rooms (seed):
 *   Deluxe Sea View (Premier 2 chuẩn): included=2, max_adults=3, max_children=2, free_children_count=1
 *   Family Suite (free=2):              included=4, max_adults=4, max_children=3, free_children_count=2
 *
 * Hotel "The Cap" config: ticket_free_children_max_age=5, ticket_free_children_count=1
 *
 * `expected` chỉ assert counts + cờ (khớp đúng workbook §13.2);
 * không assert VND tuyệt đối vì seed có weekend multiplier ngẫu nhiên.
 */

if (!isset($GLOBALS['wpdb'])) {
    throw new RuntimeException('Test cases must be loaded inside WP context');
}

$wpdb = $GLOBALS['wpdb'];

$premierRoom = $wpdb->get_var(
    "SELECT id FROM {$wpdb->prefix}vie_room WHERE name = 'Deluxe Sea View' LIMIT 1"
);
$familyRoom  = $wpdb->get_var(
    "SELECT id FROM {$wpdb->prefix}vie_room WHERE name = 'Family Suite' LIMIT 1"
);

if (!$premierRoom || !$familyRoom) {
    throw new RuntimeException('Required seed rooms not found. Run inc/seed/run.php first.');
}

$premierRoom = (int) $premierRoom;
$familyRoom  = (int) $familyRoom;

$today    = (new DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable('today +1 day', wp_timezone()))->format('Y-m-d');

return [
    [
        'name'     => 'Case 1: 2NL + 1 bé 5T, Room',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5],
            'user_rooms'   => 0,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 1,
            'requires_quote'     => false,
            'ticket_subtotal'    => 0,
        ],
        'extra_check' => 'first_child_free',
    ],
    [
        'name'     => 'Case 2: 2NL + 2 bé (5,4), Room (free=1) — 1 bé tính phí',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5, 4],
            'user_rooms'   => 0,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 2,
            'requires_quote'     => false,
        ],
        'extra_check' => 'one_free_one_charged',
    ],
    [
        'name'     => 'Case 3: 2NL + 2 bé (5,4), Family Suite (free=2) — cả 2 miễn',
        'request'  => [
            'room_id'      => $familyRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5, 4],
            'user_rooms'   => 0,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 2,
            'requires_quote'     => false,
        ],
        'extra_check' => 'both_children_free',
    ],
    [
        'name'     => 'Case 4: 2NL + 1 bé 7T, Room — bé vẫn là trẻ em',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [7],
            'user_rooms'   => 0,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 1,
            'requires_quote'     => false,
        ],
    ],
    [
        'name'     => 'Case 5: 3NL + 2 bé (5,9), Premier — vẫn 1 phòng (max_children=2)',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 3,
            'child_ages'   => [5, 9],
            'user_rooms'   => 0,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 3,
            'effective_children' => 2,
            'requires_quote'     => false,
        ],
    ],
    [
        'name'     => 'Case 6: 2NL + 1 bé 5T, Combo — 1 vé miễn',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 1,
            'seat_count'         => 3,
            'billable_seats'     => 2,
            'free_child_seats'   => 1,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 7: 2NL + 1 bé 7T, Combo — bé là trẻ trong phòng nhưng tính 1 vé',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [7],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 1,
            'seat_count'         => 3,
            'billable_seats'     => 3,
            'free_child_seats'   => 0,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 8: 2NL + 2 bé (5,4), Combo — 1 vé miễn',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5, 4],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 2,
            'seat_count'         => 4,
            'billable_seats'     => 3,
            'free_child_seats'   => 1,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 9: 2NL + 2 bé (5,7), Combo — bé 5T miễn vé, bé 7T tính vé NL',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [5, 7],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 2,
            'seat_count'         => 4,
            'billable_seats'     => 3,
            'free_child_seats'   => 1,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 10: 2NL + 2 bé (7,8), Combo — vẫn 1 phòng (max_children=2)',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 2,
            'child_ages'   => [7, 8],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 1,
            'effective_adults'   => 2,
            'effective_children' => 2,
            'seat_count'         => 4,
            'billable_seats'     => 4,
            'free_child_seats'   => 0,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 11: 4NL + 3 bé (5,4,9), Combo — 2 phòng do max_children=2; 1 vé miễn',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 4,
            'child_ages'   => [5, 4, 9],
            'user_rooms'   => 0,
            'booking_type' => 'combo',
        ],
        'expected' => [
            'num_rooms'          => 2,
            'effective_adults'   => 4,
            'effective_children' => 3,
            'seat_count'         => 7,
            'billable_seats'     => 6,
            'free_child_seats'   => 1,
            'requires_quote'     => false,
        ],
        'assert_ticket_positive' => true,
    ],
    [
        'name'     => 'Case 12: 1NL + 2 bé (5,4), Premier 1 phòng — bé thứ 2 KHÔNG free (#1)',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 1,
            'child_ages'   => [5, 4],
            'user_rooms'   => 1,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'      => 1,
            'requires_quote' => false,
            'rooms_expanded' => false,
        ],
        'extra_check' => 'one_free_one_charged',
    ],
    [
        'name'     => 'Case 13: 4NL + 0 bé, Premier user_rooms=1 — auto tách 2 phòng (#6)',
        'request'  => [
            'room_id'      => $premierRoom,
            'checkin'      => $today,
            'checkout'     => $tomorrow,
            'adults'       => 4,
            'child_ages'   => [],
            'user_rooms'   => 1,
            'booking_type' => 'room',
        ],
        'expected' => [
            'num_rooms'      => 2,
            'requires_quote' => false,
            'rooms_expanded' => true,
        ],
    ],
];
