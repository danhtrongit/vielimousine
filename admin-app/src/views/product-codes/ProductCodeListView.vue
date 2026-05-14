<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import ToggleSwitch from 'primevue/toggleswitch';
import { useConfirm } from 'primevue/useconfirm';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore, labelBookingType } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { productCodesApi, type ProductCode } from '@/api/productCodes.api';

const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();
const confirmService = useConfirm();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Code / tên', type: 'string' },
  { key: 'booking_type', label: 'Loại', type: 'enum', options: [
    { label: 'Phòng', value: 'room' },
    { label: 'Combo', value: 'combo' },
  ]},
];

const dialog = ref(false);
const form = ref<Partial<ProductCode>>({
  code: '',
  hotel_id: 0,
  room_id: 0,
  booking_type: 'room',
  weekday_pattern: '',
  display_name: '',
  unit_label: 'Phòng',
  is_active: true,
});
const editing = ref<number | null>(null);
const saving = ref(false);
const refreshKey = ref(0);

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Mã sản phẩm' }]);
  await lookup.ensureLoaded();
});

function openCreate() {
  editing.value = null;
  form.value = {
    code: '',
    hotel_id: lookup.hotels[0]?.id ?? 0,
    room_id: 0,
    booking_type: 'room',
    weekday_pattern: '',
    display_name: '',
    unit_label: 'Phòng',
    is_active: true,
  };
  dialog.value = true;
}

function openEdit(row: ProductCode) {
  editing.value = row.id;
  form.value = { ...row };
  dialog.value = true;
}

async function save() {
  if (!form.value.code || !form.value.hotel_id || !form.value.room_id) {
    notify.warn('Vui lòng nhập đủ Code, Khách sạn, Phòng');
    return;
  }
  saving.value = true;
  try {
    if (editing.value !== null) {
      await productCodesApi.update(editing.value, form.value);
      notify.success('Đã cập nhật');
    } else {
      await productCodesApi.create(form.value);
      notify.success('Đã tạo');
    }
    dialog.value = false;
    refreshKey.value++;
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

function askDelete(row: ProductCode) {
  confirmService.require({
    message: `Xóa mã ${row.code}?`,
    accept: async () => {
      try {
        await productCodesApi.destroy(row.id);
        notify.success('Đã xóa');
        refreshKey.value++;
      } catch (e) {
        notify.apiError(e);
      }
    },
  });
}
</script>

<template>
  <div>
    <div class="header">
      <h1 class="page-title">Mã sản phẩm</h1>
      <Button label="Tạo mã mới" icon="pi pi-plus" @click="openCreate" />
    </div>

    <DataTablePanel
      :key="refreshKey"
      endpoint="/product-codes"
      :defaults="{ sort: 'code', order: 'asc' }"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="code" header="Code" sortable />
      <Column header="Khách sạn">
        <template #body="{ data }">{{ lookup.hotelById(data.hotel_id)?.name ?? '—' }}</template>
      </Column>
      <Column header="Phòng">
        <template #body="{ data }">{{ lookup.roomById(data.room_id)?.name ?? '—' }}</template>
      </Column>
      <Column field="booking_type" header="Loại">
        <template #body="{ data }">{{ labelBookingType(data.booking_type) }}</template>
      </Column>
      <Column field="weekday_pattern" header="Lịch áp dụng">
        <template #body="{ data }">{{ data.weekday_pattern || '—' }}</template>
      </Column>
      <Column field="display_name" header="Tên hiển thị" />
      <Column field="unit_label" header="Đơn vị" />
      <Column field="is_active" header="Trạng thái">
        <template #body="{ data }">
          <Tag v-if="data.is_active" severity="success" value="Hoạt động" />
          <Tag v-else severity="secondary" value="Ngưng" />
        </template>
      </Column>
      <Column header="" style="width: 100px">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded @click="openEdit(data)" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="askDelete(data)" />
        </template>
      </Column>
    </DataTablePanel>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="dialog" :header="editing ? 'Sửa mã' : 'Tạo mã mới'" :modal="true" :style="{ width: '520px' }">
      <div class="dialog-content">
        <div class="field">
          <label>Code <span style="color: red">*</span></label>
          <InputText v-model="form.code" :disabled="editing !== null" />
        </div>
        <div class="grid-2">
          <div class="field">
            <label>Khách sạn</label>
            <Dropdown
              v-model="form.hotel_id"
              :options="lookup.hotels"
              option-label="name"
              option-value="id"
            />
          </div>
          <div class="field">
            <label>Phòng</label>
            <Dropdown
              v-model="form.room_id"
              :options="form.hotel_id ? lookup.roomsByHotel(form.hotel_id) : []"
              option-label="name"
              option-value="id"
            />
          </div>
          <div class="field">
            <label>Loại</label>
            <Dropdown
              v-model="form.booking_type"
              :options="[{label:'Phòng', value:'room'},{label:'Combo', value:'combo'}]"
              option-label="label"
              option-value="value"
            />
          </div>
          <div class="field">
            <label>Weekday pattern</label>
            <InputText v-model="form.weekday_pattern" placeholder="CN-T5, T6-T7, ..." />
          </div>
        </div>
        <div class="field">
          <label>Tên hiển thị</label>
          <InputText v-model="form.display_name" />
        </div>
        <div class="field">
          <label>Đơn vị</label>
          <InputText v-model="form.unit_label" placeholder="Phòng, Số Combo, ..." />
        </div>
        <div class="field">
          <label>Trạng thái</label>
          <ToggleSwitch v-model="form.is_active" />
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" severity="secondary" text @click="dialog = false" />
        <Button label="Lưu" :loading="saving" @click="save" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.dialog-content { display: flex; flex-direction: column; gap: 0.75rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
</style>
