# Booking Feedback Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 11 issues from `feedback.pdf` + client notes across the booking flow, pricing engine, and admin SPA.

**Architecture:** PHP pricing services (`inc/src/Service/Pricing/`) compute quotes; `public-app/` (Vue 3 + PrimeVue) renders the booking flow on `single-hotel.php`; `admin-app/` (Vue 3 + PrimeVue) is the back office. Pure-logic PHP classes are unit-tested with plain `php`; WP-integration cases via `wp eval-file`; Vue verified via `vite build`.

**Tech Stack:** PHP 8.5, Vue 3 + TypeScript, PrimeVue 4 (`@primeuix/themes` Aura preset), Vite.

**Test commands:**
- Pure PHP unit tests (runnable here): `php inc/tests/unit/<file>.php`
- WP integration (Local env only): `wp eval-file inc/tests/run.php`
- Public SPA build: `cd public-app && npm run build`
- Admin SPA build: `cd admin-app && npm run build`

All paths below are relative to `vielimousine-child/` unless noted.

---

## File Structure

**PHP (modify):**
- `inc/src/Service/Pricing/RoomAllocation.php` — auto-expand rooms, `roomsExpanded`, new `requiresQuote`.
- `inc/src/Service/Pricing/ChildPolicy.php` — remove spare-adult-slot free treatment.
- `inc/src/Service/Pricing/PriceCalculator.php` — wire the above, add `childTicketSubtotal` + expanded message, drop spare-slot message.
- `inc/src/DTO/PriceBreakdown.php` — add `childTicketSubtotal`, `roomsExpanded`.
- `inc/tests/cases.php` — add regression cases.
- `inc/tests/child-slot-pricing.php` — repair stale `ChildPolicy` constructor calls.

**PHP (create):**
- `inc/tests/unit/room-allocation-test.php` — pure unit test.
- `inc/tests/unit/child-policy-test.php` — pure unit test.

**Frontend public-app (modify):**
- `../single-hotel.php` — room sort `base_price` → `sort_order`.
- `public-app/src/api/types.ts` — add `child_ticket_subtotal`, `rooms_expanded`.
- `public-app/src/components/HotelDetailApp.vue` — `sortedRooms` + back navigation listener.
- `public-app/src/components/BookingWidget.vue` — child-ticket regroup + benefits block.
- `public-app/src/composables/useBookingState.ts` — pushState on select / back helper.

**Admin app (modify):**
- `admin-app/src/views/pricing/UnifiedMatrixView.vue` — replace debounced auto-save with Update button.
- `admin-app/src/styles/preset.ts` — green `#00a651` primary scale.
- `admin-app/src/styles/tokens.css` — replace any hardcoded orange.

---

## Task 1: RoomAllocation auto-expands rooms (Issue #6)

**Files:**
- Test: `inc/tests/unit/room-allocation-test.php` (create)
- Modify: `inc/src/Service/Pricing/RoomAllocation.php`

- [ ] **Step 1: Write the failing test**

Create `inc/tests/unit/room-allocation-test.php`:

```php
<?php
declare(strict_types=1);

// Pure unit test — no WordPress needed.
require __DIR__ . '/../../src/Service/Pricing/GuestComposition.php';
require __DIR__ . '/../../src/Service/Pricing/RoomAllocation.php';

use Vie\Service\Pricing\GuestComposition;
use Vie\Service\Pricing\RoomAllocation;

$pass = 0; $fail = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n"; $fail++; }
};

// Premier-style room: included 2, max_adults 3, max_children 2.
$premier = ['included_adults' => 2, 'max_adults' => 3, 'max_children' => 2];

// A) 4 adults, user asks 1 room → auto-expand to 2, priced (not quote).
$a = new RoomAllocation($premier, new GuestComposition(4, [], 12), 1);
$assert('A: numRooms auto-expands 1→2', $a->numRooms() === 2, 'got ' . $a->numRooms());
$assert('A: roomsExpanded flag true', $a->roomsExpanded() === true);
$assert('A: not requiresQuote', $a->requiresQuote() === false);

// B) fits in requested rooms → no expansion.
$b = new RoomAllocation($premier, new GuestComposition(2, [5], 12), 1);
$assert('B: numRooms stays 1', $b->numRooms() === 1, 'got ' . $b->numRooms());
$assert('B: not expanded', $b->roomsExpanded() === false);

// C) room rejects children (max_children 0) + has children → requiresQuote.
$noKids = ['included_adults' => 2, 'max_adults' => 2, 'max_children' => 0];
$c = new RoomAllocation($noKids, new GuestComposition(2, [5], 12), 1);
$assert('C: requiresQuote when no child capacity', $c->requiresQuote() === true);

// D) user asks 3 rooms for 2 adults → honor minimum 3.
$d = new RoomAllocation($premier, new GuestComposition(2, [], 12), 3);
$assert('D: userRooms is a minimum (3)', $d->numRooms() === 3, 'got ' . $d->numRooms());
$assert('D: not flagged expanded (user chose 3)', $d->roomsExpanded() === false);

echo "\n--- RoomAllocation: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php inc/tests/unit/room-allocation-test.php`
Expected: FAIL — `A: numRooms auto-expands 1→2` got 1; `A: roomsExpanded flag true` (method missing → fatal) ; `A: not requiresQuote` false. (If fatal on `roomsExpanded()` missing, that is the expected red.)

- [ ] **Step 3: Implement the change**

Replace the constructor body and add `roomsExpanded` in `inc/src/Service/Pricing/RoomAllocation.php`. New full file:

```php
<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

final class RoomAllocation
{
    private const MAX_AUTO_ROOMS = 10;

    private int   $numRooms;
    private int   $extraAdultBeds;
    private int   $spareAdultSlots;
    private bool  $roomsExpanded = false;
    private bool  $requiresQuote = false;
    private array $messages      = [];

    public function __construct(array $room, GuestComposition $g, int $userRooms)
    {
        $capacity     = (int) $room['max_adults'];
        $maxChildren  = (int) $room['max_children'];
        $included     = (int) $room['included_adults'];

        $effAdults    = $g->effectiveAdults();
        $effChildren  = $g->effectiveChildren();

        // Số phòng tối thiểu để chứa nhóm theo sức chứa từng phòng.
        $byAdults   = (int) ceil($effAdults / max(1, $capacity));
        $byChildren = $maxChildren > 0 ? (int) ceil($effChildren / $maxChildren) : 0;
        $needed     = max($byAdults, $byChildren, 1);

        // userRooms = số phòng tối thiểu khách muốn; tự tăng nếu thiếu chỗ.
        $requested      = $userRooms > 0 ? $userRooms : 0;
        $this->numRooms = max($requested, $needed, 1);
        $this->roomsExpanded = ($requested > 0 && $this->numRooms > $requested);

        $this->extraAdultBeds  = max(0, $effAdults - $this->numRooms * $included);
        $this->spareAdultSlots = max(0, $this->numRooms * $included - $effAdults);

        // requiresQuote chỉ khi không thể chứa dù đã tăng phòng.
        if ($maxChildren === 0 && $effChildren > 0) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Hạng phòng này không nhận trẻ em — Liên hệ báo giá';
        } elseif ($this->numRooms > self::MAX_AUTO_ROOMS) {
            $this->requiresQuote = true;
            $this->messages[]    = 'Số khách vượt quá sức chứa — Liên hệ báo giá';
        } elseif ($this->roomsExpanded) {
            $this->messages[]    = "Đã tăng lên {$this->numRooms} phòng để đủ chỗ cho nhóm";
        }
    }

    public function numRooms(): int        { return $this->numRooms; }
    public function extraAdultBeds(): int  { return $this->extraAdultBeds; }
    public function spareAdultSlots(): int { return $this->spareAdultSlots; }
    public function roomsExpanded(): bool  { return $this->roomsExpanded; }
    public function requiresQuote(): bool  { return $this->requiresQuote; }
    public function messages(): array      { return $this->messages; }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php inc/tests/unit/room-allocation-test.php`
Expected: PASS — `RoomAllocation: 8 passed, 0 failed`.

- [ ] **Step 5: Commit**

```bash
git add inc/tests/unit/room-allocation-test.php inc/src/Service/Pricing/RoomAllocation.php
git commit -m "fix(pricing): auto-expand rooms instead of requiring quote (#6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: ChildPolicy drops spare-adult-slot free treatment (Issue #1 + #9)

**Files:**
- Test: `inc/tests/unit/child-policy-test.php` (create)
- Modify: `inc/src/Service/Pricing/ChildPolicy.php`
- Modify: `inc/tests/child-slot-pricing.php` (repair stale constructor calls)

- [ ] **Step 1: Write the failing test**

Create `inc/tests/unit/child-policy-test.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../../src/DTO/ChildAssessment.php';
require __DIR__ . '/../../src/Service/Pricing/ChildPolicy.php';

use Vie\Service\Pricing\ChildPolicy;

$pass = 0; $fail = 0;
$assert = function (string $name, bool $cond, string $detail = '') use (&$pass, &$fail): void {
    if ($cond) { echo "  ✓ {$name}\n"; $pass++; }
    else { echo "  ✗ {$name}" . ($detail ? " — {$detail}" : '') . "\n"; $fail++; }
};

// 2 children age 5, free quota 1 (under-6 policy). Only ONE is free —
// a spare adult slot must NOT free the second child.
$p = new ChildPolicy([5, 5], 1, 5);
$assert('exactly 1 free child', $p->freeChildrenCount() === 1, 'got ' . $p->freeChildrenCount());
$assert('policy free count 1', $p->policyFreeCount() === 1);
$charged = array_values(array_filter($p->assessments(), fn($a) => !$a->isFree));
$assert('1 charged child', count($charged) === 1);
$assert('charged child index 1', ($charged[0]->childIndex ?? 0) === 1);

// No spareAdultSlots concept anymore — constructor takes 3 args only.
$ref = new ReflectionMethod(ChildPolicy::class, '__construct');
$assert('constructor has 3 params', $ref->getNumberOfParameters() === 3, 'got ' . $ref->getNumberOfParameters());

echo "\n--- ChildPolicy: {$pass} passed, {$fail} failed ---\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php inc/tests/unit/child-policy-test.php`
Expected: FAIL on `constructor has 3 params` (currently 4). The free-count asserts pass (with no 4th arg, spareAdultSlots defaults to 0) — the param-count assertion is the red.

- [ ] **Step 3: Implement the change**

Edit `inc/src/Service/Pricing/ChildPolicy.php`: remove the `$spareAdultSlots` parameter, the spare-slot `elseif` block, and `spareSlotFreeCount`. New full file:

```php
<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

use Vie\DTO\ChildAssessment;

final class ChildPolicy
{
    private array $assessments = [];
    private int   $freeCount   = 0;
    private int   $policyFreeCount = 0;

    /**
     * @param int[] $childAges  All child ages (đã lọc < adult threshold bởi GuestComposition).
     * @param int   $freeQuota  Số bé được miễn theo chính sách phòng (free_children_count × numRooms).
     * @param int   $freeAgeCap Tuổi tối đa (inclusive) đủ điều kiện miễn theo chính sách.
     */
    public function __construct(
        array $childAges,
        int $freeQuota = 0,
        int $freeAgeCap = 99,
    ) {
        $ages = array_map('intval', $childAges);
        // Sort DESC: bé lớn nhất tiêu thụ free-quota trước (đồng bộ business rule §3.4).
        rsort($ages);

        $usedPolicyFree = 0;
        $payableIndex   = 0;
        foreach ($ages as $age) {
            $isFree = false;
            if ($age <= $freeAgeCap && $usedPolicyFree < $freeQuota) {
                $usedPolicyFree++;
                $isFree = true;
            }
            if ($isFree) {
                $this->freeCount++;
                $childIndex = 0;
            } else {
                $payableIndex++;
                $childIndex = $payableIndex;
            }
            $this->assessments[] = new ChildAssessment(
                age:            $age,
                isFree:         $isFree,
                treatedAsAdult: false,
                childIndex:     $childIndex,
            );
        }
        $this->policyFreeCount = $usedPolicyFree;
    }

    public function assessments(): array      { return $this->assessments; }
    public function freeChildrenCount(): int  { return $this->freeCount; }
    public function policyFreeCount(): int    { return $this->policyFreeCount; }
}
```

- [ ] **Step 4: Repair stale test calls**

In `inc/tests/child-slot-pricing.php`, replace the three constructor calls (they pass `[]` as the int freeQuota — a pre-existing bug):
- `new \Vie\Service\Pricing\ChildPolicy([3, 3], [], 0)` → `new \Vie\Service\Pricing\ChildPolicy([3, 3], 0)`
- `new \Vie\Service\Pricing\ChildPolicy([3, 3, 3], [], 0)` → `new \Vie\Service\Pricing\ChildPolicy([3, 3, 3], 0)`
- `new \Vie\Service\Pricing\ChildPolicy([5, 5], [], 0)` → `new \Vie\Service\Pricing\ChildPolicy([5, 5], 0)`

- [ ] **Step 5: Run test to verify it passes**

Run: `php inc/tests/unit/child-policy-test.php`
Expected: PASS — `ChildPolicy: 6 passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add inc/src/Service/Pricing/ChildPolicy.php inc/tests/unit/child-policy-test.php inc/tests/child-slot-pricing.php
git commit -m "fix(pricing): remove spare-adult-slot free child treatment (#1, #9)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: PriceCalculator + PriceBreakdown wiring (Issues #1, #3, #6 plumbing)

**Files:**
- Modify: `inc/src/DTO/PriceBreakdown.php`
- Modify: `inc/src/Service/Pricing/PriceCalculator.php`
- Modify: `inc/tests/cases.php`

- [ ] **Step 1: Add fields to PriceBreakdown**

In `inc/src/DTO/PriceBreakdown.php` constructor, add two params after `$childSurchargeTotal` (keep order consistent with the named-arg call sites in PriceCalculator — both use named args so position is flexible, but add before `$ticketSubtotal` for readability):

Add to the constructor signature:
```php
        public int     $childTicketSubtotal,
        public bool    $roomsExpanded,
```
(place `childTicketSubtotal` right after `ticketSubtotal`, and `roomsExpanded` right after `requiresQuote`.)

Add to `toArray()`:
```php
            'child_ticket_subtotal' => $this->childTicketSubtotal,
            'rooms_expanded'        => $this->roomsExpanded,
```
(put `child_ticket_subtotal` next to `ticket_subtotal`, `rooms_expanded` next to `requires_quote`.)

- [ ] **Step 2: Wire PriceCalculator**

In `inc/src/Service/Pricing/PriceCalculator.php`:

(a) Change the `ChildPolicy` construction (drop the 4th arg):
```php
        $childPolicy  = new ChildPolicy(
            $guest->childrenUnderFloor(),
            (int) $room['free_children_count'] * $allocation->numRooms(),
            $freeAgeCap,
        );
```

(b) Remove the spare-slot message block (delete these lines):
```php
        if ($childPolicy->spareSlotFreeCount() > 0) {
            $messages[] = "{$childPolicy->spareSlotFreeCount()} bé ngồi vào chỗ người lớn còn trống — không tính phụ thu";
        }
```

(c) Compute child ticket subtotal (after `$ticket` is built, before the breakdown returns). Add near the top of `quote()` after `$ticket = new TicketCalculator(...)`:
```php
        $childTicketSubtotal = $isCombo
            ? max(0, $ticket->billableSeats() - $req->adults) * $ticket->ticketPrice()
            : 0;
```

(d) Pass the two new named args into BOTH `new PriceBreakdown(...)` (full) and `emptyBreakdown(...)`. For the full breakdown add:
```php
            childTicketSubtotal: $childTicketSubtotal,
            roomsExpanded:       $allocation->roomsExpanded(),
```
For `emptyBreakdown()`: add params `int $childTicketSubtotal` and `bool $roomsExpanded` to its signature, pass them through to the inner `new PriceBreakdown(...)`, and at the two call sites pass `$childTicketSubtotal` and `$allocation->roomsExpanded()`.

- [ ] **Step 3: Lint the PHP files**

Run: `php -l inc/src/DTO/PriceBreakdown.php && php -l inc/src/Service/Pricing/PriceCalculator.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Add regression cases to cases.php**

Append two cases to the returned array in `inc/tests/cases.php` (before the closing `];`):

```php
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
            'num_rooms'       => 1,
            'requires_quote'  => false,
            'rooms_expanded'  => false,
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
```

> NOTE: `run.php` only checks keys present in `expected` against `toArray()`, so `rooms_expanded` must exist in `toArray()` (added in Step 1). The `run.php` harness needs WordPress; run it in the Local environment.

- [ ] **Step 5: Run WP integration (Local env)**

Run (in the Local site shell): `wp eval-file inc/tests/run.php`
Expected: Pricing section shows Case 1–13 all `✓`; Case 12 confirms one free / one charged; Case 13 confirms `num_rooms=2, rooms_expanded=true`.
> If `wp` is unavailable in the sandbox, this step is verified by the user in their Local environment. The pure unit tests (Tasks 1–2) already cover the core logic offline.

- [ ] **Step 6: Commit**

```bash
git add inc/src/DTO/PriceBreakdown.php inc/src/Service/Pricing/PriceCalculator.php inc/tests/cases.php
git commit -m "feat(pricing): expose child_ticket_subtotal + rooms_expanded; drop spare-slot msg (#1,#3,#6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Frontend Quote types (Issues #3, #6 plumbing)

**Files:**
- Modify: `public-app/src/api/types.ts`

- [ ] **Step 1: Add fields to the `Quote` interface**

In `public-app/src/api/types.ts`, inside `interface Quote`, add after `ticket_subtotal`:
```ts
  child_ticket_subtotal: number;
```
and after `requires_quote`:
```ts
  rooms_expanded: boolean;
```

- [ ] **Step 2: Verify build compiles (deferred to Task 7 build)**

No standalone run; type usage is verified by `npm run build` in Task 7.

- [ ] **Step 3: Commit**

```bash
git add public-app/src/api/types.ts
git commit -m "chore(public): add child_ticket_subtotal + rooms_expanded to Quote type (#3,#6)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Room base order follows admin sort_order (Issue #8)

**Files:**
- Modify: `../single-hotel.php` (parent of `vielimousine-child/`, i.e. `vielimousine-child/single-hotel.php`)

- [ ] **Step 1: Change the room query sort**

In `single-hotel.php`, in the `$roomRepo->all([...])` call (around line 39–45), change:
```php
        'sort'      => 'base_price',
        'order'     => 'asc',
```
to:
```php
        'sort'      => 'sort_order',
        'order'     => 'asc',
```

- [ ] **Step 2: Verify the repository accepts `sort_order`**

Run: `grep -n "sort_order\|allowedSort\|sortable\|'sort'" inc/src/Repository/RoomRepository.php inc/src/Repository/AbstractRepository.php`
Expected: confirm `sort_order` is an allowed sort column (it has `KEY idx_sort_order`). If the repository whitelists sort columns and `sort_order` is missing, add it to the whitelist. (If no whitelist, ordering passes through.)

- [ ] **Step 3: Commit**

```bash
git add single-hotel.php
git commit -m "fix(public): order rooms by admin sort_order, not base_price (#8)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Reactive room sorting after price check (Issues #4, #5)

**Files:**
- Modify: `public-app/src/components/HotelDetailApp.vue`

- [ ] **Step 1: Add `sortedRooms` computed**

In `HotelDetailApp.vue` `<script setup>`, import what is needed and compute a sorted list. Add imports:
```ts
import { onMounted, computed, onBeforeUnmount } from 'vue';
import { getQuote, priceChecked, search } from '@/composables/useBookingState';
```
Add the computed (after `props` is defined):
```ts
// Sort priority (only after prices are checked):
//  1. has a price (room-type quote not requires-quote / not unavailable) before quote-required
//  2. fits in requested rooms (not auto-expanded) before expanded
//  3. tighter capacity fit (less spare adult capacity) first
//  4. original admin sort_order (props order) as tiebreak
const sortedRooms = computed(() => {
  if (!priceChecked.value) return props.rooms;
  const decorated = props.rooms.map((room, index) => {
    const q = getQuote(room.id, 'room');
    const priced = !!q && !q.requires_quote && !q.unavailable_date;
    const expanded = !!q && q.rooms_expanded;
    const waste = q ? q.num_rooms * room.max_adults - q.effective_adults : 9999;
    return { room, index, priced, expanded, waste };
  });
  decorated.sort((a, b) => {
    if (a.priced !== b.priced) return a.priced ? -1 : 1;
    if (a.expanded !== b.expanded) return a.expanded ? 1 : -1;
    if (a.waste !== b.waste) return a.waste - b.waste;
    return a.index - b.index;
  });
  return decorated.map((d) => d.room);
});
```

- [ ] **Step 2: Render `sortedRooms`**

In the template, change:
```html
<RoomCard v-for="room in rooms" :key="room.id" :room="room" />
```
to:
```html
<RoomCard v-for="room in sortedRooms" :key="room.id" :room="room" />
```

- [ ] **Step 3: Verify build (deferred to Task 8 build).** Commit:

```bash
git add public-app/src/components/HotelDetailApp.vue
git commit -m "feat(public): sort rooms priced→fit→admin after price check (#4,#5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Booking summary — child ticket regroup + benefits (Issues #2, #3)

**Files:**
- Modify: `public-app/src/components/BookingWidget.vue`

- [ ] **Step 1: Add computed breakdown values**

In `BookingWidget.vue` `<script setup>`, after `const quote = computed(...)`, add:
```ts
const isCombo = computed(() => selection.bookingType === 'combo');

// Combo line = phòng + vé NGƯỜI LỚN (vé của bé chuyển sang phụ thu trẻ em).
const baseLineTotal = computed(() => {
  const q = quote.value;
  if (!q) return 0;
  const adultTickets = isCombo.value ? q.ticket_subtotal - q.child_ticket_subtotal : 0;
  return q.room_subtotal + adultTickets;
});

// Phụ thu trẻ em = buffet (child_surcharge_total) + vé xe của bé.
const childSurchargeLine = computed(() => {
  const q = quote.value;
  if (!q) return 0;
  return q.child_surcharge_total + (isCombo.value ? q.child_ticket_subtotal : 0);
});
```

- [ ] **Step 2: Update the price lines + add benefits block**

In the template, replace the priced lines block:
```html
        <div v-else class="vh-widget-lines">
          <div class="vh-line">
            <span>{{ quote.num_rooms }} phòng × {{ quote.nights }} đêm{{ selection.bookingType === 'combo' ? ' (combo)' : '' }}</span>
            <span>{{ formatVND(quote.room_subtotal + (selection.bookingType === 'combo' ? quote.ticket_subtotal : 0)) }}</span>
          </div>
          <div v-if="quote.extra_adult_subtotal" class="vh-line">
            <span>Phụ thu người lớn</span><span>{{ formatVND(quote.extra_adult_subtotal) }}</span>
          </div>
          <div v-if="quote.child_surcharge_total" class="vh-line">
            <span>Phụ thu trẻ em</span><span>{{ formatVND(quote.child_surcharge_total) }}</span>
          </div>
          <div v-if="quote.discount" class="vh-line vh-line-discount">
            <span>Giảm giá</span><span>−{{ formatVND(quote.discount) }}</span>
          </div>
        </div>
```
with:
```html
        <div v-else class="vh-widget-lines">
          <div class="vh-line">
            <span>{{ quote.num_rooms }} phòng × {{ quote.nights }} đêm{{ isCombo ? ' (combo)' : '' }}</span>
            <span>{{ formatVND(baseLineTotal) }}</span>
          </div>
          <div v-if="quote.extra_adult_subtotal" class="vh-line">
            <span>Phụ thu người lớn</span><span>{{ formatVND(quote.extra_adult_subtotal) }}</span>
          </div>
          <div v-if="childSurchargeLine" class="vh-line">
            <span>Phụ thu trẻ em</span><span>{{ formatVND(childSurchargeLine) }}</span>
          </div>
          <div v-if="quote.discount" class="vh-line vh-line-discount">
            <span>Giảm giá</span><span>−{{ formatVND(quote.discount) }}</span>
          </div>
        </div>
```

Add a benefits block right after the `vh-widget-meta` div (before the `v-if="quote.requires_quote"` block):
```html
        <ul class="vh-widget-benefits">
          <li><i class="pi pi-check-circle" /> Buffet sáng</li>
          <li v-if="isCombo && quote.seat_count > 0"><i class="pi pi-check-circle" /> {{ quote.seat_count }} vé khứ hồi xe limousine</li>
        </ul>
```

- [ ] **Step 3: Add minimal styling**

Append to the component's style (or `main.css` if styles are global — check `<style>` presence; this component has no `<style>` block, styles live in `public-app/src/styles/main.css`). Add to `public-app/src/styles/main.css`:
```css
.vh-widget-benefits { list-style: none; margin: .5rem 0 0; padding: 0; display: grid; gap: .25rem; }
.vh-widget-benefits li { display: flex; align-items: center; gap: .4rem; font-size: .85rem; color: var(--vh-muted, #475569); }
.vh-widget-benefits .pi { color: #00a651; font-size: .8rem; }
```

- [ ] **Step 4: Build the public SPA**

Run: `cd public-app && npm run build`
Expected: build succeeds, no TS errors. (`child_ticket_subtotal`/`rooms_expanded` types from Task 4 resolve.)

- [ ] **Step 5: Commit**

```bash
git add public-app/src/components/BookingWidget.vue public-app/src/styles/main.css public-app/dist
git commit -m "feat(public): regroup child ticket into child surcharge + show benefits (#2,#3)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Back returns to room list (Issue #7)

**Files:**
- Modify: `public-app/src/composables/useBookingState.ts`
- Modify: `public-app/src/components/HotelDetailApp.vue`
- Modify: `public-app/src/components/InlineCheckout.vue` (changeRoom uses history.back when applicable)

- [ ] **Step 1: pushState on selection in useBookingState**

In `public-app/src/composables/useBookingState.ts`, add a module flag and update `setSelection`:
```ts
let pushedBookingState = false;

export function setSelection(roomId: number | null, type: BookingType = 'room') {
  const wasEmpty = selection.roomId === null;
  selection.roomId = roomId;
  selection.bookingType = type;
  if (roomId !== null && wasEmpty && typeof history !== 'undefined') {
    history.pushState({ vhBooking: true }, '');
    pushedBookingState = true;
  }
}

// Clear selection, returning the browser to the room list without leaving the page.
export function clearSelectionBack() {
  if (pushedBookingState && typeof history !== 'undefined') {
    history.back(); // triggers popstate → handleBackToRooms
  } else {
    setSelection(null);
  }
}

// Called from the popstate handler when the user presses browser Back.
export function handleBackToRooms(): boolean {
  pushedBookingState = false;
  if (selection.roomId !== null) {
    selection.roomId = null;
    return true;
  }
  return false;
}
```

- [ ] **Step 2: Listen for popstate in HotelDetailApp**

In `HotelDetailApp.vue`, import the new helpers and register a listener:
```ts
import { prefillFromQuery, setSelection, handleBackToRooms } from '@/composables/useBookingState';
```
Add inside `<script setup>`:
```ts
function onPopState() {
  if (handleBackToRooms()) {
    setTimeout(() => {
      document.querySelector('.vh-rooms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
  }
}
onMounted(() => { window.addEventListener('popstate', onPopState); });
onBeforeUnmount(() => { window.removeEventListener('popstate', onPopState); });
```
(`onBeforeUnmount` import was added in Task 6 Step 1.)

- [ ] **Step 3: Make changeRoom use the back helper**

In `InlineCheckout.vue`, import and use:
```ts
import { search, selection, getQuote, setSelection, clearSelectionBack } from '@/composables/useBookingState';
```
Change `changeRoom()`:
```ts
function changeRoom() {
  clearSelectionBack();
  document.querySelector('.vh-rooms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
```

- [ ] **Step 4: Build the public SPA**

Run: `cd public-app && npm run build`
Expected: build succeeds, no TS errors.

- [ ] **Step 5: Commit**

```bash
git add public-app/src/composables/useBookingState.ts public-app/src/components/HotelDetailApp.vue public-app/src/components/InlineCheckout.vue public-app/dist
git commit -m "feat(public): browser Back returns to room list, keeps search state (#7)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Admin pricing — Update button instead of auto-save (Issue #10)

**Files:**
- Modify: `admin-app/src/views/pricing/UnifiedMatrixView.vue`

- [ ] **Step 1: Remove debounced auto-flush**

In `UnifiedMatrixView.vue`:
- Delete the debounce constants/var:
```ts
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const DEBOUNCE_MS = 600;
```
- In `enqueueChange`, remove the timer lines so it only queues:
```ts
function enqueueChange(key: string, change: CellChange) {
  pendingMap.value.set(key, change);
  pendingMap.value = new Map(pendingMap.value);
  errorKeys.value.delete(key);
}
```
- In `onBeforeUnmount`, replace the debounce-flush block with a no-op or keep a final flush only if pending:
```ts
onBeforeUnmount(() => {
  if (pendingMap.value.size > 0) void flush();
});
```

- [ ] **Step 2: Add an Update button to the toolbar**

Locate the toolbar/header area of the template (the controls row near the hotel/date selectors — search for the date pickers `dateFrom`/`dateTo` in the template). Add a button bound to `flush`:
```html
<Button
  :label="`Cập nhật${pendingMap.size ? ' (' + pendingMap.size + ')' : ''}`"
  icon="pi pi-save"
  :disabled="pendingMap.size === 0 || flushing"
  :loading="flushing"
  @click="flush"
/>
```
Ensure `Button` from `primevue/button` is imported (it likely already is; if not, add `import Button from 'primevue/button';`).

- [ ] **Step 3: Warn on navigating away with unsaved changes**

Add a `beforeunload` guard and a route-leave guard. In `<script setup>`:
```ts
import { onBeforeRouteLeave } from 'vue-router';

function beforeUnloadHandler(e: BeforeUnloadEvent) {
  if (pendingMap.value.size > 0) { e.preventDefault(); e.returnValue = ''; }
}
onMounted(() => window.addEventListener('beforeunload', beforeUnloadHandler));
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnloadHandler));

onBeforeRouteLeave(() => {
  if (pendingMap.value.size > 0) {
    return window.confirm('Còn thay đổi chưa lưu. Rời trang và bỏ thay đổi?');
  }
  return true;
});
```
(Merge the `onMounted` registration with any existing `onMounted` rather than declaring twice.)

- [ ] **Step 4: Build the admin SPA**

Run: `cd admin-app && npm run build`
Expected: build succeeds, no TS errors.

- [ ] **Step 5: Commit**

```bash
git add admin-app/src/views/pricing/UnifiedMatrixView.vue admin-app/dist
git commit -m "feat(admin): explicit Update button for pricing matrix, no auto-save (#10)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Admin primary color → green #00a651 (Issue #11)

**Files:**
- Modify: `admin-app/src/styles/preset.ts`
- Modify: `admin-app/src/styles/tokens.css` (only if hardcoded orange found)

- [ ] **Step 1: Replace the `vielimo` primitive scale + focus ring**

In `admin-app/src/styles/preset.ts`, replace the `primitive.vielimo` object with a green scale and update the comment:
```ts
  primitive: {
    vielimo: {
      50:  '#e8f8ef',
      100: '#c6ecd5',
      200: '#93dcb0',
      300: '#57c987',
      400: '#1eb568',
      500: '#00a651',
      600: '#009247',
      700: '#00793a',
      800: '#00602f',
      900: '#004f28',
      950: '#002c16',
    },
  },
```
Change the `focusRing.shadow` line from `rgba(250, 84, 28, 0.15)` to:
```ts
      shadow: '0 0 0 4px rgba(0, 166, 81, 0.15)',
```
Update the header comment `brand orange (#fa541c)` → `brand green (#00a651)`.

- [ ] **Step 2: Scan for hardcoded orange across admin app**

Run: `grep -rni "fa541c\|ff7237\|ff9a64\|250, 84, 28\|#e34112" admin-app/src`
For each match in `tokens.css` or component styles that is meant to be the brand color, replace with the green equivalent (`#00a651`, or `var(--p-primary-500)` where a token is appropriate). Leave genuinely-semantic oranges (e.g., a "warn" severity) unchanged.

- [ ] **Step 3: Build the admin SPA**

Run: `cd admin-app && npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add admin-app/src/styles/preset.ts admin-app/src/styles/tokens.css admin-app/dist
git commit -m "style(admin): primary brand color orange → green #00a651 (#11)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Final verification & manual QA checklist

**Files:** none (verification only)

- [ ] **Step 1: Re-run pure PHP unit tests**

Run: `php inc/tests/unit/room-allocation-test.php && php inc/tests/unit/child-policy-test.php`
Expected: both suites pass.

- [ ] **Step 2: Lint all modified PHP**

Run: `for f in inc/src/Service/Pricing/RoomAllocation.php inc/src/Service/Pricing/ChildPolicy.php inc/src/Service/Pricing/PriceCalculator.php inc/src/DTO/PriceBreakdown.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 3: Confirm both SPA dist bundles built**

Run: `git status --porcelain public-app/dist admin-app/dist`
Expected: dist changes are committed (clean) or staged.

- [ ] **Step 4: Manual QA in the Local site (user-driven)**

Walk through the feedback scenarios:
1. Premier Queen, 2 adults + 2 children (5,5) → 2nd child shows buffet surcharge (Issue #1) and no "ngồi vào chỗ người lớn" message (Issue #9).
2. Combo with 1 child age 6 → "Phụ thu trẻ em" shows buffet + child ticket; total unchanged (Issue #3).
3. Summary shows "Buffet sáng" + "N vé khứ hồi xe limousine" + child ages (Issue #2).
4. Search 1 room / 4 people → room shows a price (auto-split note), ranked after best-fit rooms, not "Liên hệ báo giá" (Issue #6).
5. Room list order: priced first, best-fit on top, then admin order; quote-required at bottom (Issues #4, #5, #8).
6. Select a room → press browser Back → returns to room list with search kept (Issue #7).
7. Admin pricing matrix: edits don't auto-save; "Cập nhật (N)" button saves; warn on leave (Issue #10).
8. Admin UI primary color is green (Issue #11).
9. (Local env) `wp eval-file inc/tests/run.php` → all pricing cases pass.

- [ ] **Step 5: Update plan checkboxes & finalize**

Mark all tasks complete. Use `superpowers:finishing-a-development-branch` to decide merge/PR.

---

## Self-Review

**Spec coverage:**
- Issue #1 (2nd child surcharge): Task 2 + Task 3 ✓
- Issue #2 (benefits in summary): Task 7 ✓
- Issue #3 (child ticket regroup 940k): Task 3 (backend field) + Task 4 (type) + Task 7 (display) ✓
- Issue #4 (suitable rooms first): Task 6 ✓
- Issue #5 (priced before quote): Task 6 ✓
- Issue #6 (auto-split rooms, show price): Task 1 + Task 3 ✓
- Issue #7 (back to room list): Task 8 ✓
- Issue #8 (admin sort_order base order): Task 5 ✓
- Issue #9 (remove red "ngồi vào chỗ" message): Task 2 + Task 3 ✓
- Issue #10 (admin Update button): Task 9 ✓
- Issue #11 (admin green primary): Task 10 ✓

**Type consistency:** `child_ticket_subtotal` / `childTicketSubtotal`, `rooms_expanded` / `roomsExpanded` used consistently across PHP DTO ↔ `toArray()` ↔ TS `Quote` ↔ components. `roomsExpanded()` / `numRooms()` / `requiresQuote()` getters match between RoomAllocation and PriceCalculator usage. `handleBackToRooms` / `clearSelectionBack` / `setSelection` consistent across composable ↔ components.

**Placeholder scan:** No TBD/TODO; every code step has concrete code.

**Open risk:** WP-integration test (`wp eval-file`) can't run in the sandbox (no `wp`); mitigated by pure unit tests for core logic + build checks + manual QA. Toolbar anchor in UnifiedMatrixView (Task 9 Step 2) and global-vs-scoped styles in BookingWidget (Task 7 Step 3) are confirmed by reading the file region at execution time.
