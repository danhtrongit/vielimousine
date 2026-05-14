<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import ProgressSpinner from 'primevue/progressspinner';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { roomPricesApi, type RoomPrice } from '@/api/roomPrices.api';

const lookup = useLookupStore();
const notify = useNotify();

const hotelId = ref<number | null>(null);
const today = new Date();
const defaultEnd = new Date(today.getTime() + 29 * 24 * 3600 * 1000);
const dateFrom = ref<Date>(today);
const dateTo = ref<Date>(defaultEnd);

const loading = ref(false);
const priceMap = ref<Map<string, RoomPrice>>(new Map());

const editDialog = ref(false);
const editCell = ref<{ roomId: number; date: string; existing: RoomPrice | null }>({
  roomId: 0, date: '', existing: null,
});
const editForm = ref({ price: 0, extra_adult_price: 0, stock: 0, is_active: true });
const saving = ref(false);

function fmtDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

function fmtDateVN(d: string): string {
  const date = new Date(d);
  return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
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

const rooms = computed(() =>
  hotelId.value === null ? [] : lookup.roomsByHotel(hotelId.value)
);

const cellKey = (roomId: number, date: string): string => `${roomId}_${date}`;

function cellPrice(roomId: number, date: string): RoomPrice | null {
  return priceMap.value.get(cellKey(roomId, date)) ?? null;
}

function cellClass(roomId: number, date: string): string {
  const p = cellPrice(roomId, date);
  if (!p) return 'cell cell-empty';
  if (!p.is_active) return 'cell cell-inactive';
  if (p.stock === 0) return 'cell cell-soldout';
  if (p.stock <= 3) return 'cell cell-low';
  return 'cell cell-ok';
}

function fmtVNDCompact(n: number): string {
  if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000) return `${Math.round(n / 1000)}K`;
  return String(n);
}

async function loadPrices() {
  if (hotelId.value === null || rooms.value.length === 0) {
    priceMap.value = new Map();
    return;
  }
  loading.value = true;
  try {
    const roomIds = rooms.value.map((r) => r.id);
    const resp = await roomPricesApi.list({
      date_from: fmtDate(dateFrom.value),
      date_to: fmtDate(dateTo.value),
      per_page: 2000,
    });
    const map = new Map<string, RoomPrice>();
    for (const p of resp.data) {
      if (roomIds.includes(p.room_id)) {
        map.set(cellKey(p.room_id, p.date), p);
      }
    }
    priceMap.value = map;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

watch([hotelId, dateFrom, dateTo], loadPrices);

onMounted(async () => {
  await lookup.ensureLoaded();
  if (lookup.hotels.length > 0) {
    hotelId.value = lookup.hotels[0].id;
  }
});

function openEdit(roomId: number, date: string) {
  const existing = cellPrice(roomId, date);
  editCell.value = { roomId, date, existing };
  if (existing) {
    editForm.value = {
      price: existing.price,
      extra_adult_price: existing.extra_adult_price,
      stock: existing.stock,
      is_active: existing.is_active,
    };
  } else {
    const room = lookup.roomById(roomId);
    editForm.value = {
      price: room?.base_price ?? 0,
      extra_adult_price: room?.extra_adult_price ?? 0,
      stock: 5,
      is_active: true,
    };
  }
  editDialog.value = true;
}

async function saveCell() {
  saving.value = true;
  try {
    const { roomId, date, existing } = editCell.value;
    const body = {
      room_id: roomId,
      date,
      price: editForm.value.price,
      extra_adult_price: editForm.value.extra_adult_price,
      stock: editForm.value.stock,
      is_active: editForm.value.is_active,
      source: 'manual',
    };
    let saved: RoomPrice;
    if (existing) {
      const resp = await roomPricesApi.update(existing.id, body);
      saved = resp.data;
    } else {
      const resp = await roomPricesApi.create(body);
      saved = resp.data;
    }
    priceMap.value.set(cellKey(roomId, date), saved);
    priceMap.value = new Map(priceMap.value);
    notify.success('Đã lưu giá');
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
        <Dropdown
          v-model="hotelId"
          :options="lookup.hotels"
          option-label="name"
          option-value="id"
          placeholder="Chọn khách sạn"
        />
      </div>
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
    <div v-else-if="rooms.length === 0" class="empty">Chọn khách sạn để xem bảng giá</div>
    <div v-else class="matrix-scroll">
      <table class="matrix">
        <thead>
          <tr>
            <th class="frozen">Phòng</th>
            <th v-for="d in dateRange" :key="d">{{ fmtDateVN(d) }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="room in rooms" :key="room.id">
            <td class="frozen room-name">{{ room.name }}</td>
            <td
              v-for="d in dateRange"
              :key="d"
              :class="cellClass(room.id, d)"
              @click="openEdit(room.id, d)"
            >
              <template v-if="cellPrice(room.id, d)">
                <div class="price">{{ fmtVNDCompact(cellPrice(room.id, d)!.price) }}</div>
                <div class="stock">{{ cellPrice(room.id, d)!.stock }}p</div>
              </template>
              <template v-else>
                <div class="empty-dot">+</div>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="legend">
      <span class="legend-item"><i class="dot cell-ok" /> Còn nhiều</span>
      <span class="legend-item"><i class="dot cell-low" /> Còn ít (≤3)</span>
      <span class="legend-item"><i class="dot cell-soldout" /> Hết phòng</span>
      <span class="legend-item"><i class="dot cell-empty" /> Chưa cấu hình</span>
      <span class="legend-item"><i class="dot cell-inactive" /> Tạm ngưng</span>
    </div>

    <!-- Edit dialog -->
    <Dialog v-model:visible="editDialog" header="Sửa giá phòng" :modal="true" :style="{ width: '420px' }">
      <div class="dialog-content">
        <p class="muted">
          {{ lookup.roomById(editCell.roomId)?.name }} · {{ editCell.date }}
        </p>
        <div class="field">
          <label>Giá phòng (VND)</label>
          <InputNumber v-model="editForm.price" :min="0" />
        </div>
        <div class="field">
          <label>Phụ thu người lớn (VND)</label>
          <InputNumber v-model="editForm.extra_adult_price" :min="0" />
        </div>
        <div class="field">
          <label>Tồn phòng</label>
          <InputNumber v-model="editForm.stock" :min="0" show-buttons />
        </div>
        <div class="field">
          <label>Đang mở bán</label>
          <ToggleSwitch v-model="editForm.is_active" />
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
.matrix th { background: var(--p-surface-100); font-weight: 500; position: sticky; top: 0; z-index: 2; }
.frozen { position: sticky; left: 0; background: var(--p-surface-0); font-weight: 500; text-align: left; min-width: 180px; z-index: 1; }
.matrix th.frozen { z-index: 3; }
.room-name { background: var(--p-surface-50); }
.cell { cursor: pointer; transition: background 0.1s; }
.cell:hover { background: var(--p-primary-50) !important; }
.cell .price { font-weight: 600; }
.cell .stock { font-size: 0.7rem; color: var(--p-text-muted-color); }
.cell-ok { background: var(--p-green-50); }
.cell-low { background: var(--p-yellow-100); }
.cell-soldout { background: var(--p-red-100); color: var(--p-red-700); }
.cell-empty { background: var(--p-surface-50); color: var(--p-text-muted-color); }
.cell-empty .empty-dot { font-size: 1.2rem; opacity: 0.5; }
.cell-inactive { background: var(--p-surface-200); color: var(--p-text-muted-color); }
.legend { display: flex; gap: 1rem; padding: 0.5rem; font-size: 0.8rem; color: var(--p-text-muted-color); }
.legend-item { display: flex; align-items: center; gap: 0.35rem; }
.legend .dot { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid var(--p-surface-300); }
.dialog-content { display: flex; flex-direction: column; gap: 0.75rem; }
.dialog-content .muted { margin: 0; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
</style>
