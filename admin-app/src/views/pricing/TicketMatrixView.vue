<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Calendar from 'primevue/calendar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import ProgressSpinner from 'primevue/progressspinner';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { ticketPricesApi, type TicketPrice } from '@/api/ticketPrices.api';

const lookup = useLookupStore();
const notify = useNotify();

const today = new Date();
const defaultEnd = new Date(today.getTime() + 13 * 24 * 3600 * 1000);
const dateFrom = ref<Date>(today);
const dateTo = ref<Date>(defaultEnd);

const loading = ref(false);
const ticketMap = ref<Map<string, TicketPrice>>(new Map());

const editDialog = ref(false);
const editCell = ref<{ hotelId: number; date: string; existing: TicketPrice | null }>({
  hotelId: 0, date: '', existing: null,
});
const editPrice = ref(0);
const saving = ref(false);

function fmtDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}
function fmtDateVN(d: string): string {
  const date = new Date(d);
  return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}
const cellKey = (hid: number, date: string) => `${hid}_${date}`;

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

function fmtVNDCompact(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${Math.round(n / 1000)}K`;
  return String(n);
}

async function loadPrices() {
  if (lookup.hotels.length === 0) return;
  loading.value = true;
  try {
    const resp = await ticketPricesApi.list({
      date_from: fmtDate(dateFrom.value),
      date_to: fmtDate(dateTo.value),
      per_page: 1000,
    });
    const map = new Map<string, TicketPrice>();
    for (const p of resp.data) {
      map.set(cellKey(p.hotel_id, p.date), p);
    }
    ticketMap.value = map;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

watch([dateFrom, dateTo], loadPrices);

onMounted(async () => {
  await lookup.ensureLoaded();
  loadPrices();
});

function openEdit(hotelId: number, date: string) {
  const existing = ticketMap.value.get(cellKey(hotelId, date)) ?? null;
  const hotel = lookup.hotelById(hotelId);
  editCell.value = { hotelId, date, existing };
  editPrice.value = existing?.ticket_price ?? hotel?.default_ticket_price ?? 0;
  editDialog.value = true;
}

async function saveCell() {
  saving.value = true;
  try {
    const { hotelId, date, existing } = editCell.value;
    const body = {
      hotel_id: hotelId,
      route_id: 0,
      date,
      ticket_price: editPrice.value,
      is_active: true,
    };
    let saved: TicketPrice;
    if (existing) {
      const resp = await ticketPricesApi.update(existing.id, body);
      saved = resp.data;
    } else {
      const resp = await ticketPricesApi.create(body);
      saved = resp.data;
    }
    ticketMap.value.set(cellKey(hotelId, date), saved);
    ticketMap.value = new Map(ticketMap.value);
    notify.success('Đã lưu giá vé');
    editDialog.value = false;
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="matrix-wrapper">
    <div class="toolbar">
      <div class="field">
        <label>Từ ngày</label>
        <Calendar v-model="dateFrom" date-format="yy-mm-dd" show-icon />
      </div>
      <div class="field">
        <label>Đến ngày</label>
        <Calendar v-model="dateTo" date-format="yy-mm-dd" show-icon />
      </div>
      <Button label="Tải lại" icon="pi pi-refresh" outlined @click="loadPrices" :loading="loading" />
    </div>

    <div v-if="loading" class="loading"><ProgressSpinner style="width: 40px;height: 40px" /></div>
    <div v-else class="matrix-scroll">
      <table class="matrix">
        <thead>
          <tr>
            <th class="frozen">Khách sạn</th>
            <th v-for="d in dateRange" :key="d">{{ fmtDateVN(d) }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="hotel in lookup.hotels" :key="hotel.id">
            <td class="frozen">
              <div>{{ hotel.name }}</div>
              <small>Mặc định: {{ fmtVNDCompact(hotel.default_ticket_price) }}</small>
            </td>
            <td
              v-for="d in dateRange"
              :key="d"
              :class="ticketMap.get(cellKey(hotel.id, d)) ? 'cell cell-set' : 'cell cell-default'"
              @click="openEdit(hotel.id, d)"
            >
              <template v-if="ticketMap.get(cellKey(hotel.id, d))">
                {{ fmtVNDCompact(ticketMap.get(cellKey(hotel.id, d))!.ticket_price) }}
              </template>
              <template v-else>
                <span class="muted">{{ fmtVNDCompact(hotel.default_ticket_price) }}</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="editDialog" header="Sửa giá vé" :modal="true" :style="{ width: '400px' }">
      <div class="dialog-content">
        <p class="muted">
          {{ lookup.hotelById(editCell.hotelId)?.name }} · {{ editCell.date }}
        </p>
        <div class="field">
          <label>Giá vé (VND)</label>
          <InputNumber v-model="editPrice" :min="0" />
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" severity="secondary" text @click="editDialog = false" />
        <Button label="Lưu" :loading="saving" @click="saveCell" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.matrix-wrapper { display: flex; flex-direction: column; gap: 1rem; }
.toolbar { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field label { font-size: 0.8rem; color: var(--p-text-muted-color); }
.loading { display: grid; place-items: center; min-height: 200px; }
.matrix-scroll { overflow-x: auto; max-height: 70vh; }
.matrix { border-collapse: collapse; font-size: 0.85rem; }
.matrix th, .matrix td { border: 1px solid var(--p-surface-200); padding: 0.4rem 0.5rem; text-align: center; min-width: 64px; }
.matrix th { background: var(--p-surface-100); position: sticky; top: 0; z-index: 2; }
.frozen { position: sticky; left: 0; background: var(--p-surface-0); text-align: left; min-width: 220px; z-index: 1; }
.matrix th.frozen { z-index: 3; }
.frozen small { display: block; color: var(--p-text-muted-color); font-size: 0.7rem; }
.cell { cursor: pointer; }
.cell:hover { background: var(--p-primary-50); }
.cell-default { color: var(--p-text-muted-color); }
.cell-set { background: var(--p-primary-50); font-weight: 600; }
.dialog-content { display: flex; flex-direction: column; gap: 0.75rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
</style>
