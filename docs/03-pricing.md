# 03 — Pricing Engine

## 3.0. Số phòng — `user_rooms` vs `num_rooms` vs `quantity`

| Tên | Nơi xuất hiện | Ý nghĩa |
|---|---|---|
| `user_rooms` | input `QuoteRequest`, `POST /orders` items | Số phòng khách **yêu cầu**. `0` = để hệ thống auto. |
| `num_rooms` / `rooms_allocated` | output `PriceBreakdown`, `pricing_snapshot` | Số phòng hệ thống **quyết định** (auto từ guest mix hoặc echo lại `user_rooms`). |
| `quantity` | column `vie_order_item.quantity` | Server-side: **= `num_rooms`** sau khi tạo đơn. Client KHÔNG được gửi `quantity` khi POST `/orders` (sẽ bị bỏ qua). |

> Một dòng item = một loại phòng × số phòng cùng giá. Multi-item dùng cho khách mix loại phòng / hotel.

## 3.1. Inputs (`QuoteRequest`)

```php
final readonly class QuoteRequest {
    public function __construct(
        public int     $roomId,
        public int     $hotelId,         // resolve từ room nếu không có
        public string  $checkin,         // Y-m-d
        public string  $checkout,        // Y-m-d (exclusive)
        public int     $adults,
        public array   $childAges,       // [5,7,9]
        public int     $userRooms,       // 0 = auto
        public string  $bookingType,     // 'room' | 'combo'
        public ?string $couponCode = null,
        public int     $routeId = 0,     // combo, default 0
    ) {}
}
```

Validator (Service layer):

- `checkout > checkin`
- `nights ≤ 30` (giới hạn ứng dụng)
- `adults ≥ 1 && adults ≤ 20`
- `count(childAges) ≤ 10`
- mọi tuổi `0 ≤ age ≤ 17`
- `bookingType ∈ {'room','combo'}`

## 3.2. Định nghĩa giá

| Khái niệm | Nguồn |
|---|---|
| Giá phòng / đêm | `vie_room_price.price` (theo `room_id × date`) |
| Phụ thu NL / đêm | `vie_room_price.extra_adult_price` |
| Phụ thu trẻ em / đêm | `vie_surcharge` rule + `vie_surcharge_price` override |
| Giá vé khứ hồi / khách | `vie_ticket_price` (theo `hotel_id × date`) — combo dùng giá ngày checkin |
| Combo tổng | tiền phòng + tiền vé + phụ thu (như Room Only, không double-count) |

> Không có `price_combo_addon` riêng. Combo chỉ là **room + ticket** cộng dồn — đơn giản, khớp workbook §57: "Cộng vào giá vé khứ hồi theo từng tuyến/từng khách sạn".

## 3.3. Phân phòng (`RoomAllocation`)

Input: `room.included_adults`, `room.max_adults`, `room.max_children`, `adults`, `childAges`, `userRooms`.

### Bước 1: Quy đổi bé ≥ ngưỡng phòng → NL

Phân phòng dùng **ngưỡng phòng** `vie_room.free_children_max_age` (default 5). Vé xe dùng **ngưỡng vé** `vie_hotel.ticket_free_children_max_age` (default 5) — xem §3.5. Hai ngưỡng độc lập nhau (xem [02-database.md §2.5](02-database.md#25-vie_room)).

```php
$roomAdultAgeFloor = $room->free_children_max_age + 1; // 5 + 1 = 6 — ngưỡng cho PHÒNG
$childrenUnderFloor = [];
$convertedAdults    = 0;
foreach ($childAges as $age) {
    if ($age >= $roomAdultAgeFloor) $convertedAdults++;
    else                            $childrenUnderFloor[] = $age;
}
$effectiveAdults   = $adults + $convertedAdults;
$effectiveChildren = count($childrenUnderFloor);
```

### Bước 2: Tính số phòng

```php
if ($userRooms > 0) {
    $numRooms = $userRooms;
} else {
    $capacity     = $room->max_adults; // = included + extra
    $byAdults     = (int) ceil($effectiveAdults / $capacity);
    $byChildren   = $room->max_children > 0
        ? (int) ceil($effectiveChildren / $room->max_children)
        : 1;
    $numRooms = max($byAdults, $byChildren, 1);
}
```

### Bước 3: Validate hard limits

```php
$totalCapacityAdults = $numRooms * $room->max_adults;
$totalCapacityChildren = $numRooms * $room->max_children;
$extraBeds = max(0, $effectiveAdults - $numRooms * $room->included_adults);
$extraBedsAllowed = $numRooms * ($room->max_adults - $room->included_adults);

if ($effectiveAdults > $totalCapacityAdults
    || $effectiveChildren > $totalCapacityChildren
    || $extraBeds > $extraBedsAllowed) {
    $requiresQuote = true;
}
```

`requiresQuote = true` → frontend hiển thị **"Liên hệ báo giá"**, không cho checkout.

## 3.4. Apply child policy (`ChildPolicy`)

Input: `childrenUnderFloor`, `room.free_children_count`, `numRooms`.

```php
usort($childrenUnderFloor, fn($a, $b) => $b <=> $a); // DESC by age
$freeQuota = $room->free_children_count * $numRooms;

$assessments = [];
foreach ($childrenUnderFloor as $i => $age) {
    $assessments[] = new ChildAssessment(
        age: $age,
        isFree: $i < $freeQuota,
        treatedAsAdult: false,
    );
}
foreach ($convertedAdults_list as $age) {
    $assessments[] = new ChildAssessment(
        age: $age,
        isFree: false,
        treatedAsAdult: true,
    );
}
```

`ChildAssessment` dùng để:

- Tính tiền phụ thu trẻ em (xem §3.6).
- Tính số vé miễn phí cho combo (xem §3.5).
- Lưu vào `order_item.pricing_snapshot.child_assessments`.

## 3.5. Vé xe & quy tắc mới (`TicketCalculator`)

Áp dụng khi `bookingType === 'combo'`.

```php
$ticketAgeFloor = $hotel->ticket_free_children_max_age + 1; // 6

$under   = count(array_filter($childAges, fn($a) => $a < $ticketAgeFloor));
$over    = count(array_filter($childAges, fn($a) => $a >= $ticketAgeFloor));

$freeSeats   = min($under, $hotel->ticket_free_children_count); // mặc định 1
$seatCount   = $adults + $over + $under; // tổng chỗ ngồi (cả bé miễn vẫn chiếm ghế)
$billable    = $adults + $over + ($under - $freeSeats);
$ticketPrice = ticket_price_lookup($hotel->id, $checkin) ?? $hotel->default_ticket_price;
$ticketTotal = $billable * $ticketPrice;
```

`seatCount` lưu vào `vie_order_item.ticket_count`. Đây là trường email admin yêu cầu hiển thị ("Số chỗ ngồi").

> `ticket_count` được dùng cho 2 mục đích: (1) tính số ghế thực tế trên xe; (2) làm cơ sở `billable_seats = ticket_count - free_seats` cho doanh thu. Khi cần phân biệt, đọc thêm `pricing_snapshot.billable_seats`.

Bảng kiểm tra khớp workbook (sheet 3 R58–R62):

| Đoàn | under | over | freeSeats | seatCount | billable |
|---|---|---|---|---|---|
| 2 NL + 1 bé 5T | 1 | 0 | 1 | 3 | 2 |
| 2 NL + 1 bé 7T | 0 | 1 | 0 | 3 | 3 |
| 2 NL + 1 bé 5T + 1 bé 4T | 2 | 0 | 1 | 4 | 3 |
| 2 NL + 1 bé 7T + 1 bé 8T | 0 | 2 | 0 | 4 | 4 |

## 3.6. Phụ thu trẻ em (`SurchargeCalculator`)

Cho từng bé `treatedAsAdult = false`:

1. Bỏ qua nếu `isFree = true` (đã được quota miễn phí). MVP **không có cờ `is_mandatory`** trong `vie_surcharge` — nếu nghiệp vụ cần phụ thu bắt buộc kể cả khi miễn (giường phụ, ăn sáng), bổ sung cột này ở phase sau.
2. Tìm rule active `KEY(room_id, guest_type='child', is_active=1)` sort ASC theo `sort_order`. Lấy rule đầu tiên có `age_from ≤ age ≤ age_to`.
3. Nếu rule `is_free = 1` → 0đ.
4. Else: tiền cho mỗi đêm:
   - tra `vie_surcharge_price(surcharge_id, date)` → `amount` (nếu active).
   - không có → `vie_surcharge.amount`.
5. Cộng dồn theo `nights`.

Cho từng bé `treatedAsAdult = true`:

- Bỏ qua phụ thu **trẻ em**, vì đã được tính như NL → có thể dùng `extra_adult_price` nếu chiếm slot extra. Logic chuyển dời sang phần "extra adult beds".

## 3.7. Tiền NL (extra bed)

```php
$extraAdultBeds = max(0, $effectiveAdults - $numRooms * $room->included_adults);
$extraAdultTotal = 0;
foreach ($nights as $date) {
    $row = room_price_lookup($room->id, $date);
    if (!$row || !$row->is_active) {
        $unavailable = true; break;
    }
    $extraAdultTotal += $extraAdultBeds * ($row->extra_adult_price ?? $room->extra_adult_price);
}
```

`$unavailable = true` → trả `requiresQuote` + message "Hết phòng đêm `{date}`".

## 3.8. Tổng hợp

```php
$roomSubtotal       = sum(price * numRooms for each night);
$extraAdultSubtotal = sum extra_adult_price * extraBeds for each night;
$childSurchargeTotal= sum của bé chargeable;
$ticketSubtotal     = (bookingType=='combo') ? billable * ticketPrice : 0;
$subtotal           = roomSubtotal + extraAdultSubtotal + childSurchargeTotal + ticketSubtotal;
$discount           = coupon ? CouponService::calc($coupon, $subtotal) : 0;
$total              = max(0, $subtotal - $discount);
```

Làm tròn `$total` về 1.000đ cuối cùng (`Money::roundVND`).

## 3.9. Output (`PriceBreakdown`)

```php
final readonly class PriceBreakdown {
    public function __construct(
        public int   $numRooms,
        public int   $nights,
        public int   $effectiveAdults,
        public int   $effectiveChildren,
        public int   $extraAdultBeds,
        public int   $seatCount,
        public int   $billableSeats,
        public int   $freeChildSeats,
        public array $nightly,              // [{date, price, extra_adult_price, ticket_price, child_surcharges:[]}]
        public array $childAssessments,
        public int   $roomSubtotal,
        public int   $extraAdultSubtotal,
        public int   $childSurchargeTotal,
        public int   $ticketSubtotal,
        public int   $subtotal,
        public int   $discount,
        public int   $total,
        public int   $costTotal,
        public bool  $requiresQuote,
        public array $messages,             // ['Miễn phí 1 bé dưới 6', ...]
        public ?string $unavailableDate = null,
    ) {}
}
```

`PriceBreakdown::toArray()` → format dùng cho REST response và `pricing_snapshot`.

## 3.10. Cost & Profit

**MVP**: `vie_room_price` / `vie_ticket_price` chưa có cột `cost_price`. Vì vậy:

- `PriceCalculator` luôn trả `cost_total = 0`.
- `vie_order.cost_total` & `vie_order_item.cost_total` mặc định `0` khi tạo đơn.
- Admin SPA (trang chi tiết đơn → tab "Giá vốn") cho phép sửa tay từng line, gọi `POST /orders/{id}/recalculate-cost` (xem [05-rest-api.md §5.12](05-rest-api.md#512-orders)). Sau khi sửa, `profit_total = total − cost_total` tự cập nhật.
- Báo cáo `/reports/*` cột `cost` / `profit` → **chỉ có nghĩa cho các đơn admin đã nhập giá vốn**. Frontend phải hiển thị tooltip "Giá vốn nhập tay; đơn chưa nhập = 0".

**Phase sau** (`Phase 3+`): thêm cột `cost_price DECIMAL(12,0) NULL` ở `vie_room_price` & `vie_ticket_price` + UI bulk update giá vốn (cùng UI bulk update giá bán). Khi đó `PriceCalculator` tự sum `cost_price` cho mọi đêm → `cost_total` auto, không cần nhập tay.

> Đây là **trade-off có chủ ý**: giữ schema MVP gọn, đổi lấy việc kế toán tự nhập giá vốn cho ~5–10 đơn/ngày. Nếu khối lượng tăng > 50 đơn/ngày → ưu tiên Phase 3.

## 3.11. Bảng tổng kết quy tắc trẻ em

| Tình huống | Phòng | Vé xe |
|---|---|---|
| Bé < 6, ở phòng | Miễn `room.free_children_count` / phòng | Miễn `hotel.ticket_free_children_count` / **booking** |
| Bé ≥ 6, ở phòng | Tính NL (chiếm slot, có thể là extra bed) | Tính 1 vé như NL |
| Bé < 6 vượt quota | Phụ thu theo `vie_surcharge` rule | Tính 1 vé |
| `is_free = 1` ở rule | Không tính tiền | — |
| `is_free = 0` & age match | Phụ thu / đêm | — |
