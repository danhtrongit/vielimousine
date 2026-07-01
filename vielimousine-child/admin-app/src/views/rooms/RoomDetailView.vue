<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import ProgressSpinner from 'primevue/progressspinner';
import { roomsApi } from '@/api/rooms.api';
import { mediaApi } from '@/api/media.api';
import { surchargesApi, type Surcharge } from '@/api/surcharges.api';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useAuthStore } from '@/stores/auth.store';
import { useNotify } from '@/composables/useNotify';
import { decodeEntities } from '@/composables/useFormat';
import type { Room } from '@/types/hotel';
import type { MediaItem } from '@/types/media';
import MediaPickerDialog from '@/components/media/MediaPickerDialog.vue';
import MediaThumb from '@/components/media/MediaThumb.vue';
import PageHeader from '@/components/PageHeader.vue';

const route = useRoute();
const ui = useUIStore();
const lookup = useLookupStore();
const auth = useAuthStore();
const notify = useNotify();

// Chỉ người có quyền giá (admin) mới thấy & sửa được ô giá phòng.
const canEditPricing = computed(() => auth.can('vie_manage_pricing'));

const room = ref<Room | null>(null);
const loading = ref(true);
const saving = ref(false);

const id = computed(() => Number(route.params.id));

// Surcharge rules (per-room "phụ thu trẻ em")
const surcharges = ref<Surcharge[]>([]);
const savingRule = ref<number | null>(null);

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

const childRules = computed(() =>
  surcharges.value
    .filter((s) => s.guest_type === 'child')
    .sort((a, b) => (a.child_index_min - b.child_index_min) || (a.age_from - b.age_from)),
);

function indexOfRule(rule: Surcharge): number {
  return surcharges.value.findIndex((s) => s === rule);
}

function addRule() {
  const draft = blankRule(id.value);
  const maxIdx = childRules.value.reduce((acc, r) => Math.max(acc, r.child_index_min), 0);
  draft.child_index_min = maxIdx + 1;
  draft.child_index_max = maxIdx + 1;
  surcharges.value.push(draft);
}

async function saveRule(rule: Surcharge, index: number) {
  savingRule.value = index;
  try {
    const payload: Partial<Surcharge> = {
      room_id: id.value,
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
    const resp = rule.id === 0
      ? await surchargesApi.create(payload)
      : await surchargesApi.update(rule.id, payload);
    surcharges.value[index] = { ...resp.data };
    await lookup.refresh();
    notify.success('Đã lưu phụ thu');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingRule.value = null;
  }
}

async function deleteRule(rule: Surcharge, index: number) {
  if (rule.id === 0) { surcharges.value.splice(index, 1); return; }
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

// Media picker
const pickerVisible = ref(false);
const pickerMode = ref<'single' | 'multi'>('single');
const mediaMap = ref(new Map<number, MediaItem>());

const thumbItem = computed(() =>
  room.value?.thumbnail_id ? mediaMap.value.get(room.value.thumbnail_id) ?? null : null
);
const pickerInitial = computed<number[]>(() => {
  if (!room.value) return [];
  return pickerMode.value === 'single'
    ? (room.value.thumbnail_id ? [room.value.thumbnail_id] : [])
    : (room.value.gallery ?? []);
});

async function prefetchMedia(ids: number[]) {
  const missing = ids.filter((i) => i && !mediaMap.value.has(i));
  if (missing.length === 0) return;
  await Promise.all(
    missing.map(async (i) => {
      try {
        const r = await mediaApi.get(i);
        mediaMap.value.set(i, r.data);
      } catch { /* ignore */ }
    })
  );
}

function openPicker(mode: 'single' | 'multi') {
  pickerMode.value = mode;
  pickerVisible.value = true;
}

async function onPickerSelect(ids: number[]) {
  if (!room.value) return;
  if (pickerMode.value === 'single') room.value.thumbnail_id = ids[0] ?? null;
  else room.value.gallery = ids;
  await prefetchMedia(ids);
}

function removeGallery(gid: number) {
  if (!room.value) return;
  room.value.gallery = (room.value.gallery ?? []).filter((x) => x !== gid);
}

async function saveMedia() {
  if (!room.value) return;
  saving.value = true;
  try {
    await roomsApi.update(id.value, {
      thumbnail_id: room.value.thumbnail_id,
      gallery: room.value.gallery,
    });
    notify.success('Đã lưu hình ảnh');
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

async function load() {
  loading.value = true;
  try {
    await lookup.ensureLoaded();
    const [roomResp, surResp] = await Promise.all([
      roomsApi.get(id.value),
      surchargesApi.list({ room_id: id.value, per_page: 200 }),
    ]);
    room.value = { ...roomResp.data, name: decodeEntities(roomResp.data.name) };
    surcharges.value = surResp.data.map((s) => ({ ...s }));
    ui.setBreadcrumb([
      { label: 'Phòng', to: '/rooms' },
      { label: room.value.name },
    ]);
    const mediaIds = [
      ...(room.value.thumbnail_id ? [room.value.thumbnail_id] : []),
      ...(room.value.gallery ?? []),
    ];
    if (mediaIds.length > 0) await prefetchMedia(mediaIds);
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function save() {
  if (!room.value) return;
  saving.value = true;
  try {
    await roomsApi.update(id.value, {
      name: room.value.name,
      included_adults: room.value.included_adults,
      max_adults: room.value.max_adults,
      max_children: room.value.max_children,
      free_children_count: room.value.free_children_count,
      free_children_max_age: room.value.free_children_max_age,
      is_active: room.value.is_active,
      // Ô giá chỉ gửi khi có quyền (backend cũng chặn ghi giá nếu thiếu quyền).
      ...(canEditPricing.value
        ? { base_price: room.value.base_price, extra_adult_price: room.value.extra_adult_price }
        : {}),
    });
    notify.success('Đã lưu');
    await lookup.refresh();
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else-if="room">
    <PageHeader
      :title="room.name"
      :subtitle="`Khách sạn: ${lookup.hotelById(room.hotel_id)?.name ?? '—'}`"
      icon="pi pi-th-large"
    />

    <Card>
      <template #content>
        <div class="grid-2">
          <div class="field">
            <label>Tên phòng</label>
            <InputText v-model="room.name" />
          </div>
          <div class="field">
            <label>Người lớn gồm trong giá</label>
            <InputNumber v-model="room.included_adults" :min="1" show-buttons />
          </div>
          <div class="field">
            <label>Người lớn tối đa</label>
            <InputNumber v-model="room.max_adults" :min="1" show-buttons />
          </div>
          <div class="field">
            <label>Trẻ em tối đa</label>
            <InputNumber v-model="room.max_children" :min="0" show-buttons />
          </div>
          <template v-if="canEditPricing">
            <div class="field">
              <label>Giá phòng / đêm (VND)</label>
              <InputNumber v-model="room.base_price" :min="0" />
            </div>
            <div class="field">
              <label>Phụ thu người lớn / đêm (VND)</label>
              <InputNumber v-model="room.extra_adult_price" :min="0" />
            </div>
          </template>
          <div class="field">
            <label>Số bé miễn phí / phòng</label>
            <InputNumber v-model="room.free_children_count" :min="0" show-buttons />
          </div>
          <div class="field">
            <label>Tuổi tối đa bé miễn phí</label>
            <InputNumber v-model="room.free_children_max_age" :min="0" :max="17" show-buttons />
          </div>
          <div class="field">
            <label>Trạng thái</label>
            <ToggleSwitch v-model="room.is_active" />
          </div>
        </div>
      </template>
    </Card>
    <div class="actions">
      <Button label="Lưu" icon="pi pi-save" :loading="saving" @click="save" />
    </div>

    <Card class="surcharge-card">
      <template #title>Phụ thu trẻ em</template>
      <template #content>
        <p class="muted-inline">
          Mỗi dòng định nghĩa phụ thu cho 1 nhóm trẻ theo độ tuổi và thứ tự xuất hiện
          (Trẻ #1, #2…). Để trống "đến #" nghĩa là áp dụng từ # đó trở lên.
        </p>
        <table v-if="childRules.length > 0" class="surcharge-table">
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
            <tr v-for="rule in childRules" :key="rule.id || `new-${indexOfRule(rule)}`">
              <td><InputText v-model="rule.label" /></td>
              <td><InputNumber v-model="rule.child_index_min" :min="1" :max="20" /></td>
              <td><InputNumber v-model="rule.child_index_max" :min="1" :max="20" placeholder="trở lên" /></td>
              <td><InputNumber v-model="rule.age_from" :min="0" :max="17" /></td>
              <td><InputNumber v-model="rule.age_to" :min="0" :max="17" /></td>
              <td><InputNumber v-model="rule.amount" :min="0" /></td>
              <td><ToggleSwitch v-model="rule.is_free" /></td>
              <td><ToggleSwitch v-model="rule.is_active" /></td>
              <td class="row-actions">
                <Button icon="pi pi-save" severity="success" text rounded
                  :loading="savingRule === indexOfRule(rule)"
                  @click="saveRule(rule, indexOfRule(rule))" />
                <Button icon="pi pi-trash" severity="danger" text rounded
                  @click="deleteRule(rule, indexOfRule(rule))" />
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted-inline">Chưa có rule phụ thu.</p>
        <Button label="Thêm hàng" icon="pi pi-plus" outlined size="small" @click="addRule" />
      </template>
    </Card>

    <Card class="media-card">
      <template #title>Hình ảnh</template>
      <template #content>
        <div class="field">
          <label>Ảnh đại diện</label>
          <div class="thumb-row">
            <MediaThumb
              v-if="thumbItem"
              :item="thumbItem"
              size="md"
              removable
              @remove="room!.thumbnail_id = null"
            />
            <Button label="Chọn ảnh" icon="pi pi-image" outlined @click="openPicker('single')" />
          </div>
        </div>

        <div class="field" style="margin-top: 1.25rem">
          <label>Gallery ({{ (room.gallery ?? []).length }} ảnh)</label>
          <div v-if="(room.gallery ?? []).length > 0" class="gallery-grid">
            <MediaThumb
              v-for="gid in (room.gallery ?? [])"
              :key="gid"
              :item="mediaMap.get(gid)"
              size="sm"
              removable
              @remove="removeGallery(gid)"
            />
          </div>
          <p v-else class="muted-inline">Chưa có ảnh gallery.</p>
          <Button label="Thêm vào gallery" icon="pi pi-plus" outlined class="add-gallery-btn" @click="openPicker('multi')" />
        </div>
      </template>
    </Card>
    <div class="actions">
      <Button label="Lưu hình ảnh" icon="pi pi-save" :loading="saving" @click="saveMedia" />
    </div>

    <MediaPickerDialog
      v-model:visible="pickerVisible"
      :multiple="pickerMode === 'multi'"
      :initial="pickerInitial"
      @select="onPickerSelect"
    />
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.actions { display: flex; justify-content: flex-end; margin-top: 1rem; }
.surcharge-card { margin-top: 1.5rem; }
.surcharge-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem; margin-bottom: 0.75rem; }
.surcharge-table th, .surcharge-table td { padding: 0.35rem; border-bottom: 1px solid var(--p-surface-200); text-align: left; }
.surcharge-table th { color: var(--p-text-muted-color); font-weight: 500; }
.surcharge-table td :deep(.p-inputnumber), .surcharge-table td :deep(.p-inputtext) { width: 100%; min-width: 80px; }
.row-actions { display: flex; gap: 0.25rem; white-space: nowrap; }
.media-card { margin-top: 1.5rem; }
.thumb-row { display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.gallery-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.add-gallery-btn { margin-top: 0.5rem; }
.muted-inline { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0 0 0.5rem; }
</style>
