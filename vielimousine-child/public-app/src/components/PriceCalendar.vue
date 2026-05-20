<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '@/api/client';
import type { RoomPricesResponse, RoomPriceRow } from '@/api/types';
import { formatCompact, ymd, addDays, isWeekend } from '@/composables/useFormat';
import { search } from '@/composables/useBookingState';

const props = defineProps<{ roomId: number; basePrice: number }>();

const month = ref(new Date());
const priceMap = ref<Map<string, RoomPriceRow>>(new Map());
const loading = ref(false);
const todayStr = ymd(new Date());

// Calendar grid: 6 weeks × 7 days. Start from first Monday on/before month 1st.
const days = computed(() => {
  const first = new Date(month.value.getFullYear(), month.value.getMonth(), 1);
  // Vietnamese week: Mon=1..Sun=7. JS getDay() Sun=0..Sat=6 → shift.
  const dow = first.getDay(); // 0..6, 0=Sun
  const offset = dow === 0 ? 6 : dow - 1;
  const start = addDays(first, -offset);
  const list: Date[] = [];
  for (let i = 0; i < 42; i++) list.push(addDays(start, i));
  return list;
});

const monthTitle = computed(() => {
  const m = month.value.getMonth() + 1;
  return `Tháng ${m}/${month.value.getFullYear()}`;
});

const dateFrom = computed(() => ymd(days.value[0]));
const dateTo = computed(() => ymd(days.value[days.value.length - 1]));

// Price tiers — green = cheap, orange = avg, red = expensive.
// Compute min/max from visible prices.
const stats = computed(() => {
  const vals = Array.from(priceMap.value.values()).map((p) => p.price).filter((v) => v > 0);
  if (vals.length === 0) return { min: props.basePrice, max: props.basePrice };
  const base = props.basePrice > 0 ? [props.basePrice] : [];
  return { min: Math.min(...vals, ...base), max: Math.max(...vals, ...base) };
});

// Fallback price for dates without a configured row.
// Priority: row.price (if > 0) > room.base_price (if > 0) > min price from any row.
const fallbackPrice = computed(() => {
  if (props.basePrice > 0) return props.basePrice;
  const vals = Array.from(priceMap.value.values()).map((p) => p.price).filter((v) => v > 0);
  return vals.length ? Math.min(...vals) : 0;
});

function priceForDate(d: Date): number {
  const r = priceMap.value.get(ymd(d));
  if (r && r.price > 0) return r.price;
  return fallbackPrice.value;
}

function stockForDate(d: Date): number | null {
  const r = priceMap.value.get(ymd(d));
  return r ? r.stock : null;
}

function tierClass(d: Date): string {
  const p = priceForDate(d);
  if (p <= 0) return '';
  const { min, max } = stats.value;
  if (max === min) return 'tier-mid';
  const t = (p - min) / (max - min);
  if (t < 0.34) return 'tier-low';
  if (t < 0.67) return 'tier-mid';
  return 'tier-high';
}

function classFor(d: Date): Record<string, boolean> {
  const ds = ymd(d);
  const inMonth = d.getMonth() === month.value.getMonth();
  const isCheckin = ds === search.checkin;
  const isCheckout = ds === search.checkout;
  const isBetween = ds > search.checkin && ds < search.checkout;
  const isPast = ds < todayStr;
  const stock = stockForDate(d);
  return {
    'vh-cal-day': true,
    'out-of-month': !inMonth,
    'is-today': ds === todayStr,
    'is-weekend': isWeekend(d),
    'is-past': isPast,
    'is-checkin': isCheckin,
    'is-checkout': isCheckout,
    'is-between': isBetween,
    'soldout': stock === 0,
    [tierClass(d)]: !isPast && stock !== 0,
  };
}

async function fetchPrices() {
  loading.value = true;
  try {
    const data = await api.get<RoomPricesResponse>('public/room-prices', {
      room_id: props.roomId,
      date_from: dateFrom.value,
      date_to: dateTo.value,
    });
    const m = new Map<string, RoomPriceRow>();
    for (const row of data.prices) m.set(row.date, row);
    priceMap.value = m;
  } catch {
    priceMap.value = new Map();
  } finally {
    loading.value = false;
  }
}

function prevMonth() {
  month.value = new Date(month.value.getFullYear(), month.value.getMonth() - 1, 1);
}
function nextMonth() {
  month.value = new Date(month.value.getFullYear(), month.value.getMonth() + 1, 1);
}

// 2-click range picker: next click resets checkin (mode='start'), then sets checkout.
const pickMode = ref<'start' | 'end'>('start');

function pickDay(d: Date) {
  const ds = ymd(d);
  if (ds < todayStr) return;

  if (pickMode.value === 'start' || !search.checkin || ds < search.checkin) {
    search.checkin = ds;
    search.checkout = ymd(addDays(d, 1));
    pickMode.value = 'end';
  } else if (ds === search.checkin) {
    // Clicked checkin again → toggle off range, back to single-night default.
    search.checkout = ymd(addDays(d, 1));
    pickMode.value = 'start';
  } else {
    search.checkout = ds;
    pickMode.value = 'start';
  }
}

function clearSelection() {
  const today = new Date();
  const tomorrow = addDays(today, 1);
  search.checkin = ymd(today);
  search.checkout = ymd(tomorrow);
  pickMode.value = 'start';
}

onMounted(fetchPrices);
watch(month, fetchPrices);
</script>

<template>
  <div class="vh-cal">
    <div class="vh-cal-head">
      <button type="button" class="vh-cal-nav" @click="prevMonth" aria-label="Tháng trước">‹</button>
      <div class="vh-cal-title">{{ monthTitle }}</div>
      <button type="button" class="vh-cal-nav" @click="nextMonth" aria-label="Tháng sau">›</button>
    </div>
    <div class="vh-cal-grid vh-cal-dow">
      <div>T2</div><div>T3</div><div>T4</div><div>T5</div><div>T6</div><div>T7</div><div class="dow-sun">CN</div>
    </div>
    <div class="vh-cal-grid">
      <button
        v-for="(d, i) in days"
        :key="i"
        type="button"
        :class="classFor(d)"
        :disabled="ymd(d) < todayStr || stockForDate(d) === 0"
        @click="pickDay(d)"
      >
        <span class="vh-cal-num">{{ d.getDate() }}</span>
        <span v-if="stockForDate(d) === 0" class="vh-cal-price">Hết</span>
        <span v-else-if="ymd(d) >= todayStr && priceForDate(d) > 0" class="vh-cal-price">{{ formatCompact(priceForDate(d)) }}</span>
      </button>
    </div>
    <div class="vh-cal-footer">
      <div class="vh-cal-legend">
        <span class="vh-cal-leg-item"><i class="leg-dot tier-low" /> Giá rẻ</span>
        <span class="vh-cal-leg-item"><i class="leg-dot tier-mid" /> Trung bình</span>
        <span class="vh-cal-leg-item"><i class="leg-dot tier-high" /> Giá cao</span>
        <span class="vh-cal-leg-item"><i class="leg-dot soldout" /> Hết phòng</span>
      </div>
      <button type="button" class="vh-link vh-cal-clear" @click="clearSelection">
        <i class="pi pi-refresh" /> Xoá lựa chọn
      </button>
    </div>
    <p v-if="loading" class="vh-muted">Đang tải lịch giá…</p>
  </div>
</template>

<style scoped>
.vh-cal {
  margin: var(--space-3) 0;
  padding: var(--space-3);
  background: var(--app-muted-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-lg);
}
.vh-cal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-2);
}
.vh-cal-title {
  font-weight: 700;
  color: var(--app-text-strong);
}
.vh-cal-nav {
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-sm);
  padding: 0.25rem 0.75rem;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  color: var(--app-text);
  transition: background var(--motion-fast) var(--ease-out),
    border-color var(--motion-fast) var(--ease-out);
}
.vh-cal-nav:hover {
  background: var(--app-hover-bg);
  border-color: var(--p-primary-300);
}
.vh-cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 3px;
}
.vh-cal-dow {
  font-size: 0.72rem;
  color: var(--app-text-muted);
  text-align: center;
  margin-bottom: 4px;
  padding: 0 0.15rem;
  font-weight: 600;
}
.vh-cal-dow .dow-sun { color: var(--p-red-500); }
.vh-cal-day {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 52px;
  padding: 0.2rem 0.1rem;
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-sm);
  font-family: inherit;
  color: var(--app-text);
  cursor: pointer;
  transition: transform var(--motion-fast) var(--ease-out),
    border-color var(--motion-fast) var(--ease-out),
    box-shadow var(--motion-fast) var(--ease-out);
}
.vh-cal-day:hover:not(:disabled) {
  transform: scale(1.05);
  border-color: var(--p-primary-500);
  z-index: 1;
}
.vh-cal-day:disabled { cursor: not-allowed; opacity: 0.4; }
.vh-cal-day.out-of-month {
  background: var(--app-muted-bg);
  color: var(--app-text-subtle);
}
.vh-cal-day.is-past {
  opacity: 0.4;
  background: var(--app-hover-bg);
}
.vh-cal-day.is-today {
  box-shadow: 0 0 0 2px var(--p-blue-500) inset;
}
.vh-cal-day.is-weekend .vh-cal-num {
  color: var(--p-red-500);
  font-weight: 700;
}
.vh-cal-num { font-size: 0.85rem; font-weight: 500; }
.vh-cal-price {
  font-size: 0.65rem;
  color: var(--app-text-muted);
  line-height: 1;
  margin-top: 1px;
  font-variant-numeric: tabular-nums;
}
.vh-cal-day.tier-low { background: var(--app-tint-success); }
.vh-cal-day.tier-low .vh-cal-price { color: var(--app-on-tint-success); }
.vh-cal-day.tier-mid { background: var(--app-tint-warning); }
.vh-cal-day.tier-mid .vh-cal-price { color: var(--app-on-tint-warning); }
.vh-cal-day.tier-high { background: var(--app-tint-danger); }
.vh-cal-day.tier-high .vh-cal-price { color: var(--app-on-tint-danger); }
.vh-cal-day.soldout { background: var(--app-muted-bg); }
.vh-cal-day.soldout .vh-cal-price { color: var(--app-text-subtle); }
.vh-cal-day.is-checkin,
.vh-cal-day.is-checkout {
  background: var(--p-primary-500) !important;
  color: #fff !important;
  border-color: var(--p-primary-500);
}
.vh-cal-day.is-checkin .vh-cal-num,
.vh-cal-day.is-checkin .vh-cal-price,
.vh-cal-day.is-checkout .vh-cal-num,
.vh-cal-day.is-checkout .vh-cal-price {
  color: #fff !important;
}
.vh-cal-day.is-between {
  background: color-mix(in srgb, var(--p-primary-500), transparent 80%);
}
.vh-cal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
  margin-top: var(--space-2);
}
.vh-cal-legend {
  display: flex;
  gap: var(--space-3);
  flex-wrap: wrap;
  font-size: 0.75rem;
  color: var(--app-text-muted);
}
.vh-cal-clear {
  font-size: 0.85rem;
  color: var(--app-text-muted);
}
.vh-cal-clear:hover { color: var(--p-primary-500); }
.vh-cal-leg-item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}
.leg-dot {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: var(--radius-xs);
  border: 1px solid var(--app-card-border);
}
.leg-dot.tier-low { background: var(--app-tint-success); }
.leg-dot.tier-mid { background: var(--app-tint-warning); }
.leg-dot.tier-high { background: var(--app-tint-danger); }
.leg-dot.soldout { background: var(--app-muted-bg); }
.vh-muted {
  font-size: 0.85rem;
  color: var(--app-text-muted);
  margin-top: 0.4rem;
}
</style>
