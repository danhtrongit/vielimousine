<script setup lang="ts">
import { onMounted } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import MediaThumbLazy from '@/components/media/MediaThumbLazy.vue';
import { useUIStore } from '@/stores/ui.store';

const router = useRouter();
const ui = useUIStore();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Tên / Thành phố', type: 'string' },
  { key: 'is_active', label: 'Trạng thái', type: 'enum', options: [
    { label: 'Đang hoạt động', value: '1' },
    { label: 'Tạm ngưng', value: '0' },
  ]},
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Khách sạn' }]);
});
</script>

<template>
  <div>
    <h1 class="page-title">Khách sạn</h1>
    <DataTablePanel
      endpoint="/hotels"
      :defaults="{ sort: 'sort_order', order: 'asc' }"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="id" header="ID" sortable style="width: 70px" />
      <Column header="Ảnh" style="width: 80px">
        <template #body="{ data }">
          <MediaThumbLazy :id="data.thumbnail_id" size="sm" />
        </template>
      </Column>
      <Column field="name" header="Tên" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/hotels/${data.id}`" class="link">{{ data.name }}</RouterLink>
        </template>
      </Column>
      <Column field="city" header="Thành phố" />
      <Column field="star_rating" header="Hạng sao">
        <template #body="{ data }">
          <span v-if="data.star_rating">
            <i v-for="i in data.star_rating" :key="i" class="pi pi-star-fill" style="color: #f59e0b" />
          </span>
        </template>
      </Column>
      <Column field="contact_phone" header="SĐT" />
      <Column field="is_active" header="Trạng thái">
        <template #body="{ data }">
          <Tag v-if="data.is_active" severity="success" value="Hoạt động" />
          <Tag v-else severity="secondary" value="Tạm ngưng" />
        </template>
      </Column>
      <Column header="" style="width: 60px">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded @click="router.push(`/hotels/${data.id}`)" />
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
