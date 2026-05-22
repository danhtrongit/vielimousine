<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND } from '@/composables/useFormat';
import { roomsApi } from '@/api/rooms.api';

const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();

const createDialog = ref(false);
const creating = ref(false);
const refreshKey = ref(0);

const blankForm = () => ({
  hotel_id: lookup.hotels[0]?.id ?? null as number | null,
  name: '',
  included_adults: 2,
  max_adults: 2,
  max_children: 2,
  base_price: 0,
  extra_adult_price: 0,
  free_children_count: 1,
  free_children_max_age: 5,
  is_active: true,
});
const form = ref(blankForm());

function openCreate() {
  form.value = blankForm();
  createDialog.value = true;
}

async function submitCreate() {
  if (!form.value.hotel_id) { notify.warn('Vui lòng chọn khách sạn'); return; }
  if (!form.value.name.trim()) { notify.warn('Vui lòng nhập tên phòng'); return; }
  creating.value = true;
  try {
    const resp = await roomsApi.create({
      hotel_id: form.value.hotel_id,
      name: form.value.name.trim(),
      included_adults: form.value.included_adults,
      max_adults: form.value.max_adults,
      max_children: form.value.max_children,
      base_price: form.value.base_price,
      extra_adult_price: form.value.extra_adult_price,
      free_children_count: form.value.free_children_count,
      free_children_max_age: form.value.free_children_max_age,
      is_active: form.value.is_active,
    });
    notify.success('Đã tạo phòng');
    createDialog.value = false;
    await lookup.refresh();
    refreshKey.value++;
    router.push(`/rooms/${resp.data.id}`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    creating.value = false;
  }
}

const filterSchema = computed<FilterDef[]>(() => [
  { key: 'q', label: 'Tên phòng', type: 'string' },
  {
    key: 'hotel_id',
    label: 'Khách sạn',
    type: 'enum',
    options: lookup.hotels.map((h) => ({ label: h.name, value: String(h.id) })),
  },
  { key: 'is_active', label: 'Trạng thái', type: 'enum', options: [
    { label: 'Hoạt động', value: '1' },
    { label: 'Tạm ngưng', value: '0' },
  ]},
]);

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Phòng' }]);
  await lookup.ensureLoaded();
});

function hotelName(hotelId: number): string {
  return lookup.hotelById(hotelId)?.name ?? '—';
}
</script>

<template>
  <div>
    <PageHeader title="Phòng" subtitle="Quản lý phòng và sức chứa" icon="pi pi-th-large">
      <Button label="Tạo phòng" icon="pi pi-plus" @click="openCreate" />
    </PageHeader>
    <DataTablePanel
      :key="refreshKey"
      endpoint="/rooms"
      :defaults="{ sort: 'sort_order', order: 'asc' }"
      storage-key="rooms.list.v1"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="id" header="ID" sortable style="width: 70px">
        <template #body="{ data }"><span class="room-id">#{{ data.id }}</span></template>
      </Column>
      <Column field="name" header="Tên phòng" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/rooms/${data.id}`" class="link">{{ data.name }}</RouterLink>
        </template>
      </Column>
      <Column header="Khách sạn">
        <template #body="{ data }">{{ hotelName(data.hotel_id) }}</template>
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
      <Column field="base_price" header="Giá phòng" sortable>
        <template #body="{ data }">{{ formatVND(data.base_price) }}</template>
      </Column>
      <Column field="extra_adult_price" header="Phụ thu người lớn">
        <template #body="{ data }">{{ formatVND(data.extra_adult_price) }}</template>
      </Column>
      <Column field="is_active" header="Trạng thái" style="width: 110px">
        <template #body="{ data }">
          <Tag v-if="data.is_active" severity="success" value="Hoạt động" />
          <Tag v-else severity="secondary" value="Tạm ngưng" />
        </template>
      </Column>
      <Column header="" style="width: 60px">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded @click="router.push(`/rooms/${data.id}`)" />
        </template>
      </Column>
    </DataTablePanel>

    <Dialog v-model:visible="createDialog" header="Tạo phòng mới" :modal="true" :style="{ width: '560px' }">
      <div class="form-grid">
        <div class="field span-2">
          <label>Khách sạn <span class="req">*</span></label>
          <Select
            v-model="form.hotel_id"
            :options="lookup.hotels"
            option-label="name"
            option-value="id"
            placeholder="Chọn khách sạn"
            filter
          />
        </div>
        <div class="field span-2">
          <label>Tên phòng <span class="req">*</span></label>
          <InputText v-model="form.name" placeholder="VD: Deluxe Sea View" />
        </div>
        <div class="field">
          <label>Người lớn gồm</label>
          <InputNumber v-model="form.included_adults" :min="1" show-buttons />
        </div>
        <div class="field">
          <label>Người lớn tối đa</label>
          <InputNumber v-model="form.max_adults" :min="1" show-buttons />
        </div>
        <div class="field">
          <label>Trẻ em tối đa</label>
          <InputNumber v-model="form.max_children" :min="0" show-buttons />
        </div>
        <div class="field">
          <label>Bé miễn / phòng</label>
          <InputNumber v-model="form.free_children_count" :min="0" show-buttons />
        </div>
        <div class="field">
          <label>Giá phòng (VND)</label>
          <InputNumber v-model="form.base_price" :min="0" fluid />
        </div>
        <div class="field">
          <label>Phụ thu người lớn (VND)</label>
          <InputNumber v-model="form.extra_adult_price" :min="0" fluid />
        </div>
        <div class="field">
          <label>Tuổi tối đa bé miễn</label>
          <InputNumber v-model="form.free_children_max_age" :min="0" :max="17" show-buttons />
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" severity="secondary" text @click="createDialog = false" />
        <Button label="Tạo phòng" icon="pi pi-check" :loading="creating" @click="submitCreate" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field.span-2 { grid-column: span 2; }
.field label { font-size: 0.82rem; font-weight: 500; }
.req { color: var(--p-red-500); }

.room-id { font-variant-numeric: tabular-nums; color: var(--p-text-muted-color); font-size: 0.85rem; }
.room-thumb {
  width: 56px; height: 56px; border-radius: 6px; overflow: hidden;
  background: var(--p-surface-100); display: grid; place-items: center;
}
.room-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.room-thumb-empty { color: var(--p-text-muted-color); font-size: 1.4rem; }
.room-cap { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.85rem; color: #4b5563; }
.room-cap > span { display: inline-flex; align-items: center; gap: 0.35rem; }
.room-cap .pi { color: var(--p-primary-color); font-size: 0.85rem; }
</style>
