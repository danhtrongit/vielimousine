<script setup lang="ts">
import { onMounted } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { formatVND } from '@/composables/useFormat';

const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Tên phòng', type: 'string' },
  { key: 'hotel_id', label: 'Khách sạn', type: 'enum', options: [] },
  { key: 'is_active', label: 'Trạng thái', type: 'enum', options: [
    { label: 'Hoạt động', value: '1' },
    { label: 'Tạm ngưng', value: '0' },
  ]},
];

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Phòng' }]);
  await lookup.ensureLoaded();
  filterSchema[1].options = lookup.hotels.map((h) => ({ label: h.name, value: String(h.id) }));
});

function hotelName(hotelId: number): string {
  return lookup.hotelById(hotelId)?.name ?? '—';
}
</script>

<template>
  <div>
    <h1 class="page-title">Phòng</h1>
    <DataTablePanel
      endpoint="/rooms"
      :defaults="{ sort: 'sort_order', order: 'asc' }"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="name" header="Tên phòng" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/rooms/${data.id}`" class="link">{{ data.name }}</RouterLink>
        </template>
      </Column>
      <Column header="Khách sạn">
        <template #body="{ data }">{{ hotelName(data.hotel_id) }}</template>
      </Column>
      <Column field="included_adults" header="Người lớn gồm" />
      <Column field="max_adults" header="Người lớn tối đa" />
      <Column field="max_children" header="Trẻ em tối đa" />
      <Column field="base_price" header="Giá phòng">
        <template #body="{ data }">{{ formatVND(data.base_price) }}</template>
      </Column>
      <Column field="extra_adult_price" header="Phụ thu người lớn">
        <template #body="{ data }">{{ formatVND(data.extra_adult_price) }}</template>
      </Column>
      <Column field="is_active" header="Trạng thái">
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
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 600; }
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
</style>
