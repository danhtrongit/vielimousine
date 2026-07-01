<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import { hotelsApi, roomsApi } from '@/api/hotels.api';
import { mediaApi } from '@/api/media.api';
import { useUIStore } from '@/stores/ui.store';
import { useAuthStore } from '@/stores/auth.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND, decodeEntities } from '@/composables/useFormat';
import type { Hotel, Room } from '@/types/hotel';
import type { MediaItem } from '@/types/media';
import MediaPickerDialog from '@/components/media/MediaPickerDialog.vue';
import MediaThumb from '@/components/media/MediaThumb.vue';
import PageHeader from '@/components/PageHeader.vue';

const route = useRoute();
const ui = useUIStore();
const auth = useAuthStore();
const notify = useNotify();

// Chỉ người có quyền giá (admin) mới thấy & sửa giá vé / chính sách vé.
const canEditPricing = computed(() => auth.can('vie_manage_pricing'));

const hotel = ref<Hotel | null>(null);
const rooms = ref<Room[]>([]);
const loading = ref(true);
const saving = ref(false);

const id = computed(() => Number(route.params.id));

// Policy texts (free-form)
const pricingPolicyText = ref('');
const cancellationPolicyText = ref('');

// Media picker state
const pickerVisible = ref(false);
const pickerMode = ref<'single' | 'multi'>('single');
const mediaMap = ref(new Map<number, MediaItem>());

const thumbItem = computed(() =>
  hotel.value?.thumbnail_id ? mediaMap.value.get(hotel.value.thumbnail_id) ?? null : null
);

const pickerInitial = computed<number[]>(() => {
  if (!hotel.value) return [];
  return pickerMode.value === 'single'
    ? (hotel.value.thumbnail_id ? [hotel.value.thumbnail_id] : [])
    : (hotel.value.gallery ?? []);
});

async function prefetchMedia(ids: number[]) {
  const missing = ids.filter((i) => i && !mediaMap.value.has(i));
  if (missing.length === 0) return;
  await Promise.all(
    missing.map(async (i) => {
      try {
        const r = await mediaApi.get(i);
        mediaMap.value.set(i, r.data);
      } catch { /* ignore — missing/deleted media */ }
    })
  );
}

function openPicker(mode: 'single' | 'multi') {
  pickerMode.value = mode;
  pickerVisible.value = true;
}

async function onPickerSelect(ids: number[]) {
  if (!hotel.value) return;
  if (pickerMode.value === 'single') {
    hotel.value.thumbnail_id = ids[0] ?? null;
  } else {
    hotel.value.gallery = ids;
  }
  await prefetchMedia(ids);
}

function removeGallery(id: number) {
  if (!hotel.value) return;
  hotel.value.gallery = (hotel.value.gallery ?? []).filter((x) => x !== id);
}

async function saveMedia() {
  if (!hotel.value) return;
  saving.value = true;
  try {
    await hotelsApi.update(id.value, {
      thumbnail_id: hotel.value.thumbnail_id,
      gallery: hotel.value.gallery,
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
    const [hResp, rResp] = await Promise.all([
      hotelsApi.get(id.value),
      roomsApi.list({ hotel_id: id.value, per_page: 100 }),
    ]);
    hotel.value = { ...hResp.data, name: decodeEntities(hResp.data.name) };
    rooms.value = rResp.data.map((r) => ({ ...r, name: decodeEntities(r.name) }));
    pricingPolicyText.value = hotel.value.pricing_policy?.text ?? '';
    cancellationPolicyText.value = hotel.value.cancellation_policy?.text ?? '';
    ui.setBreadcrumb([
      { label: 'Khách sạn', to: '/hotels' },
      { label: hotel.value.name },
    ]);
    // Prefetch media items for thumbnail + gallery
    const mediaIds = [
      ...(hotel.value.thumbnail_id ? [hotel.value.thumbnail_id] : []),
      ...(hotel.value.gallery ?? []),
    ];
    if (mediaIds.length > 0) await prefetchMedia(mediaIds);
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function saveInfo() {
  if (!hotel.value) return;
  saving.value = true;
  try {
    await hotelsApi.update(id.value, {
      name: hotel.value.name,
      address: hotel.value.address,
      city: hotel.value.city,
      contact_phone: hotel.value.contact_phone,
      contact_email: hotel.value.contact_email,
      star_rating: hotel.value.star_rating,
      // Giá vé / chính sách vé chỉ gửi khi có quyền (backend cũng chặn ghi nếu thiếu quyền).
      ...(canEditPricing.value
        ? {
            default_ticket_price: hotel.value.default_ticket_price,
            ticket_free_children_count: hotel.value.ticket_free_children_count,
            ticket_free_children_max_age: hotel.value.ticket_free_children_max_age,
          }
        : {}),
    });
    notify.success('Đã lưu thông tin');
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

async function savePolicy() {
  if (!hotel.value) return;
  saving.value = true;
  try {
    await hotelsApi.update(id.value, {
      pricing_policy: { text: pricingPolicyText.value },
      cancellation_policy: { text: cancellationPolicyText.value },
    });
    notify.success('Đã lưu chính sách');
    await load();
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else-if="hotel">
    <PageHeader :title="hotel.name" subtitle="Chi tiết khách sạn" icon="pi pi-building" />

    <Tabs value="info">
      <TabList>
        <Tab value="info">Thông tin</Tab>
        <Tab value="media">Hình ảnh</Tab>
        <Tab value="rooms">Phòng</Tab>
        <Tab value="policy">Chính sách</Tab>
      </TabList>
      <TabPanels>
      <TabPanel value="info">
        <Card>
          <template #content>
            <div class="grid-2">
              <div class="field">
                <label>Tên</label>
                <InputText v-model="hotel.name" />
              </div>
              <div class="field">
                <label>Thành phố</label>
                <InputText v-model="hotel.city" />
              </div>
              <div class="field grid-span-2">
                <label>Địa chỉ</label>
                <InputText v-model="hotel.address" />
              </div>
              <div class="field">
                <label>SĐT</label>
                <InputText v-model="hotel.contact_phone" />
              </div>
              <div class="field">
                <label>Email</label>
                <InputText v-model="hotel.contact_email" />
              </div>
              <div class="field">
                <label>Hạng sao (1–5)</label>
                <InputNumber v-model="hotel.star_rating" :min="1" :max="5" show-buttons />
              </div>
              <template v-if="canEditPricing">
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
              </template>
            </div>
          </template>
        </Card>
        <div class="actions-row">
          <Button label="Lưu thông tin" icon="pi pi-save" :loading="saving" @click="saveInfo" />
        </div>
      </TabPanel>

      <TabPanel value="media">
        <Card>
          <template #content>
            <div class="field">
              <label>Ảnh đại diện</label>
              <div class="thumb-row">
                <MediaThumb
                  v-if="thumbItem"
                  :item="thumbItem"
                  size="md"
                  removable
                  @remove="hotel!.thumbnail_id = null"
                />
                <Button label="Chọn ảnh" icon="pi pi-image" outlined @click="openPicker('single')" />
              </div>
            </div>

            <div class="field" style="margin-top: 1.25rem">
              <label>Gallery ({{ (hotel.gallery ?? []).length }} ảnh)</label>
              <div v-if="(hotel.gallery ?? []).length > 0" class="gallery-grid">
                <MediaThumb
                  v-for="gid in (hotel.gallery ?? [])"
                  :key="gid"
                  :item="mediaMap.get(gid)"
                  size="sm"
                  removable
                  @remove="removeGallery(gid)"
                />
              </div>
              <p v-else class="muted">Chưa có ảnh gallery.</p>
              <Button label="Thêm vào gallery" icon="pi pi-plus" outlined class="add-gallery-btn" @click="openPicker('multi')" />
            </div>
          </template>
        </Card>
        <div class="actions-row">
          <Button label="Lưu hình ảnh" icon="pi pi-save" :loading="saving" @click="saveMedia" />
        </div>

        <MediaPickerDialog
          v-model:visible="pickerVisible"
          :multiple="pickerMode === 'multi'"
          :initial="pickerInitial"
          @select="onPickerSelect"
        />
      </TabPanel>

      <TabPanel value="rooms">
        <DataTable :value="rooms" data-key="id" :empty-message="'Chưa có phòng'" class="rooms-table">
          <Column field="id" header="ID" style="width: 60px">
            <template #body="{ data }"><span class="room-id">#{{ data.id }}</span></template>
          </Column>
          <Column field="name" header="Tên phòng">
            <template #body="{ data }">
              <div class="room-name">{{ data.name }}</div>
            </template>
          </Column>
          <Column header="Sức chứa">
            <template #body="{ data }">
              <div class="room-cap">
                <span><i class="pi pi-users" /> {{ data.included_adults }}<span v-if="data.max_adults > data.included_adults">–{{ data.max_adults }}</span> người lớn</span>
                <span v-if="data.max_children > 0">
                  <i class="pi pi-user" />
                  tối đa {{ data.max_children }} trẻ em<span v-if="data.free_children_count > 0"> · miễn {{ data.free_children_count }}</span>
                </span>
              </div>
            </template>
          </Column>
          <Column field="base_price" header="Giá phòng">
            <template #body="{ data }">{{ formatVND(data.base_price) }}</template>
          </Column>
          <Column field="extra_adult_price" header="Phụ thu người lớn">
            <template #body="{ data }">{{ formatVND(data.extra_adult_price) }}</template>
          </Column>
          <Column field="is_active" header="Trạng thái" style="width: 96px">
            <template #body="{ data }">
              <span :class="['room-status', data.is_active ? 'room-status-on' : 'room-status-off']">
                <i :class="['pi', data.is_active ? 'pi-check-circle' : 'pi-ban']" />
                {{ data.is_active ? 'Hoạt động' : 'Tạm tắt' }}
              </span>
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel value="policy">
        <Card>
          <template #content>
            <div class="field">
              <label>Chính sách giá</label>
              <Textarea v-model="pricingPolicyText" rows="6" placeholder="VD: Giá đã bao gồm ăn sáng, thuế VAT..." />
            </div>
            <div class="field">
              <label>Chính sách hủy</label>
              <Textarea v-model="cancellationPolicyText" rows="6" placeholder="VD: Hủy trước ≥ 48 tiếng: phí 0%&#10;Hủy trong 48–24 tiếng: phí 50%" />
            </div>
          </template>
        </Card>
        <div class="actions-row">
          <Button label="Lưu chính sách" icon="pi pi-save" :loading="saving" @click="savePolicy" />
        </div>
      </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.75rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.actions-row { display: flex; justify-content: flex-end; margin-top: 1rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin-bottom: 0.75rem; }
.thumb-row { display: flex; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.gallery-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; }
.add-gallery-btn { margin-top: 0.5rem; }

/* Rooms table */
.room-id { font-variant-numeric: tabular-nums; color: var(--p-text-muted-color); font-size: 0.85rem; }
.room-thumb {
  width: 56px; height: 56px; border-radius: 6px; overflow: hidden;
  background: var(--p-surface-100); display: grid; place-items: center;
}
.room-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.room-thumb-empty { color: var(--p-text-muted-color); font-size: 1.4rem; }
.room-name { font-weight: 600; }
.room-cap { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.85rem; color: #4b5563; }
.room-cap > span { display: inline-flex; align-items: center; gap: 0.35rem; }
.room-cap .pi { color: var(--p-primary-color); font-size: 0.85rem; }
.room-status {
  display: inline-flex; align-items: center; gap: 0.3rem;
  padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.78rem; font-weight: 500;
}
.room-status-on { background: #dcfce7; color: #166534; }
.room-status-off { background: #f3f4f6; color: #6b7280; }
</style>
