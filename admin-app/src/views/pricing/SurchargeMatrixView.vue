<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import ProgressSpinner from 'primevue/progressspinner';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { surchargePricesApi, type SurchargePrice } from '@/api/surcharges.api';

const lookup = useLookupStore();
const notify = useNotify();

const hotelId = ref<number | null>(null);
const today = new Date();
const defaultEnd = new Date(today.getTime() + 13 * 24 * 3600 * 1000);
const dateFrom = ref<Date>(today);
const dateTo = ref<Date>(defaultEnd);

const loading = ref(false);
const overrideMap = ref<Map<string, SurchargePrice>>(new Map());

const editDialog = ref(false);
const editCell = ref<{ surchargeId: number; date: string; existing: SurchargePrice | null }>({
  surchargeId: 0, date: '', existing: null,
});
const editAmount = ref(0);
const saving = ref(false);

function fmtDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}
function fmtDateVN(d: string): string {
  const date = new Date(d);
  return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}
const cellKey = (sid: number, date: string) => `${sid}_${date}`;

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

const surcharges = computed(() => {
  if (hotelId.value === null) return [];
  const roomIds = lookup.roomsByHotel(hotelId.value).map((r) => r.id);
  return lookup.surcharges.filter((s) => roomIds.includes(s.room_id));
});

function fmtVNDCompact(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${Math.round(n / 1000)}K`;
  return String(n);
}

async function loadOverrides() {
  if (surcharges.value.length === 0) {
    overrideMap.value = new Map();
    return;
  }
  loading.value = true;
  try {
    const resp = await surchargePricesApi.list({
      date_from: fmtDate(dateFrom.value),
      date_to: fmtDate(dateTo.value),
      per_page: 1000,
    });
    const ids = surcharges.value.map((s) => s.id);
    const map = new Map<string, SurchargePrice>();
    for (const p of resp.data) {
      if (ids.includes(p.surcharge_id)) {
        map.set(cellKey(p.surcharge_id, p.date), p);
      }
    }
    overrideMap.value = map;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

watch([hotelId, dateFrom, dateTo], loadOverrides);

onMounted(async () => {
  await lookup.ensureLoaded();
  if (lookup.hotels.length > 0) hotelId.value = lookup.hotels[0].id;
});

function openEdit(surchargeId: number, date: string) {
  const existing = overrideMap.value.get(cellKey(surchargeId, date)) ?? null;
  const rule = lookup.surcharges.find((s) => s.id === surchargeId);
  editCell.value = { surchargeId, date, existing };
  editAmount.value = existing?.amount ?? rule?.amount ?? 0;
  editDialog.value = true;
}

async function saveCell() {
  saving.value = true;
  try {
    const { surchargeId, date, existing } = editCell.value;
    const body = {
      surcharge_id: surchargeId,
      date,
      amount: editAmount.value,
      is_active: true,
    };
    let saved: SurchargePrice;
    if (existing) {
      const resp = await surchargePricesApi.update(existing.id, body);
      saved = resp.data;
    } else {
      const resp = await surchargePricesApi.create(body);
      saved = resp.data;
    }
    overrideMap.value.set(cellKey(surchargeId, date), saved);
    overrideMap.value = new Map(overrideMap.value);
    notify.success('Đã lưu phụ thu');
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
        <label>Khách sạn</label>
        <Dropdown v-model="hotelId" :options="lookup.hotels" option-label="name" option-value="id" />
      </div>
      <div class="field">
        <label>Từ ngày</label>
        <Calendar v-model="dateFrom" date-format="yy-mm-dd" show-icon />
      </div>
      <div class="field">
        <label>Đến ngày</label>
        <Calendar v-model="dateTo" date-format="yy-mm-dd" show-icon />
      </div>
      <Button label="Tải lại" icon="pi pi-refresh" outlined @click="loadOverrides" :loading="loading" />
    </div>

    <div v-if="loading" class="loading"><ProgressSpinner style="width: 40px;height: 40px" /></div>
    <div v-else-if="surcharges.length === 0" class="empty">Không có rule phụ thu cho khách sạn này</div>
    <div v-else class="matrix-scroll">
      <table class="matrix">
        <thead>
          <tr>
            <th class="frozen">Rule</th>
            <th v-for="d in dateRange" :key="d">{{ fmtDateVN(d) }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rule in surcharges" :key="rule.id">
            <td class="frozen">
              <div>{{ rule.label }}</div>
              <small>{{ lookup.roomById(rule.room_id)?.name }} · {{ rule.age_from }}-{{ rule.age_to }}T</small>
            </td>
            <td
              v-for="d in dateRange"
              :key="d"
              :class="overrideMap.get(cellKey(rule.id, d)) ? 'cell cell-override' : 'cell cell-default'"
              @click="openEdit(rule.id, d)"
            >
              <template v-if="overrideMap.get(cellKey(rule.id, d))">
                {{ fmtVNDCompact(overrideMap.get(cellKey(rule.id, d))!.amount) }}
              </template>
              <template v-else>
                <span class="muted">{{ fmtVNDCompact(rule.amount) }}</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="legend">
      <span class="legend-item"><i class="dot cell-default" /> Mặc định (theo rule)</span>
      <span class="legend-item"><i class="dot cell-override" /> Đã override</span>
    </div>

    <Dialog v-model:visible="editDialog" header="Sửa phụ thu" :modal="true" :style="{ width: '400px' }">
      <div class="dialog-content">
        <p class="muted">
          {{ lookup.surcharges.find(s => s.id === editCell.surchargeId)?.label }} · {{ editCell.date }}
        </p>
        <div class="field">
          <label>Số tiền phụ thu (VND)</label>
          <InputNumber v-model="editAmount" :min="0" />
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
.empty { text-align: center; padding: 3rem; color: var(--p-text-muted-color); }
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
.cell-override { background: var(--p-primary-50); font-weight: 600; }
.legend { display: flex; gap: 1rem; padding: 0.5rem; font-size: 0.8rem; color: var(--p-text-muted-color); }
.legend-item { display: flex; align-items: center; gap: 0.35rem; }
.legend .dot { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid var(--p-surface-300); }
.dialog-content { display: flex; flex-direction: column; gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
</style>
