<script setup lang="ts">
import { onMounted } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { formatDate } from '@/composables/useFormat';

const router = useRouter();
const ui = useUIStore();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'SĐT / Tên / Email', type: 'string' },
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Khách hàng' }]);
});
</script>

<template>
  <div>
    <PageHeader title="Khách hàng" icon="pi pi-users" />
    <DataTablePanel
      endpoint="/customers"
      :defaults="{ sort: 'created_at', order: 'desc' }"
      storage-key="customers.list.v1"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="phone" header="SĐT" sortable />
      <Column field="name" header="Tên" sortable />
      <Column field="email" header="Email">
        <template #body="{ data }">{{ data.email ?? '—' }}</template>
      </Column>
      <Column field="booking_count" header="Số đơn" sortable />
      <Column field="vat_company_name" header="Công ty VAT">
        <template #body="{ data }">{{ data.vat_company_name ?? '—' }}</template>
      </Column>
      <Column field="created_at" header="Tạo lúc" sortable>
        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
      </Column>
      <Column header="" style="width: 60px">
        <template #body="{ data }">
          <Button icon="pi pi-eye" text rounded @click="router.push(`/customers/${data.id}`)" />
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
</style>
