<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import VirtualScroller from 'primevue/virtualscroller';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { pricingApi, type CellChange } from '@/api/pricing.api';
import { roomPricesApi, type RoomPrice } from '@/api/roomPrices.api';
import { surchargePricesApi, type SurchargePrice } from '@/api/surcharges.api';
import { ticketPricesApi, type TicketPrice } from '@/api/ticketPrices.api';
import { ymdLocal, formatCompact, decodeEntities } from '@/composables/useFormat';
import type { Hotel, Room } from '@/types/hotel';
import type { Surcharge } from '@/api/surcharges.api';
import PricingCell from './PricingCell.vue';
import RowActionMenu from './RowActionMenu.vue';

type Row =
  | { kind: 'hotel-header'; key: string; hotel: Hotel; roomsCount: number }
  | { kind: 'ticket'; key: string; hotel: Hotel }
  | { kind: 'room-price'; key: string; hotel: Hotel; room: Room }
  | { kind: 'room-stock'; key: string; hotel: Hotel; room: Room }
  | { kind: 'surcharge'; key: string; hotel: Hotel; room: Room; rule: Surcharge };

const lookup = useLookupStore();
const notify = useNotify();

const today = new Date();
const todayStr = ymdLocal(today);
const defaultEnd = new Date(today.getTime() + 13 * 24 * 3600 * 1000);
const dateFrom = ref<Date>(today);
const dateTo = ref<Date>(defaultEnd);

const loading = ref(false);
const flushing = ref(false);
const roomPriceMap = ref<Map<string, RoomPrice>>(new Map());
const surchargePriceMap = ref<Map<string, SurchargePrice>>(new Map());
const ticketPriceMap = ref<Map<string, TicketPrice>>(new Map());

// Save queue: key = `${kind}_${entityId}_${date}`, value = pending CellChange
const pendingMap = ref<Map<string, CellChange>>(new Map());
const errorKeys = ref<Set<string>>(new Set());
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const DEBOUNCE_MS = 600;

const LABEL_WIDTH = 280;
const CELL_WIDTH = 96;
const ROW_HEIGHT = 64; // uniform for all data rows; hotel-header row gets same height

function fmtDate(d: Date): string { return ymdLocal(d); }
function fmtDateVN(d: string): string {
  const date = new Date(d);
  const dow = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][date.getDay()];
  return `${dow}\n${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}
function isWeekend(d: string): boolean {
  const day = new Date(d).getDay();
  return day === 0 || day === 6;
}
function isToday(d: string): boolean {
  return d === todayStr;
}

const dateRange = computed<string[]>(() => {
  const dates: string[] = [];
  if (!dateFrom.value || !dateTo.value) return dates;
  const cursor = new Date(dateFrom.value);
  const end = new Date(dateTo.value);
  while (cursor <= end) {
    dates.push(fmtDate(cursor));
    cursor.setDate(cursor.getDate() + 1);
  }
  return dates;
});

const totalWidth = computed(() => LABEL_WIDTH + dateRange.value.length * CELL_WIDTH);

const rows = computed<Row[]>(() => {
  const out: Row[] = [];
  for (const hotel of lookup.hotels) {
    const hotelRooms = lookup.rooms
      .filter((r) => r.hotel_id === hotel.id)
      .sort((a, b) => a.name.localeCompare(b.name));
    out.push({ kind: 'hotel-header', key: `h-${hotel.id}`, hotel, roomsCount: hotelRooms.length });
    out.push({ kind: 'ticket', key: `t-${hotel.id}`, hotel });
    for (const room of hotelRooms) {
      out.push({ kind: 'room-price', key: `rp-${room.id}`, hotel, room });
      out.push({ kind: 'room-stock', key: `rs-${room.id}`, hotel, room });
      const rules = lookup.surcharges
        .filter((s) => s.room_id === room.id && s.guest_type === 'child' && s.is_active)
        .sort((a, b) => (a.child_index_min - b.child_index_min) || (a.age_from - b.age_from));
      for (const rule of rules) {
        out.push({ kind: 'surcharge', key: `s-${rule.id}`, hotel, room, rule });
      }
    }
  }
  return out;
});

const roomKey = (rid: number, date: string) => `${rid}_${date}`;
const surKey = (sid: number, date: string) => `${sid}_${date}`;
const ticketKey = (hid: number, date: string) => `${hid}_${date}`;

async function loadAll() {
  if (dateRange.value.length === 0) return;
  loading.value = true;
  try {
    const resp = await pricingApi.matrix({
      date_from: fmtDate(dateFrom.value),
      date_to: fmtDate(dateTo.value),
    });

    const rpMap = new Map<string, RoomPrice>();
    for (const p of resp.data.room_prices) rpMap.set(roomKey(p.room_id, p.date), p);
    roomPriceMap.value = rpMap;

    const spMap = new Map<string, SurchargePrice>();
    for (const p of resp.data.surcharge_prices) spMap.set(surKey(p.surcharge_id, p.date), p);
    surchargePriceMap.value = spMap;

    const tpMap = new Map<string, TicketPrice>();
    for (const p of resp.data.ticket_prices) tpMap.set(ticketKey(p.hotel_id, p.date), p);
    ticketPriceMap.value = tpMap;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

watch([dateFrom, dateTo], loadAll);
onMounted(async () => {
  await lookup.ensureLoaded();
  loadAll();
});

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
    if (pendingMap.value.size > 0) void flush();
  }
});

// ── Getters: return current effective value ──
function getTicketPrice(hotel: Hotel, date: string): number {
  return ticketPriceMap.value.get(ticketKey(hotel.id, date))?.ticket_price ?? hotel.default_ticket_price ?? 0;
}
function getRoomPrice(room: Room, date: string): number {
  return roomPriceMap.value.get(roomKey(room.id, date))?.price ?? room.base_price ?? 0;
}
function getRoomStock(room: Room, date: string): number {
  return roomPriceMap.value.get(roomKey(room.id, date))?.stock ?? 0;
}
function getSurchargeAmount(rule: Surcharge, date: string): number {
  return surchargePriceMap.value.get(surKey(rule.id, date))?.amount ?? rule.amount ?? 0;
}

function hasTicketOverride(hotel: Hotel, date: string): boolean {
  return ticketPriceMap.value.has(ticketKey(hotel.id, date));
}
function hasRoomEntry(room: Room, date: string): boolean {
  return roomPriceMap.value.has(roomKey(room.id, date));
}
function hasSurOverride(rule: Surcharge, date: string): boolean {
  return surchargePriceMap.value.has(surKey(rule.id, date));
}

// ── Queue change + debounced flush ──
function enqueueChange(key: string, change: CellChange) {
  pendingMap.value.set(key, change);
  pendingMap.value = new Map(pendingMap.value);
  errorKeys.value.delete(key);
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(flush, DEBOUNCE_MS);
}

async function flush() {
  if (pendingMap.value.size === 0 || flushing.value) return;
  const entries = Array.from(pendingMap.value.entries());
  const changes = entries.map(([, c]) => c);
  flushing.value = true;
  try {
    const resp = await pricingApi.saveCells(changes);
    // Mark errors by index
    const newErrors = new Set<string>();
    for (const err of resp.data.errors) {
      const [k] = entries[err.index] ?? [];
      if (k) newErrors.add(k);
    }
    // Remove successfully saved keys from queue
    for (let i = 0; i < entries.length; i++) {
      const [k] = entries[i];
      if (!newErrors.has(k)) pendingMap.value.delete(k);
    }
    errorKeys.value = newErrors;
    pendingMap.value = new Map(pendingMap.value);

    if (resp.data.errors.length > 0) {
      notify.warn(`Lưu ${resp.data.saved} thay đổi, ${resp.data.errors.length} lỗi`);
    }
    // Reload affected entities so map reflects DB state
    await loadAll();
  } catch (e) {
    notify.apiError(e);
    for (const [k] of entries) errorKeys.value.add(k);
  } finally {
    flushing.value = false;
  }
}

// ── Optimistic update + queue handlers per cell type ──
function onTicketChange(hotel: Hotel, date: string, value: number) {
  // Optimistic: update map immediately
  const existing = ticketPriceMap.value.get(ticketKey(hotel.id, date));
  const optimistic: TicketPrice = existing
    ? { ...existing, ticket_price: value }
    : { id: 0, hotel_id: hotel.id, route_id: 0, date, ticket_price: value, is_active: true };
  const next = new Map(ticketPriceMap.value);
  next.set(ticketKey(hotel.id, date), optimistic);
  ticketPriceMap.value = next;

  enqueueChange(`ticket_${hotel.id}_${date}`, {
    kind: 'ticket_price',
    hotel_id: hotel.id,
    date,
    fields: { ticket_price: value, route_id: 0 },
  });
}

function onRoomPriceChange(room: Room, date: string, value: number) {
  const existing = roomPriceMap.value.get(roomKey(room.id, date));
  const optimistic: RoomPrice = existing
    ? { ...existing, price: value }
    : {
        id: 0, room_id: room.id, date,
        price: value, extra_adult_price: room.extra_adult_price ?? 0,
        stock: 0, is_active: true, source: 'manual',
      };
  const next = new Map(roomPriceMap.value);
  next.set(roomKey(room.id, date), optimistic);
  roomPriceMap.value = next;

  enqueueChange(`room_${room.id}_${date}`, {
    kind: 'room_price',
    room_id: room.id,
    date,
    fields: {
      price: value,
      extra_adult_price: optimistic.extra_adult_price,
      stock: optimistic.stock,
      is_active: optimistic.is_active,
      source: 'manual',
    },
  });
}

function onRoomStockChange(room: Room, date: string, value: number) {
  const existing = roomPriceMap.value.get(roomKey(room.id, date));
  const optimistic: RoomPrice = existing
    ? { ...existing, stock: value }
    : {
        id: 0, room_id: room.id, date,
        price: room.base_price ?? 0, extra_adult_price: room.extra_adult_price ?? 0,
        stock: value, is_active: true, source: 'manual',
      };
  const next = new Map(roomPriceMap.value);
  next.set(roomKey(room.id, date), optimistic);
  roomPriceMap.value = next;

  enqueueChange(`room_${room.id}_${date}`, {
    kind: 'room_price',
    room_id: room.id,
    date,
    fields: {
      price: optimistic.price,
      extra_adult_price: optimistic.extra_adult_price,
      stock: value,
      is_active: optimistic.is_active,
      source: 'manual',
    },
  });
}

function onSurchargeChange(rule: Surcharge, date: string, value: number) {
  const existing = surchargePriceMap.value.get(surKey(rule.id, date));
  const optimistic: SurchargePrice = existing
    ? { ...existing, amount: value }
    : { id: 0, surcharge_id: rule.id, date, amount: value, is_active: true };
  const next = new Map(surchargePriceMap.value);
  next.set(surKey(rule.id, date), optimistic);
  surchargePriceMap.value = next;

  enqueueChange(`sur_${rule.id}_${date}`, {
    kind: 'surcharge_price',
    surcharge_id: rule.id,
    date,
    fields: { amount: value },
  });
}

// ── Row action handlers (apply-range, reset) ──
async function applyRangeForRow(
  row: Row,
  payload: { value: number; from: string; to: string; weekdays: number[] | null },
): Promise<void> {
  flushing.value = true;
  try {
    if (row.kind === 'ticket') {
      await ticketPricesApi.bulk(
        { hotel_ids: [row.hotel.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays, route_id: 0 },
        { ticket_price: payload.value },
      );
    } else if (row.kind === 'room-price') {
      await roomPricesApi.bulk(
        { room_ids: [row.room.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { price: payload.value },
      );
    } else if (row.kind === 'room-stock') {
      await roomPricesApi.bulk(
        { room_ids: [row.room.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { stock: payload.value },
      );
    } else if (row.kind === 'surcharge') {
      await surchargePricesApi.bulk(
        { surcharge_ids: [row.rule.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { amount: payload.value },
      );
    } else {
      return;
    }
    notify.success('Đã áp dụng giá hàng loạt');
    await loadAll();
  } catch (e) {
    notify.apiError(e);
  } finally {
    flushing.value = false;
  }
}

async function resetRow(row: Row): Promise<void> {
  if (row.kind === 'hotel-header') return;
  if (!confirm('Tắt tất cả override trong khoảng ngày đang xem?')) return;
  flushing.value = true;
  try {
    const scope = {
      date_from: fmtDate(dateFrom.value),
      date_to: fmtDate(dateTo.value),
      weekdays: null,
    };
    if (row.kind === 'ticket') {
      await ticketPricesApi.bulk({ hotel_ids: [row.hotel.id], route_id: 0, ...scope }, { is_active: false });
    } else if (row.kind === 'room-price' || row.kind === 'room-stock') {
      await roomPricesApi.bulk({ room_ids: [row.room.id], ...scope }, { is_active: false });
    } else if (row.kind === 'surcharge') {
      await surchargePricesApi.bulk({ surcharge_ids: [row.rule.id], ...scope }, { is_active: false });
    }
    notify.success('Đã reset');
    await loadAll();
  } catch (e) {
    notify.apiError(e);
  } finally {
    flushing.value = false;
  }
}

// ── Row labels & key helpers ──
function rowLabel(row: Row): { primary: string; secondary: string; icon: string } {
  if (row.kind === 'hotel-header') {
    return { primary: decodeEntities(row.hotel.name), secondary: `${row.roomsCount} phòng`, icon: 'pi-building' };
  }
  if (row.kind === 'ticket') {
    return {
      primary: 'Vé xe',
      secondary: `Mặc định: ${formatCompact(row.hotel.default_ticket_price)}`,
      icon: 'pi-ticket',
    };
  }
  if (row.kind === 'room-price') {
    return {
      primary: `${decodeEntities(row.room.name)} · Giá`,
      secondary: `Mặc định: ${formatCompact(row.room.base_price)}`,
      icon: 'pi-home',
    };
  }
  if (row.kind === 'room-stock') {
    return {
      primary: `${decodeEntities(row.room.name)} · Tồn`,
      secondary: `Số phòng còn bán theo ngày`,
      icon: 'pi-box',
    };
  }
  const slotLabel = row.rule.child_index_max === null
    ? `Trẻ ${row.rule.child_index_min}+`
    : (row.rule.child_index_min === row.rule.child_index_max
        ? `Trẻ ${row.rule.child_index_min}`
        : `Trẻ ${row.rule.child_index_min}–${row.rule.child_index_max}`);
  return {
    primary: row.rule.label || 'Trẻ em',
    secondary: `${decodeEntities(row.room.name)} · ${slotLabel} · ${row.rule.age_from}–${row.rule.age_to}T`,
    icon: 'pi-users',
  };
}

function pendingKeyForTicket(hid: number, date: string) { return `ticket_${hid}_${date}`; }
function pendingKeyForRoom(rid: number, date: string)   { return `room_${rid}_${date}`; }
function pendingKeyForSur(sid: number, date: string)    { return `sur_${sid}_${date}`; }

function rowCurrentValue(row: Row): number {
  if (row.kind === 'ticket')      return getTicketPrice(row.hotel, fmtDate(dateFrom.value));
  if (row.kind === 'room-price')  return getRoomPrice(row.room, fmtDate(dateFrom.value));
  if (row.kind === 'room-stock')  return getRoomStock(row.room, fmtDate(dateFrom.value));
  if (row.kind === 'surcharge')   return getSurchargeAmount(row.rule, fmtDate(dateFrom.value));
  return 0;
}

const gridTemplateColumns = computed(
  () => `${LABEL_WIDTH}px repeat(${dateRange.value.length}, ${CELL_WIDTH}px)`,
);

const pendingCount = computed(() => pendingMap.value.size);
</script>

<template>
  <div class="wrap">
    <div class="toolbar">
      <div class="field">
        <label>Từ ngày</label>
        <DatePicker v-model="dateFrom" date-format="yy-mm-dd" show-icon />
      </div>
      <div class="field">
        <label>Đến ngày</label>
        <DatePicker v-model="dateTo" date-format="yy-mm-dd" show-icon />
      </div>
      <Button label="Tải lại" icon="pi pi-refresh" outlined @click="loadAll" :loading="loading" />
      <span class="muted">{{ rows.length }} dòng · {{ dateRange.length }} ngày</span>
      <span class="spacer" />
      <span v-if="pendingCount > 0" class="pending-badge">
        <i class="pi pi-clock" />
        {{ pendingCount }} thay đổi đang chờ
        <Button label="Lưu ngay" size="small" severity="warn" :loading="flushing" @click="flush" />
      </span>
      <span v-else-if="flushing" class="pending-badge"><i class="pi pi-spin pi-spinner" /> Đang lưu…</span>
    </div>

    <div v-if="loading && rows.length === 0" class="loading"><ProgressSpinner style="width: 40px;height: 40px" /></div>
    <div v-else-if="rows.length === 0" class="empty">Chưa có KS/phòng nào.</div>

    <div v-else class="matrix-shell" :style="{ width: '100%', overflowX: 'auto' }">
      <div class="matrix-inner" :style="{ width: totalWidth + 'px' }">
        <div class="header-row" :style="{ gridTemplateColumns }">
          <div class="th th-label">KS / Phòng / Phụ thu</div>
          <div
            v-for="d in dateRange"
            :key="d"
            class="th"
            :class="{ 'th-weekend': isWeekend(d), 'th-today': isToday(d) }"
          ><span class="th-date">{{ fmtDateVN(d) }}</span></div>
        </div>

        <VirtualScroller
          :items="rows"
          :item-size="ROW_HEIGHT"
          :style="{ height: 'calc(80vh - 220px)', width: totalWidth + 'px', overflowX: 'hidden' }"
          :pt="{ content: { style: { width: totalWidth + 'px' } } }"
        >
          <template #item="{ item }">
            <!-- Hotel-header banner row -->
            <div
              v-if="item.kind === 'hotel-header'"
              class="hotel-banner"
              :style="{ width: totalWidth + 'px', height: ROW_HEIGHT + 'px' }"
            >
              <i class="pi pi-building hotel-banner-icon" />
              <div class="hotel-banner-text">
                <div class="hotel-banner-name" :title="rowLabel(item).primary">{{ rowLabel(item).primary }}</div>
                <div class="hotel-banner-meta">{{ rowLabel(item).secondary }}</div>
              </div>
            </div>

            <!-- Data row -->
            <div
              v-else
              :class="['row', `row-${item.kind}`]"
              :style="{ gridTemplateColumns, height: ROW_HEIGHT + 'px' }"
            >
              <div class="cell label-cell">
                <i :class="['pi', rowLabel(item).icon, 'row-icon']" />
                <div class="label-text">
                  <div class="lbl-primary" :title="rowLabel(item).primary">{{ rowLabel(item).primary }}</div>
                  <div class="lbl-secondary" :title="rowLabel(item).secondary">{{ rowLabel(item).secondary }}</div>
                </div>
                <RowActionMenu
                  :current-value="rowCurrentValue(item)"
                  :date-from="fmtDate(dateFrom)"
                  :date-to="fmtDate(dateTo)"
                  :row-label="rowLabel(item).primary"
                  @apply-range="(p) => applyRangeForRow(item, p)"
                  @reset="resetRow(item)"
                />
              </div>

              <template v-if="item.kind === 'ticket'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell cell-ticket"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasTicketOverride(item.hotel, d) }"
                >
                  <PricingCell
                    :model-value="getTicketPrice(item.hotel, d)"
                    :placeholder="item.hotel.default_ticket_price"
                    :pending="pendingMap.has(pendingKeyForTicket(item.hotel.id, d))"
                    :error="errorKeys.has(pendingKeyForTicket(item.hotel.id, d))"
                    @change="(v: number) => onTicketChange(item.hotel, d, v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'room-price'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell cell-room"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasRoomEntry(item.room, d) }"
                >
                  <PricingCell
                    :model-value="getRoomPrice(item.room, d)"
                    :placeholder="item.room.base_price"
                    :pending="pendingMap.has(pendingKeyForRoom(item.room.id, d))"
                    :error="errorKeys.has(pendingKeyForRoom(item.room.id, d))"
                    @change="(v: number) => onRoomPriceChange(item.room, d, v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'room-stock'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell cell-stock"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasRoomEntry(item.room, d) }"
                >
                  <PricingCell
                    :model-value="getRoomStock(item.room, d)"
                    :placeholder="0"
                    :pending="pendingMap.has(pendingKeyForRoom(item.room.id, d))"
                    @change="(v: number) => onRoomStockChange(item.room, d, v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'surcharge'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell cell-sur"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasSurOverride(item.rule, d) }"
                >
                  <PricingCell
                    :model-value="getSurchargeAmount(item.rule, d)"
                    :placeholder="item.rule.amount"
                    :pending="pendingMap.has(pendingKeyForSur(item.rule.id, d))"
                    :error="errorKeys.has(pendingKeyForSur(item.rule.id, d))"
                    @change="(v: number) => onSurchargeChange(item.rule, d, v)"
                  />
                </div>
              </template>
            </div>
          </template>
        </VirtualScroller>
      </div>
    </div>

    <div class="legend">
      <span class="legend-item"><i class="pi pi-ticket" style="color: var(--p-blue-600)" /> Vé xe</span>
      <span class="legend-item"><i class="pi pi-home" style="color: var(--p-gray-700)" /> Giá phòng</span>
      <span class="legend-item"><i class="pi pi-box" style="color: var(--p-green-600)" /> Tồn kho</span>
      <span class="legend-item"><i class="pi pi-users" style="color: var(--p-amber-600)" /> Phụ thu trẻ</span>
      <span class="legend-item"><i class="dot bg-override" /> Đã override</span>
      <span class="legend-item"><i class="dot bg-weekend" /> Cuối tuần</span>
      <span class="legend-item"><i class="dot bg-today" /> Hôm nay</span>
      <span class="legend-item">Sửa giá rồi <kbd>Tab</kbd>/<kbd>Enter</kbd> · Tự lưu sau 600ms</span>
    </div>
  </div>
</template>

<style scoped>
.wrap { display: flex; flex-direction: column; gap: 0.75rem; }
.toolbar { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field label { font-size: 0.8rem; color: var(--p-text-muted-color); }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; align-self: center; }
.spacer { flex: 1; }
.pending-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.7rem; background: var(--p-yellow-100); color: var(--p-yellow-800); border-radius: 999px; font-size: 0.8rem; font-weight: 500; }
.loading { display: grid; place-items: center; min-height: 200px; }
.empty { text-align: center; padding: 3rem; color: var(--p-text-muted-color); }

.matrix-shell { border: 1px solid var(--p-surface-200); border-radius: 0.4rem; background: var(--p-surface-0); }
.matrix-inner { position: relative; }

.header-row {
  display: grid; position: sticky; top: 0; z-index: 5;
  background: var(--p-surface-100); border-bottom: 2px solid var(--p-surface-300);
}
.th { padding: 0.35rem 0.4rem; text-align: center; font-size: 0.72rem; font-weight: 500; border-right: 1px solid var(--p-surface-200); display: flex; align-items: center; justify-content: center; }
.th-date { white-space: pre-line; line-height: 1.15; }
.th-label { text-align: left; position: sticky; left: 0; background: var(--p-surface-100); z-index: 6; font-size: 0.8rem; font-weight: 600; padding-left: 0.75rem; }
.th-weekend { background: var(--p-yellow-50); color: var(--p-yellow-800); }
.th-today { background: var(--p-primary-100); color: var(--p-primary-800); font-weight: 600; border-left: 2px solid var(--p-primary-500); border-right: 2px solid var(--p-primary-500); }

.row {
  display: grid; border-bottom: 1px solid var(--p-surface-200);
}
.row-ticket { background: linear-gradient(to right, var(--p-blue-50) 0%, var(--p-blue-50) 30%, var(--p-surface-0) 100%); }
.row-room-price { background: var(--p-surface-0); }
.row-room-stock { background: var(--p-surface-50); }
.row-surcharge { background: var(--p-amber-50); }

.hotel-banner {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.4rem 0.85rem;
  background: linear-gradient(to right, var(--p-primary-100), var(--p-primary-50) 60%, var(--p-surface-0));
  border-top: 3px solid var(--p-primary-500);
  border-bottom: 1px solid var(--p-primary-200);
  position: sticky; left: 0;
}
.hotel-banner-icon { color: var(--p-primary-700); font-size: 1.1rem; flex-shrink: 0; }
.hotel-banner-text { min-width: 0; flex: 1; }
.hotel-banner-name {
  font-size: 0.95rem; font-weight: 700; color: var(--p-primary-900);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.hotel-banner-meta { font-size: 0.75rem; color: var(--p-primary-700); margin-top: 0.1rem; }

.cell {
  border-right: 1px solid var(--p-surface-200);
  display: flex; align-items: center; justify-content: center; padding: 0.15rem;
  overflow: hidden;
}
.cell.weekend { background: rgba(254, 243, 199, 0.4); }
.cell.today { border-left: 2px solid var(--p-primary-500); border-right: 2px solid var(--p-primary-500); background: rgba(219, 234, 254, 0.35); }
.cell.override { box-shadow: inset 0 -2px 0 var(--p-primary-500); }
.cell.today.weekend { background: linear-gradient(to bottom, rgba(254, 243, 199, 0.4), rgba(219, 234, 254, 0.4)); }

.label-cell {
  position: sticky; left: 0; z-index: 2;
  background: inherit;
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.3rem 0.6rem;
  border-right: 2px solid var(--p-surface-300);
}
.row-ticket .label-cell { background: var(--p-blue-50); }
.row-room-price .label-cell { background: var(--p-surface-0); }
.row-room-stock .label-cell { background: var(--p-surface-50); padding-left: 1.25rem; }
.row-surcharge .label-cell { background: var(--p-amber-50); padding-left: 1.75rem; border-left: 3px solid var(--p-amber-300); }

.row-icon { font-size: 0.9rem; flex-shrink: 0; }
.row-ticket .row-icon { color: var(--p-blue-600); }
.row-room-price .row-icon { color: var(--p-gray-700); }
.row-room-stock .row-icon { color: var(--p-green-600); }
.row-surcharge .row-icon { color: var(--p-amber-600); }
.row-room-price .lbl-primary { font-weight: 600; }
.label-text { min-width: 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
.lbl-primary { font-size: 0.82rem; font-weight: 500; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.row-room .lbl-primary { font-weight: 600; }
.lbl-secondary { font-size: 0.7rem; color: var(--p-text-muted-color); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.1rem; }

.legend { display: flex; gap: 1rem; padding: 0.4rem 0.6rem; font-size: 0.78rem; color: var(--p-text-muted-color); flex-wrap: wrap; align-items: center; }
.legend kbd { padding: 1px 4px; background: var(--p-surface-100); border: 1px solid var(--p-surface-300); border-radius: 3px; font-size: 0.7rem; }
.legend .dot { display: inline-block; width: 12px; height: 12px; border-radius: 3px; border: 1px solid var(--p-surface-300); margin-right: 0.25rem; vertical-align: middle; }
.bg-override { background: var(--p-primary-100); box-shadow: inset 0 -2px 0 var(--p-primary-500); }
.bg-weekend { background: var(--p-yellow-50); }
.bg-today { background: var(--p-primary-100); border-color: var(--p-primary-500); }
</style>
