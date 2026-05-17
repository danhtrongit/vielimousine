<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import ProgressSpinner from 'primevue/progressspinner';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { hotelsApi } from '@/api/hotels.api';
import { roomsApi } from '@/api/rooms.api';
import { surchargesApi, type Surcharge } from '@/api/surcharges.api';
import type { Hotel, Room } from '@/types/hotel';

const lookup = useLookupStore();
const notify = useNotify();

const loading = ref(true);
const savingRoom = ref<number | null>(null);
const savingHotel = ref<number | null>(null);
const savingRule = ref<number | null>(null);
const expanded = ref<Set<number>>(new Set());

// Working copies (deep enough to edit safely)
const hotels = ref<Hotel[]>([]);
const rooms = ref<Room[]>([]);
const surcharges = ref<Surcharge[]>([]);

function blankRule(roomId: number): Surcharge {
  return {
    id: 0,
    room_id: roomId,
    guest_type: 'child',
    label: 'Trẻ em',
    age_from: 0,
    age_to: 12,
    child_index_min: 1,
    child_index_max: null,
    amount: 0,
    is_free: false,
    sort_order: 0,
    is_active: true,
  };
}

async function load() {
  loading.value = true;
  try {
    await lookup.ensureLoaded();
    hotels.value = lookup.hotels.map((h) => ({ ...h }));
    rooms.value = lookup.rooms.map((r) => ({ ...r }));
    // Fetch surcharges fresh (include inactive) for full editing
    const resp = await surchargesApi.list({ per_page: 500 });
    surcharges.value = resp.data.map((s) => ({ ...s }));
    // Auto-expand first hotel
    if (hotels.value.length > 0) {
      expanded.value = new Set([hotels.value[0].id]);
    }
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

function toggleHotel(id: number) {
  const next = new Set(expanded.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  expanded.value = next;
}

function roomsOf(hotelId: number): Room[] {
  return rooms.value.filter((r) => r.hotel_id === hotelId);
}

function rulesOfRoom(roomId: number): Surcharge[] {
  return surcharges.value
    .filter((s) => s.room_id === roomId && s.guest_type === 'child')
    .sort((a, b) => (a.child_index_min - b.child_index_min) || (a.age_from - b.age_from));
}

async function saveHotel(hotel: Hotel) {
  savingHotel.value = hotel.id;
  try {
    await hotelsApi.update(hotel.id, {
      default_ticket_price: hotel.default_ticket_price,
      ticket_free_children_count: hotel.ticket_free_children_count,
      ticket_free_children_max_age: hotel.ticket_free_children_max_age,
    });
    await lookup.refresh();
    notify.success(`Đã lưu KS: ${hotel.name}`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingHotel.value = null;
  }
}

async function saveRoom(room: Room) {
  savingRoom.value = room.id;
  try {
    await roomsApi.update(room.id, {
      base_price: room.base_price,
      extra_adult_price: room.extra_adult_price,
      free_children_count: room.free_children_count,
      free_children_max_age: room.free_children_max_age,
    });
    await lookup.refresh();
    notify.success(`Đã lưu phòng: ${room.name}`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingRoom.value = null;
  }
}

function addRule(roomId: number) {
  const draft = blankRule(roomId);
  // Suggest the next slot index based on existing rules
  const existing = rulesOfRoom(roomId);
  const maxIdx = existing.reduce((acc, r) => Math.max(acc, r.child_index_min), 0);
  draft.child_index_min = maxIdx + 1;
  draft.child_index_max = maxIdx + 1;
  surcharges.value.push(draft);
}

async function saveRule(rule: Surcharge, index: number) {
  // index = position in surcharges array (working copy)
  savingRule.value = index;
  try {
    const payload: Partial<Surcharge> = {
      room_id: rule.room_id,
      guest_type: 'child',
      label: rule.label || 'Trẻ em',
      age_from: rule.age_from,
      age_to: rule.age_to,
      child_index_min: rule.child_index_min,
      child_index_max: rule.child_index_max,
      amount: rule.amount,
      is_free: rule.is_free,
      sort_order: rule.sort_order,
      is_active: rule.is_active,
    };
    if (rule.id === 0) {
      const resp = await surchargesApi.create(payload);
      surcharges.value[index] = { ...resp.data };
    } else {
      const resp = await surchargesApi.update(rule.id, payload);
      surcharges.value[index] = { ...resp.data };
    }
    await lookup.refresh();
    notify.success('Đã lưu phụ thu');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingRule.value = null;
  }
}

async function deleteRule(rule: Surcharge, index: number) {
  if (rule.id === 0) {
    // Unsaved draft → just drop locally
    surcharges.value.splice(index, 1);
    return;
  }
  if (!confirm('Xóa rule phụ thu này?')) return;
  savingRule.value = index;
  try {
    await surchargesApi.delete(rule.id);
    surcharges.value.splice(index, 1);
    await lookup.refresh();
    notify.success('Đã xóa');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingRule.value = null;
  }
}

function indexOfRule(rule: Surcharge): number {
  return surcharges.value.findIndex((s) => s === rule);
}

const hotelCount = computed(() => hotels.value.length);
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else>
    <div class="toolbar">
      <p class="muted">{{ hotelCount }} khách sạn — bấm tên KS để mở/đóng. Lưu từng phòng + từng dòng phụ thu độc lập.</p>
      <Button label="Tải lại" icon="pi pi-refresh" outlined size="small" @click="load" />
    </div>

    <Card v-for="hotel in hotels" :key="hotel.id" class="hotel-card">
      <template #content>
        <div class="hotel-header" @click="toggleHotel(hotel.id)">
          <i :class="['pi', expanded.has(hotel.id) ? 'pi-chevron-down' : 'pi-chevron-right']" />
          <h3>{{ hotel.name }}</h3>
          <span class="muted-inline">{{ roomsOf(hotel.id).length }} phòng</span>
        </div>

        <div v-if="expanded.has(hotel.id)" class="hotel-body" @click.stop>
          <div class="hotel-fields">
            <div class="field">
              <label>Giá vé mặc định (VND)</label>
              <InputNumber v-model="hotel.default_ticket_price" :min="0" />
            </div>
            <div class="field">
              <label>Số bé miễn vé / booking</label>
              <InputNumber v-model="hotel.ticket_free_children_count" :min="0" show-buttons />
            </div>
            <div class="field">
              <label>Tuổi tối đa bé miễn vé</label>
              <InputNumber v-model="hotel.ticket_free_children_max_age" :min="0" :max="17" show-buttons />
            </div>
            <div class="field">
              <Button label="Lưu KS" icon="pi pi-save" size="small" :loading="savingHotel === hotel.id" @click="saveHotel(hotel)" />
            </div>
          </div>

          <div v-for="room in roomsOf(hotel.id)" :key="room.id" class="room-block">
            <div class="room-header">
              <strong>{{ room.name }}</strong>
            </div>
            <div class="room-fields">
              <div class="field">
                <label>Giá phòng / đêm</label>
                <InputNumber v-model="room.base_price" :min="0" />
              </div>
              <div class="field">
                <label>Phụ thu người lớn</label>
                <InputNumber v-model="room.extra_adult_price" :min="0" />
              </div>
              <div class="field">
                <label>Bé miễn / phòng</label>
                <InputNumber v-model="room.free_children_count" :min="0" show-buttons />
              </div>
              <div class="field">
                <label>Tuổi bé miễn tối đa</label>
                <InputNumber v-model="room.free_children_max_age" :min="0" :max="17" show-buttons />
              </div>
              <div class="field">
                <Button label="Lưu phòng" icon="pi pi-save" size="small" :loading="savingRoom === room.id" @click="saveRoom(room)" />
              </div>
            </div>

            <div class="surcharge-block">
              <div class="surcharge-title">Phụ thu trẻ em</div>
              <table v-if="rulesOfRoom(room.id).length > 0" class="surcharge-table">
                <thead>
                  <tr>
                    <th>Nhãn</th>
                    <th>Trẻ từ #</th>
                    <th>Đến # (trống = trở lên)</th>
                    <th>Tuổi từ</th>
                    <th>Tuổi đến</th>
                    <th>Phụ thu (VND)</th>
                    <th>Miễn phí</th>
                    <th>Bật</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rule in rulesOfRoom(room.id)" :key="rule.id || `new-${indexOfRule(rule)}`">
                    <td><InputText v-model="rule.label" /></td>
                    <td><InputNumber v-model="rule.child_index_min" :min="1" :max="20" /></td>
                    <td><InputNumber v-model="rule.child_index_max" :min="1" :max="20" placeholder="trở lên" /></td>
                    <td><InputNumber v-model="rule.age_from" :min="0" :max="17" /></td>
                    <td><InputNumber v-model="rule.age_to" :min="0" :max="17" /></td>
                    <td><InputNumber v-model="rule.amount" :min="0" /></td>
                    <td><ToggleSwitch v-model="rule.is_free" /></td>
                    <td><ToggleSwitch v-model="rule.is_active" /></td>
                    <td class="row-actions">
                      <Button icon="pi pi-save" severity="success" text rounded :loading="savingRule === indexOfRule(rule)" @click="saveRule(rule, indexOfRule(rule))" />
                      <Button icon="pi pi-trash" severity="danger" text rounded @click="deleteRule(rule, indexOfRule(rule))" />
                    </td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="muted-inline">Chưa có rule phụ thu.</p>
              <Button label="Thêm hàng" icon="pi pi-plus" outlined size="small" @click="addRule(room.id)" />
            </div>
          </div>

          <p v-if="roomsOf(hotel.id).length === 0" class="muted-inline">Khách sạn này chưa có phòng.</p>
        </div>
      </template>
    </Card>

    <p v-if="hotels.length === 0" class="muted">Chưa có khách sạn nào.</p>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 200px; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; gap: 1rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0; }
.muted-inline { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0.5rem 0; }
.hotel-card { margin-bottom: 1rem; }
.hotel-header { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; }
.hotel-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.hotel-header .muted-inline { margin: 0 0 0 auto; }
.hotel-body { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--p-surface-200); }
.hotel-fields { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1rem; }
.room-block { padding: 1rem; margin-top: 1rem; background: var(--p-surface-50); border-radius: 0.5rem; }
.room-header { margin-bottom: 0.5rem; }
.room-fields { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 140px; }
.field label { font-size: 0.8rem; color: var(--p-text-muted-color); }
.surcharge-block { padding: 0.75rem; background: var(--p-surface-0); border: 1px dashed var(--p-surface-300); border-radius: 0.35rem; }
.surcharge-title { font-weight: 500; margin-bottom: 0.5rem; }
.surcharge-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 0.5rem; }
.surcharge-table th, .surcharge-table td { padding: 0.35rem; border-bottom: 1px solid var(--p-surface-200); text-align: left; }
.surcharge-table th { color: var(--p-text-muted-color); font-weight: 500; }
.surcharge-table td :deep(.p-inputnumber), .surcharge-table td :deep(.p-inputtext) { width: 100%; min-width: 80px; }
.row-actions { display: flex; gap: 0.25rem; white-space: nowrap; }
</style>
