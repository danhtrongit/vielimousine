# Booking Feedback Fixes — Design Spec

**Date:** 2026-05-29
**Source:** `~/Downloads/feedback.pdf` + bổ sung từ khách (thứ tự hạng phòng, nút cập nhật admin, màu primary).
**Scope:** Vielimousine child theme — booking flow (`public-app/`), pricing services (`inc/src/Service/Pricing/`), admin SPA (`admin-app/`).

## Tóm tắt quyết định (đã chốt với khách)

1. **Thứ tự hạng phòng:** Có giá → phù hợp nhóm → thứ tự admin (`sort_order`).
2. **Phụ thu trẻ em combo:** Gom vé xe của bé vào dòng "Phụ thu trẻ em" (buffet + vé). **Tổng tiền KHÔNG đổi** — chỉ trình bày lại.
3. **Màu primary Admin:** xanh lá `#00a651` (tông xanh thương hiệu, không phải xanh dương).
4. **Nút back:** Back trình duyệt → quay về danh sách phòng trên cùng trang khách sạn, giữ nguyên thông tin tìm kiếm.
5. **Phòng không đủ chứa (khách chọn ít phòng):** Tự tách đủ số phòng để hiện giá (xếp sau); chỉ "Liên hệ báo giá" khi thực sự không chứa được.

---

## Issues → Giải pháp

### A. Pricing backend (PHP)

#### A1 — Bỏ rule "bé free vì còn ghế người lớn trống" (Issue #1 + #9)
**Hiện trạng:** `ChildPolicy` nhận `spareAdultSlots` từ `RoomAllocation`; bé vượt quota chính sách vẫn được free nếu còn ghế người lớn trống → bé thứ 2 không bị phụ thu buffet (trang 1). Đồng thời `PriceCalculator` sinh message `"{n} bé ngồi vào chỗ người lớn còn trống — không tính phụ thu"` (dòng đỏ trang 6).

**Sửa:**
- `PriceCalculator::quote()` truyền `spareAdultSlots = 0` vào `ChildPolicy` (giữ chữ ký `ChildPolicy` để tương thích, chỉ đổi giá trị truyền vào; hoặc bỏ tham số nếu không còn nơi dùng).
- Xoá block message `spareSlotFreeCount()` (PriceCalculator dòng ~61–63).
- Giữ nguyên: free đúng `free_children_count` bé ≤ `free_children_max_age` (message "Miễn phí N bé dưới X tuổi" vẫn còn).
- `RoomAllocation::spareAdultSlots()` có thể giữ lại (không xoá API) nhưng không còn được dùng cho child policy.

**Kết quả mong đợi:** 2 bé 5T, phòng free 1 bé → bé thứ 2 bị phụ thu buffet; không còn dòng đỏ.

#### A2 — Tách vé xe trẻ em trong combo (Issue #3)
**Hiện trạng:** Combo line = `room_subtotal + ticket_subtotal` (đã gồm vé của bé). "Phụ thu trẻ em" = `child_surcharge_total` (chỉ buffet). Khách muốn "Phụ thu trẻ em" = buffet + vé bé (940k), dòng combo chỉ còn người lớn. Tổng không đổi.

**Sửa:**
- Thêm field `childTicketSubtotal` vào `PriceBreakdown` DTO.
  - Định nghĩa: `childTicketSubtotal = (billableSeats - adults) * ticketPrice` cộng theo từng đêm (chỉ khi combo). `adults` = `req->adults` (vé người lớn). Phần còn lại của `billableSeats` là vé bé (trẻ over-floor + bé under-floor không free).
  - Tính trong `TicketCalculator` (thêm getter `childTicketSubtotal()`) hoặc trong `PriceCalculator` từ dữ liệu sẵn có.
- `QuoteController` map thêm `child_ticket_subtotal` vào response.
- `public-app/src/api/types.ts`: thêm `child_ticket_subtotal: number` vào `Quote`.
- `BookingWidget.vue` tính lại hiển thị:
  - Dòng combo: `room_subtotal + (ticket_subtotal - child_ticket_subtotal)`.
  - Dòng "Phụ thu trẻ em": `child_surcharge_total + child_ticket_subtotal`.
  - Chỉ áp dụng khi `bookingType === 'combo'`; chế độ "Chỉ phòng" giữ nguyên.

#### A3 — Auto-tách phòng khi không đủ chứa (Issue #6, trang 3)
**Hiện trạng:** Nếu `userRooms > 0` thì `numRooms = userRooms` cố định; vượt sức chứa → `requiresQuote` → "Liên hệ báo giá".

**Sửa `RoomAllocation`:**
- `userRooms` trở thành **số phòng tối thiểu**:
  `numRooms = max(userRooms (nếu >0), ceil(effAdults/max_adults), ceil(effChildren/max_children nếu>0), 1)`.
- Cờ `roomsExpanded = (numRooms > userRooms && userRooms > 0)`; expose getter `roomsExpanded()`.
- Message khi expand: `"Đã tăng lên {numRooms} phòng để đủ chỗ cho nhóm"`.
- `requiresQuote` chỉ còn khi:
  - `max_children === 0 && effChildren > 0` (phòng không nhận trẻ em), hoặc
  - `numRooms > MAX_AUTO_ROOMS` (hằng số an toàn, mặc định **10**).
- Bỏ điều kiện `effAdults > totalCapacityAdults` / `effChildren > totalCapacityChildren` cũ (vì đã auto-expand).
- `PriceBreakdown` thêm `roomsExpanded: bool`; `QuoteController` map `rooms_expanded`; `types.ts` thêm `rooms_expanded: boolean`.

### B. Frontend booking flow (`public-app/`)

#### B1 — Thứ tự hạng phòng (Issue #4 / #5 / #8)
- `single-hotel.php`: đổi `'sort' => 'base_price'` → `'sort_order'`, `'order' => 'asc'` (thứ tự admin cơ bản → cao cấp). Thứ tự mảng `rooms` trở thành thứ tự admin → dùng làm tiebreak cuối.
- `HotelDetailApp.vue`: thêm computed `sortedRooms`.
  - Khi `priceChecked === false`: giữ nguyên thứ tự props (thứ tự admin).
  - Khi đã check giá: sort theo khoá (dùng quote **type 'room'** làm đại diện):
    1. `requiresQuote` (false trước true) — phòng có giá lên đầu.
    2. `roomsExpanded` (false trước true) — phòng vừa khít (không phải tăng phòng) lên trên.
    3. Độ dư sức chứa `num_rooms * room.max_adults - effective_adults` (nhỏ trước) — phòng khớp nhóm nhất lên trên.
    4. `originalIndex` (thứ tự admin) tăng dần — tiebreak.
  - Render `sortedRooms` thay cho `rooms`.

#### B2 — Quyền lợi trong Tóm tắt (Issue #2)
`BookingWidget.vue` thêm khối "Quyền lợi" (dưới meta):
- `Buffet sáng` (luôn hiển thị).
- Combo: `{seat_count} vé khứ hồi xe limousine`.
- Tuổi từng bé: đã có `childrenSummary` (giữ, đảm bảo hiển thị "(N tuổi, …)").
- Note "Đã tăng lên N phòng…" hiển thị qua `messages` (đã có sẵn list).

#### B3 — Back về danh sách phòng (Issue #7)
- `useBookingState.setSelection(roomId, type)`: khi chọn phòng (roomId != null) và chưa có history-state đặt phòng → `history.pushState({ vhBooking: true }, '')`.
- `HotelDetailApp` (hoặc composable) đăng ký `window.addEventListener('popstate')`: nếu đang có selection → `setSelection(null)` (về danh sách) và scroll lên danh sách phòng, **không** rời trang.
- Cleanup listener khi unmount.
- Search state nằm trong `reactive search` của SPA → không reset khi back trong trang.

### C. Admin SPA (`admin-app/`)

#### C1 — Nút "Cập nhật" thay auto-save (Issue #10)
`UnifiedMatrixView.vue`:
- Bỏ `debounceTimer` + `DEBOUNCE_MS` + auto-`setTimeout(flush)` trong `enqueueChange`.
- `enqueueChange` chỉ gom vào `pendingMap` (giữ optimistic UI).
- Thêm nút **"Cập nhật ({pendingMap.size})"** ở thanh công cụ: `@click="flush"`, `:disabled="pendingMap.size === 0 || flushing"`, `:loading="flushing"`.
- `onBeforeUnmount`: nếu còn pending → flush (giữ) hoặc cảnh báo; thêm `beforeunload` + router `onBeforeRouteLeave` cảnh báo "còn thay đổi chưa lưu".
- Sau `flush` thành công: `pendingMap` rỗng → nút disable.

#### C2 — Màu primary xanh lá `#00a651` (Issue #11)
`admin-app/src/styles/preset.ts`:
- Thay scale `primitive.vielimo` quanh `#00a651`:
  - 50 `#e6f7ee`, 100 `#c2ebd1`, 200 `#8fdcab`, 300 `#52c982`, 400 `#1eb866`, 500 `#00a651`, 600 `#009447`, 700 `#007d3c`, 800 `#006331`, 900 `#00512a`, 950 `#002e17` (tinh chỉnh khi build cho mượt).
- `focusRing.shadow`: đổi rgba cam → `rgba(0, 166, 81, 0.15)`.
- Rà `tokens.css` (và component CSS) tìm mã cam hardcode (`#fa541c`, `#ff7237`, `250, 84, 28`) thay bằng token primary nếu có.

---

## Kiến trúc & lý do

- **Sort ở frontend** (không thêm endpoint API mới): giá được fetch bất đồng bộ từng phòng × từng loại; sort reactive sau khi quote về là tự nhiên, tránh round-trip thừa và không phá vỡ luồng `refreshQuotes`.
- **Tách vé bé ở backend** (field `child_ticket_subtotal`): chính xác theo logic ghế/vé, có unit test PHP bao phủ, frontend chỉ hiển thị.
- **`userRooms` = số phòng tối thiểu**: tôn trọng ý khách (muốn nhiều phòng vẫn được) nhưng không bao giờ hiển thị giá sai do thiếu chỗ.

## Kiểm thử (TDD)
- **PHP unit tests** trước cho: `ChildPolicy` (bỏ spare-slot), `RoomAllocation` (auto-expand, requiresQuote mới), `TicketCalculator`/`PriceCalculator` (`childTicketSubtotal`).
- Build lại 2 SPA (`public-app`, `admin-app`) và rà UI thủ công các case trong feedback (trang 1–6).

## Ngoài phạm vi (YAGNI)
- Không làm danh sách quyền lợi cấu hình động theo từng phòng (dùng nội dung tĩnh: Buffet sáng + vé limousine). Có thể mở rộng sau nếu cần.
- Không refactor lại toàn bộ pricing pipeline; chỉ chỉnh các điểm liên quan feedback.
