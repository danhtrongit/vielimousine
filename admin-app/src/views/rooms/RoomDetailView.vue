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
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { decodeEntities } from '@/composables/useFormat';
import type { Room } from '@/types/hotel';
import type { MediaItem } from '@/types/media';
import MediaPickerDialog from '@/components/media/MediaPickerDialog.vue';
import MediaThumb from '@/components/media/MediaThumb.vue';

const route = useRoute();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();

const room = ref<Room | null>(null);
const loading = ref(true);
const saving = ref(false);

const id = computed(() => Number(route.params.id));

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
    const resp = await roomsApi.get(id.value);
    room.value = { ...resp.data, name: decodeEntities(resp.data.name) };
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
      base_price: room.value.base_price,
      extra_adult_price: room.value.extra_adult_price,
      free_children_count: room.value.free_children_count,
      free_children_max_age: room.value.free_children_max_age,
      is_active: room.value.is_active,
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
    <h1 class="page-title">{{ room.name }}</h1>
    <p class="muted">Khách sạn: {{ lookup.hotelById(room.hotel_id)?.name ?? '—' }}</p>

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
          <div class="field">
            <label>Giá phòng / đêm (VND)</label>
            <InputNumber v-model="room.base_price" :min="0" />
          </div>
          <div class="field">
            <label>Phụ thu người lớn / đêm (VND)</label>
            <InputNumber v-model="room.extra_adult_price" :min="0" />
          </div>
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
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.muted { color: var(--p-text-muted-color); margin: 0.25rem 0 1.25rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.actions { display: flex; justify-content: flex-end; margin-top: 1rem; }
.media-card { margin-top: 1.5rem; }
.thumb-row { display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.gallery-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.add-gallery-btn { margin-top: 0.5rem; }
.muted-inline { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0 0 0.5rem; }
</style>
