<script setup lang="ts">
import { onMounted } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
// note: Vue templates don't support generic component syntax; use DataTablePanel as plain component
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import StatusTag from '@/components/StatusTag.vue';
import Can from '@/components/Can.vue';
import { useUIStore } from '@/stores/ui.store';
import { ORDER_STATUSES, PAYMENT_STATUSES, ORDER_SOURCES } from '@/stores/lookup.store';
import { formatVND, formatDate } from '@/composables/useFormat';

const router = useRouter();
const ui = useUIStore();

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Mã đơn / SĐT / Tên', type: 'string' },
  { key: 'status', label: 'Trạng thái', type: 'enum', options: ORDER_STATUSES },
  { key: 'payment_status', label: 'Thanh toán', type: 'enum', options: PAYMENT_STATUSES },
  { key: 'source', label: 'Nguồn', type: 'enum', options: ORDER_SOURCES },
  { key: 'date_from', label: 'Từ ngày', type: 'date' },
  { key: 'date_to', label: 'Đến ngày', type: 'date' },
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Đơn hàng' }]);
});

function rowClick(data: { id: number }) {
  router.push(`/orders/${data.id}`);
}
</script>

<template>
  <div>
    <div class="header">
      <h1 class="page-title">Đơn hàng</h1>
      <Can cap="vie_create_orders">
        <Button label="Tạo đơn mới" icon="pi pi-plus" @click="router.push('/orders/new')" />
      </Can>
    </div>

    <DataTablePanel
      endpoint="/orders"
      :defaults="{ sort: 'created_at', order: 'desc' }"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="code" header="Mã đơn" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/orders/${data.id}`" class="link">{{ data.code }}</RouterLink>
        </template>
      </Column>
      <Column header="Khách hàng">
        <template #body="{ data }">
          <div class="customer-cell">
            <div>{{ data.customer_name }}</div>
            <small>{{ data.customer_phone }}</small>
          </div>
        </template>
      </Column>
      <Column field="checkin" header="Check-in" sortable>
        <template #body="{ data }">{{ formatDate(data.checkin) }}</template>
      </Column>
      <Column field="nights" header="Đêm" />
      <Column field="total" header="Tổng" sortable>
        <template #body="{ data }">{{ formatVND(data.total) }}</template>
      </Column>
      <Column field="payment_status" header="Thanh toán">
        <template #body="{ data }">
          <StatusTag :value="data.payment_status" kind="payment" />
        </template>
      </Column>
      <Column field="status" header="Trạng thái">
        <template #body="{ data }">
          <StatusTag :value="data.status" />
        </template>
      </Column>
      <Column field="created_at" header="Tạo lúc" sortable>
        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
      </Column>
      <Column header="" :exportable="false" style="width: 60px">
        <template #body="{ data }">
          <Button icon="pi pi-eye" text rounded @click="rowClick(data)" />
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.customer-cell small { color: var(--p-text-muted-color); }
</style>
