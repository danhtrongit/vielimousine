<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';
import ProgressSpinner from 'primevue/progressspinner';
import VirtualScroller from 'primevue/virtualscroller';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { pricingApi, type CellChange } from '@/api/pricing.api';
import { roomPricesApi, type RoomPrice } from '@/api/roomPrices.api';
import type { SurchargePrice, Surcharge } from '@/api/surcharges.api';
import { ticketPricesApi, type TicketPrice } from '@/api/ticketPrices.api';
import { ymdLocal, decodeEntities } from '@/composables/useFormat';
import type { Hotel, Room } from '@/types/hotel';
import PricingCell from './PricingCell.vue';
import RowActionMenu from './RowActionMenu.vue';
import QuickUpdateDialog from './QuickUpdateDialog.vue';

const HOTEL_FILTER_STORAGE_KEY = 'pricing.matrix.hotelFilter.v1';

type Row =
  | { kind: 'hotel-header'; key: string; hotel: Hotel; roomsCount: number }
  | { kind: 'ticket'; key: string; hotel: Hotel }
  | { kind: 'room-header'; key: string; hotel: Hotel; room: Room; rulesCount: number }
  | { kind: 'room-price'; key: string; hotel: Hotel; room: Room }
  | { kind: 'extra-adult'; key: string; hotel: Hotel; room: Room }
  | { kind: 'surcharge'; key: string; hotel: Hotel; room: Room; rule: Surcharge }
  | { kind: 'room-stock'; key: string; hotel: Hotel; room: Room };

const lookup = useLookupStore();
const notify = useNotify();

const today = new Date();
const todayStr = ymdLocal(today);
const defaultEnd = new Date(today.getTime() + 13 * 24 * 3600 * 1000);
const dateFrom = ref<Date>(today);
const dateTo = ref<Date>(defaultEnd);

const loading = ref(false);
const flushing = ref(false);
const quickDialogVisible = ref(false);
const selectedHotelIds = ref<number[]>(loadHotelFilter());
const roomPriceMap = ref<Map<string, RoomPrice>>(new Map());
const surchargePriceMap = ref<Map<string, SurchargePrice>>(new Map());
const ticketPriceMap = ref<Map<string, TicketPrice>>(new Map());

function loadHotelFilter(): number[] {
  try {
    const raw = localStorage.getItem(HOTEL_FILTER_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed.filter((x) => typeof x === 'number') : [];
  } catch {
    return [];
  }
}

watch(selectedHotelIds, (ids) => {
  try { localStorage.setItem(HOTEL_FILTER_STORAGE_KEY, JSON.stringify(ids)); } catch { /* ignore quota */ }
}, { deep: true });

// Save queue: key = `${kind}_${entityId}_${date}`, value = pending CellChange
const pendingMap = ref<Map<string, CellChange>>(new Map());
const errorKeys = ref<Set<string>>(new Set());
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const DEBOUNCE_MS = 600;

const LABEL_WIDTH = 240;
const CELL_WIDTH = 110;
const ROW_HEIGHT = 40;
const ROOM_HEADER_HEIGHT = 64;
const HOTEL_HEADER_HEIGHT = 48;

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
function isToday(d: string): boolean { return d === todayStr; }

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

const filteredHotels = computed<Hotel[]>(() => {
  if (selectedHotelIds.value.length === 0) return lookup.hotels;
  const set = new Set(selectedHotelIds.value);
  return lookup.hotels.filter((h) => set.has(h.id));
});

function childRulesOfRoom(roomId: number): Surcharge[] {
  return lookup.surcharges
    .filter((s) => s.room_id === roomId && s.guest_type === 'child' && s.is_active)
    .sort((a, b) => (a.age_from - b.age_from) || (a.child_index_min - b.child_index_min));
}

const rows = computed<Row[]>(() => {
  const out: Row[] = [];
  for (const hotel of filteredHotels.value) {
    const hotelRooms = lookup.rooms
      .filter((r) => r.hotel_id === hotel.id)
      .sort((a, b) => a.name.localeCompare(b.name));
    out.push({ kind: 'hotel-header', key: `h-${hotel.id}`, hotel, roomsCount: hotelRooms.length });
    out.push({ kind: 'ticket', key: `t-${hotel.id}`, hotel });
    for (const room of hotelRooms) {
      const rules = childRulesOfRoom(room.id);
      out.push({ kind: 'room-header', key: `rh-${room.id}`, hotel, room, rulesCount: rules.length });
      out.push({ kind: 'room-price', key: `rp-${room.id}`, hotel, room });
      out.push({ kind: 'extra-adult', key: `ea-${room.id}`, hotel, room });
      for (const rule of rules) {
        out.push({ kind: 'surcharge', key: `s-${rule.id}`, hotel, room, rule });
      }
      out.push({ kind: 'room-stock', key: `rs-${room.id}`, hotel, room });
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
  if (selectedHotelIds.value.length > 0) {
    const validIds = new Set(lookup.hotels.map((h) => h.id));
    const cleaned = selectedHotelIds.value.filter((id) => validIds.has(id));
    if (cleaned.length !== selectedHotelIds.value.length) selectedHotelIds.value = cleaned;
  }
  loadAll();
});

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
    if (pendingMap.value.size > 0) void flush();
  }
});

// ── Getters ──
function getRoomPrice(room: Room, date: string): number {
  return roomPriceMap.value.get(roomKey(room.id, date))?.price ?? room.base_price ?? 0;
}
function getRoomExtraAdult(room: Room, date: string): number {
  return roomPriceMap.value.get(roomKey(room.id, date))?.extra_adult_price ?? room.extra_adult_price ?? 0;
}
function getRoomStock(room: Room, date: string): number {
  return roomPriceMap.value.get(roomKey(room.id, date))?.stock ?? 0;
}
function getTicketPrice(hotel: Hotel, date: string): number {
  return ticketPriceMap.value.get(ticketKey(hotel.id, date))?.ticket_price ?? hotel.default_ticket_price ?? 0;
}
function getSurchargeAmount(rule: Surcharge, date: string): number {
  return surchargePriceMap.value.get(surKey(rule.id, date))?.amount ?? rule.amount ?? 0;
}
function hasTicketOverride(hotel: Hotel, date: string): boolean {
  return ticketPriceMap.value.has(ticketKey(hotel.id, date));
}

function hasRoomPriceOverride(room: Room, date: string): boolean {
  return roomPriceMap.value.get(roomKey(room.id, date))?.price != null;
}
function hasExtraAdultOverride(room: Room, date: string): boolean {
  const r = roomPriceMap.value.get(roomKey(room.id, date));
  return r != null && r.extra_adult_price !== (room.extra_adult_price ?? 0);
}
function hasStockEntry(room: Room, date: string): boolean {
  return roomPriceMap.value.has(roomKey(room.id, date));
}
function hasSurchargeOverride(rule: Surcharge, date: string): boolean {
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
    const newErrors = new Set<string>();
    for (const err of resp.data.errors) {
      const [k] = entries[err.index] ?? [];
      if (k) newErrors.add(k);
    }
    for (let i = 0; i < entries.length; i++) {
      const [k] = entries[i];
      if (!newErrors.has(k)) pendingMap.value.delete(k);
    }
    errorKeys.value = newErrors;
    pendingMap.value = new Map(pendingMap.value);

    if (resp.data.errors.length > 0) {
      notify.warn(`Lưu ${resp.data.saved} thay đổi, ${resp.data.errors.length} lỗi`);
    }
    await loadAll();
  } catch (e) {
    notify.apiError(e);
    for (const [k] of entries) errorKeys.value.add(k);
  } finally {
    flushing.value = false;
  }
}

// ── Optimistic update handlers ──
function setRoomPriceField(room: Room, date: string, field: 'price' | 'extra_adult_price' | 'stock', value: number) {
  const existing = roomPriceMap.value.get(roomKey(room.id, date));
  const optimistic: RoomPrice = existing
    ? { ...existing, [field]: value }
    : {
        id: 0, room_id: room.id, date,
        price: field === 'price' ? value : (room.base_price ?? 0),
        extra_adult_price: field === 'extra_adult_price' ? value : (room.extra_adult_price ?? 0),
        stock: field === 'stock' ? value : 0,
        is_active: true, source: 'manual',
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
      stock: optimistic.stock,
      is_active: optimistic.is_active,
      source: 'manual',
    },
  });
}

function onTicketChange(hotel: Hotel, date: string, value: number) {
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

// ── Row action handlers ──
async function applyRangeForRow(
  row: Row,
  payload: { value: number; from: string; to: string; weekdays: number[] | null },
): Promise<void> {
  flushing.value = true;
  try {
    if (row.kind === 'ticket') {
      await ticketPricesApi.bulk(
        { hotel_ids: [row.hotel.id], route_id: 0, date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { ticket_price: payload.value },
      );
    } else if (row.kind === 'room-price') {
      await roomPricesApi.bulk(
        { room_ids: [row.room.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { price: payload.value },
      );
    } else if (row.kind === 'extra-adult') {
      await roomPricesApi.bulk(
        { room_ids: [row.room.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { extra_adult_price: payload.value },
      );
    } else if (row.kind === 'room-stock') {
      await roomPricesApi.bulk(
        { room_ids: [row.room.id], date_from: payload.from, date_to: payload.to, weekdays: payload.weekdays },
        { stock: payload.value },
      );
    } else {
      return;
    }
    notify.success('Đã áp dụng hàng loạt');
    await loadAll();
  } catch (e) {
    notify.apiError(e);
  } finally {
    flushing.value = false;
  }
}

async function resetRow(row: Row): Promise<void> {
  if (row.kind === 'hotel-header' || row.kind === 'room-header') return;
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
    } else if (row.kind === 'room-price' || row.kind === 'extra-adult' || row.kind === 'room-stock') {
      await roomPricesApi.bulk({ room_ids: [row.room.id], ...scope }, { is_active: false });
    }
    notify.success('Đã reset');
    await loadAll();
  } catch (e) {
    notify.apiError(e);
  } finally {
    flushing.value = false;
  }
}

function ruleLabel(rule: Surcharge): string {
  return `PT TE ${rule.age_from}-${rule.age_to}`;
}

function rowLabel(row: Row): string {
  switch (row.kind) {
    case 'ticket':      return 'Giá vé';
    case 'room-price':  return 'Giá phòng';
    case 'extra-adult': return 'PT NL';
    case 'surcharge':   return ruleLabel(row.rule);
    case 'room-stock':  return 'Số lượng';
    default:            return '';
  }
}

function pendingKeyForTicket(hid: number, date: string) { return `ticket_${hid}_${date}`; }
function pendingKeyForRoom(rid: number, date: string)   { return `room_${rid}_${date}`; }
function pendingKeyForSur(sid: number, date: string)    { return `sur_${sid}_${date}`; }

function rowCurrentValue(row: Row): number {
  if (row.kind === 'ticket')      return getTicketPrice(row.hotel, fmtDate(dateFrom.value));
  if (row.kind === 'room-price')  return getRoomPrice(row.room, fmtDate(dateFrom.value));
  if (row.kind === 'extra-adult') return getRoomExtraAdult(row.room, fmtDate(dateFrom.value));
  if (row.kind === 'room-stock')  return getRoomStock(row.room, fmtDate(dateFrom.value));
  if (row.kind === 'surcharge')   return getSurchargeAmount(row.rule, fmtDate(dateFrom.value));
  return 0;
}

function rowHeight(row: Row): number {
  if (row.kind === 'hotel-header') return HOTEL_HEADER_HEIGHT;
  if (row.kind === 'room-header')  return ROOM_HEADER_HEIGHT;
  return ROW_HEIGHT;
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
      <div class="field field-hotel-filter">
        <label>Khách sạn</label>
        <MultiSelect
          v-model="selectedHotelIds"
          :options="lookup.hotels"
          option-label="name"
          option-value="id"
          :placeholder="`Tất cả (${lookup.hotels.length})`"
          display="chip"
          filter
          filter-placeholder="Tìm khách sạn…"
          :max-selected-labels="2"
          :selected-items-label="`{0} / ${lookup.hotels.length} khách sạn`"
          class="hotel-filter"
        />
      </div>
      <Button label="Tải lại" icon="pi pi-refresh" outlined @click="loadAll" :loading="loading" />
      <Button label="Cập nhật nhanh" icon="pi pi-bolt" severity="warn" @click="quickDialogVisible = true" />
      <Button
        v-if="selectedHotelIds.length > 0"
        label="Xóa lọc"
        icon="pi pi-times"
        size="small"
        text
        severity="secondary"
        @click="selectedHotelIds = []"
      />
      <span class="spacer" />
      <span v-if="pendingCount > 0" class="pending-badge">
        <i class="pi pi-clock" />
        {{ pendingCount }} thay đổi đang chờ
        <Button label="Lưu ngay" size="small" severity="warn" :loading="flushing" @click="flush" />
      </span>
      <span v-else-if="flushing" class="pending-badge"><i class="pi pi-spin pi-spinner" /> Đang lưu…</span>
    </div>

    <QuickUpdateDialog
      v-model:visible="quickDialogVisible"
      :default-hotel-ids="selectedHotelIds"
      :default-date-from="fmtDate(dateFrom)"
      :default-date-to="fmtDate(dateTo)"
      :pending-count="pendingCount"
      @applied="loadAll"
    />

    <div v-if="loading && rows.length === 0" class="loading"><ProgressSpinner style="width: 40px;height: 40px" /></div>
    <div v-else-if="rows.length === 0" class="empty">Chưa có KS/phòng nào.</div>

    <div v-else class="matrix-shell" :style="{ width: '100%', overflowX: 'auto' }">
      <div class="matrix-inner" :style="{ width: totalWidth + 'px' }">
        <div class="header-row" :style="{ gridTemplateColumns }">
          <div class="th th-label">Phòng / Thành phần giá</div>
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
            <!-- Hotel banner -->
            <div
              v-if="item.kind === 'hotel-header'"
              class="hotel-banner"
              :style="{ width: totalWidth + 'px', height: rowHeight(item) + 'px' }"
            >
              {{ decodeEntities(item.hotel.name) }}<span v-if="item.hotel.star_rating"> - {{ item.hotel.star_rating }} sao</span>
            </div>

            <!-- Room header banner (full-width, sticky label) -->
            <div
              v-else-if="item.kind === 'room-header'"
              class="room-banner"
              :style="{ width: totalWidth + 'px', height: rowHeight(item) + 'px' }"
            >
              <div class="room-banner-label">
                <strong>{{ decodeEntities(item.room.name) }}</strong>
                <span class="room-banner-meta">
                  {{ item.room.included_adults }}-{{ item.room.max_adults }} người lớn<span v-if="item.room.max_children > 0">, tối đa {{ item.room.max_children }} trẻ em</span>
                </span>
                <span v-if="item.rulesCount > 0" class="room-banner-rules">{{ item.rulesCount }} nhóm tuổi</span>
              </div>
            </div>

            <!-- Data row -->
            <div
              v-else
              :class="['row', `row-${item.kind}`]"
              :style="{ gridTemplateColumns, height: rowHeight(item) + 'px' }"
            >
              <div class="cell label-cell">
                <span class="lbl">{{ rowLabel(item) }}</span>
                <RowActionMenu
                  v-if="item.kind === 'ticket' || item.kind === 'room-price' || item.kind === 'extra-adult' || item.kind === 'room-stock'"
                  :current-value="rowCurrentValue(item)"
                  :date-from="fmtDate(dateFrom)"
                  :date-to="fmtDate(dateTo)"
                  :row-label="rowLabel(item)"
                  @apply-range="(p) => applyRangeForRow(item, p)"
                  @reset="resetRow(item)"
                />
              </div>

              <template v-if="item.kind === 'ticket'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasTicketOverride(item.hotel, d) }"
                >
                  <PricingCell
                    :model-value="getTicketPrice(item.hotel, d)"
                    :placeholder="item.hotel.default_ticket_price"
                    :pending="pendingMap.has(pendingKeyForTicket(item.hotel.id, d))"
                    :error="errorKeys.has(pendingKeyForTicket(item.hotel.id, d))"
                    :is-default="!hasTicketOverride(item.hotel, d)"
                    @change="(v: number) => onTicketChange(item.hotel, d, v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'room-price'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasRoomPriceOverride(item.room, d) }"
                >
                  <PricingCell
                    :model-value="getRoomPrice(item.room, d)"
                    :placeholder="item.room.base_price"
                    :pending="pendingMap.has(pendingKeyForRoom(item.room.id, d))"
                    :error="errorKeys.has(pendingKeyForRoom(item.room.id, d))"
                    :is-default="!hasRoomPriceOverride(item.room, d)"
                    @change="(v: number) => setRoomPriceField(item.room, d, 'price', v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'extra-adult'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasExtraAdultOverride(item.room, d) }"
                >
                  <PricingCell
                    :model-value="getRoomExtraAdult(item.room, d)"
                    :placeholder="item.room.extra_adult_price"
                    :pending="pendingMap.has(pendingKeyForRoom(item.room.id, d))"
                    :error="errorKeys.has(pendingKeyForRoom(item.room.id, d))"
                    :is-default="!hasExtraAdultOverride(item.room, d)"
                    @change="(v: number) => setRoomPriceField(item.room, d, 'extra_adult_price', v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'surcharge'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasSurchargeOverride(item.rule, d) }"
                >
                  <PricingCell
                    :model-value="getSurchargeAmount(item.rule, d)"
                    :placeholder="item.rule.amount"
                    :pending="pendingMap.has(pendingKeyForSur(item.rule.id, d))"
                    :error="errorKeys.has(pendingKeyForSur(item.rule.id, d))"
                    :is-default="!hasSurchargeOverride(item.rule, d)"
                    @change="(v: number) => onSurchargeChange(item.rule, d, v)"
                  />
                </div>
              </template>

              <template v-else-if="item.kind === 'room-stock'">
                <div
                  v-for="d in dateRange"
                  :key="d"
                  class="cell"
                  :class="{ weekend: isWeekend(d), today: isToday(d), override: hasStockEntry(item.room, d) }"
                >
                  <PricingCell
                    :model-value="getRoomStock(item.room, d)"
                    :placeholder="0"
                    :pending="pendingMap.has(pendingKeyForRoom(item.room.id, d))"
                    :is-default="!hasStockEntry(item.room, d)"
                    @change="(v: number) => setRoomPriceField(item.room, d, 'stock', v)"
                  />
                </div>
              </template>
            </div>
          </template>
        </VirtualScroller>
      </div>
    </div>

    <div class="legend">
      <span class="legend-item"><i class="dot bg-override" /> Đã override</span>
      <span class="legend-item"><i class="dot bg-weekend" /> Cuối tuần</span>
      <span class="legend-item"><i class="dot bg-today" /> Hôm nay</span>
      <span class="legend-item"><em>Giá trị mặc định</em> · <strong>Giá trị override</strong></span>
      <span class="legend-item">Sửa giá rồi <kbd>Tab</kbd>/<kbd>Enter</kbd> · Tự lưu sau 600ms</span>
    </div>
  </div>
</template>

<style scoped>
.wrap { display: flex; flex-direction: column; gap: 0.75rem; }
.toolbar { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field label { font-size: 0.8rem; color: var(--p-text-muted-color); }
.field-hotel-filter { min-width: 240px; max-width: 360px; }
.hotel-filter { min-width: 240px; }
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
.th-label { text-align: left; position: sticky; left: 0; background: var(--p-surface-100); z-index: 6; font-size: 0.82rem; font-weight: 600; padding-left: 0.75rem; }
.th-weekend { background: var(--p-yellow-50); color: var(--p-yellow-800); }
.th-today { background: var(--p-primary-100); color: var(--p-primary-800); font-weight: 600; border-left: 2px solid var(--p-primary-500); border-right: 2px solid var(--p-primary-500); }

/* Hotel banner — dark like the reference image */
.hotel-banner {
  display: flex; align-items: center;
  padding: 0 1rem;
  background: #1f2937;
  color: #fff;
  font-size: 1rem; font-weight: 700;
  position: sticky; left: 0;
  border-bottom: 1px solid var(--p-surface-300);
}

/* Room header banner — full-width strip; label sticks left */
.room-banner {
  display: flex;
  background: var(--p-surface-0);
  border-bottom: 1px solid var(--p-surface-200);
  border-top: 1px solid var(--p-surface-200);
}
.room-banner-label {
  position: sticky; left: 0; z-index: 3;
  background: var(--p-surface-0);
  display: flex; flex-direction: column; justify-content: center;
  padding: 0.45rem 0.75rem;
  gap: 0.15rem;
  min-width: 240px;
}
.room-banner-label strong { font-size: 0.95rem; font-weight: 700; line-height: 1.2; }
.room-banner-meta { font-size: 0.75rem; color: var(--p-text-muted-color); }
.room-banner-rules { font-size: 0.72rem; color: var(--p-green-700); font-weight: 500; }

.row {
  display: grid; border-bottom: 1px solid var(--p-surface-200);
}
.row-ticket      { background: #fff7ed; }
.row-room-price  { background: var(--p-blue-50); }
.row-extra-adult { background: var(--p-surface-0); }
.row-surcharge   { background: #ecfdf5; }
.row-room-stock  { background: #f5f3ff; }

.cell {
  border-right: 1px solid var(--p-surface-200);
  display: flex; align-items: center; justify-content: center; padding: 0;
  overflow: hidden;
  font-variant-numeric: tabular-nums;
}
.cell.override { box-shadow: inset 0 -2px 0 var(--p-primary-500); }

.label-cell {
  position: sticky; left: 0; z-index: 2;
  background: inherit;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 0.75rem;
  border-right: 1px solid var(--p-surface-200);
  font-size: 0.85rem;
}
.row-ticket      .label-cell { background: #fff7ed;            color: #c2410c; font-weight: 600; }
.row-room-price  .label-cell { background: var(--p-blue-50);   color: var(--p-blue-800); font-weight: 600; }
.row-extra-adult .label-cell { background: var(--p-surface-0); color: var(--p-surface-700); }
.row-surcharge   .label-cell { background: #ecfdf5;            color: var(--p-green-800); }
.row-room-stock  .label-cell { background: #f5f3ff;            color: #6d28d9; font-weight: 600; }

.lbl { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.legend { display: flex; gap: 1rem; padding: 0.4rem 0.6rem; font-size: 0.78rem; color: var(--p-text-muted-color); flex-wrap: wrap; align-items: center; }
.legend kbd { padding: 1px 4px; background: var(--p-surface-100); border: 1px solid var(--p-surface-300); border-radius: 3px; font-size: 0.7rem; }
.legend .dot { display: inline-block; width: 12px; height: 12px; border-radius: 3px; border: 1px solid var(--p-surface-300); margin-right: 0.25rem; vertical-align: middle; }
.bg-override { background: var(--p-primary-100); box-shadow: inset 0 -2px 0 var(--p-primary-500); }
.bg-weekend { background: var(--p-yellow-50); }
.bg-today { background: var(--p-primary-100); border-color: var(--p-primary-500); }
</style>
