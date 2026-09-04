<script setup lang="ts">
import { onMounted } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { formatVND, formatDate } from '@/composables/useFormat';

const router = useRouter();
const ui = useUIStore();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Code / mô tả', type: 'string' },
  { key: 'is_active', label: 'Trạng thái', type: 'enum', options: [
    { label: 'Hoạt động', value: '1' },
    { label: 'Vô hiệu', value: '0' },
  ]},
  { key: 'sales_only', label: 'Kênh áp dụng', type: 'enum', options: [
    { label: 'Chỉ kênh Sales', value: '1' },
    { label: 'Mọi kênh', value: '0' },
  ]},
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Mã giảm giá' }]);
});

function fmtValue(type: string, value: number): string {
  return type === 'percentage' ? `${value}%` : formatVND(value);
}
</script>

<template>
  <div>
    <PageHeader title="Mã giảm giá" subtitle="Quản lý khuyến mãi" icon="pi pi-ticket">
      <Button label="Tạo hàng loạt" icon="pi pi-bolt" severity="warn" outlined @click="router.push('/coupons/bulk')" />
      <Button label="Tạo mới" icon="pi pi-plus" @click="router.push('/coupons/new')" />
    </PageHeader>

    <DataTablePanel
      endpoint="/coupons"
      :defaults="{ sort: 'created_at', order: 'desc' }"
      storage-key="coupons.list.v1"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="code" header="Code" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/coupons/${data.id}`" class="link">{{ data.code }}</RouterLink>
        </template>
      </Column>
      <Column field="description" header="Mô tả">
        <template #body="{ data }">
          <span class="truncate">{{ data.description ?? '—' }}</span>
        </template>
      </Column>
      <Column header="Giảm">
        <template #body="{ data }">{{ fmtValue(data.type, data.value) }}</template>
      </Column>
      <Column field="min_order" header="Đơn tối thiểu">
        <template #body="{ data }">{{ data.min_order > 0 ? formatVND(data.min_order) : '—' }}</template>
      </Column>
      <Column header="Đã dùng">
        <template #body="{ data }">{{ data.used_count }}/{{ data.usage_limit ?? '∞' }}</template>
      </Column>
      <Column header="Thời hạn">
        <template #body="{ data }">
          {{ formatDate(data.valid_from) }} → {{ formatDate(data.valid_to) }}
        </template>
      </Column>
      <Column field="sales_only" header="Sales">
        <template #body="{ data }">
          <Tag v-if="data.sales_only" severity="warn" value="Sales" />
          <Tag v-else severity="info" value="Public" />
        </template>
      </Column>
      <Column field="is_active" header="Trạng thái">
        <template #body="{ data }">
          <Tag v-if="data.is_active" severity="success" value="Hoạt động" />
          <Tag v-else severity="secondary" value="Vô hiệu" />
        </template>
      </Column>
      <Column header="" style="width: 60px">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded @click="router.push(`/coupons/${data.id}`)" />
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.truncate { display: inline-block; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
</style>
