<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import StatusTag from '@/components/StatusTag.vue';
import { customersApi } from '@/api/customers.api';
import { ordersApi } from '@/api/orders.api';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND, formatDate } from '@/composables/useFormat';
import type { CustomerListItem } from '@/types/customer';
import type { Order } from '@/types/order';

const route = useRoute();
const ui = useUIStore();
const notify = useNotify();

const customer = ref<CustomerListItem | null>(null);
const orders = ref<Order[]>([]);
const loading = ref(true);

const id = computed(() => Number(route.params.id));

onMounted(async () => {
  try {
    const [cResp, oResp] = await Promise.all([
      customersApi.get(id.value),
      ordersApi.list({ customer_id: id.value, per_page: 50, sort: 'created_at', order: 'desc' }),
    ]);
    customer.value = cResp.data;
    orders.value = oResp.data;
    ui.setBreadcrumb([
      { label: 'Khách hàng', to: '/customers' },
      { label: cResp.data.name },
    ]);
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else-if="customer">
    <h1 class="page-title">{{ customer.name }}</h1>

    <Card class="info-card">
      <template #content>
        <div class="kv-grid">
          <div class="kv"><span>SĐT:</span> <strong>{{ customer.phone }}</strong></div>
          <div class="kv"><span>Email:</span> <strong>{{ customer.email ?? '—' }}</strong></div>
          <div class="kv"><span>Số đơn:</span> <strong>{{ customer.booking_count }}</strong></div>
          <div v-if="customer.vat_company_name" class="kv">
            <span>Công ty VAT:</span> <strong>{{ customer.vat_company_name }}</strong>
          </div>
          <div v-if="customer.vat_tax_code" class="kv">
            <span>MST:</span> <strong>{{ customer.vat_tax_code }}</strong>
          </div>
        </div>
      </template>
    </Card>

    <h3 class="section-title">Lịch sử đơn hàng</h3>
    <DataTable :value="orders" data-key="id" :empty-message="'Chưa có đơn'">
      <Column field="code" header="Mã">
        <template #body="{ data }">
          <RouterLink :to="`/orders/${data.id}`">{{ data.code }}</RouterLink>
        </template>
      </Column>
      <Column field="checkin" header="Check-in">
        <template #body="{ data }">{{ formatDate(data.checkin) }}</template>
      </Column>
      <Column field="total" header="Tổng">
        <template #body="{ data }">{{ formatVND(data.total) }}</template>
      </Column>
      <Column field="payment_status" header="Thanh toán">
        <template #body="{ data }"><StatusTag :value="data.payment_status" kind="payment" /></template>
      </Column>
      <Column field="status" header="Trạng thái">
        <template #body="{ data }"><StatusTag :value="data.status" /></template>
      </Column>
      <Column field="created_at" header="Tạo lúc">
        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.page-title { margin: 0 0 1.25rem; font-size: 1.5rem; font-weight: 600; }
.info-card { margin-bottom: 1.5rem; }
.kv-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem 1.5rem; }
.kv { display: flex; gap: 0.5rem; font-size: 0.95rem; }
.kv span { color: var(--p-text-muted-color); }
.section-title { margin: 1.5rem 0 1rem; font-size: 1.1rem; font-weight: 500; }
</style>
