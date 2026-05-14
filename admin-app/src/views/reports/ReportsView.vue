<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Dropdown from 'primevue/dropdown';
import Calendar from 'primevue/calendar';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import RevenueReport from './RevenueReport.vue';
import ByHotelReport from './ByHotelReport.vue';
import BySourceReport from './BySourceReport.vue';
import BySalesReport from './BySalesReport.vue';
import ReceivableReport from './ReceivableReport.vue';
import ReceivedCashReport from './ReceivedCashReport.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { useLookupStore } from '@/stores/lookup.store';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import type { Order, Payment } from '@/types/order';

const ui = useUIStore();
const notify = useNotify();
const lookup = useLookupStore();

const today = new Date();
const thirtyAgo = new Date(today.getTime() - 29 * 24 * 3600 * 1000);

const dateFrom = ref<Date>(thirtyAgo);
const dateTo = ref<Date>(today);
const activeTab = ref('revenue');
const orders = ref<Order[]>([]);
const payments = ref<Payment[]>([]);
const loading = ref(false);

const PRESETS = [
  { label: 'Hôm nay', value: 'today' },
  { label: '7 ngày qua', value: 'last7' },
  { label: '30 ngày qua', value: 'last30' },
  { label: 'Tháng này', value: 'thisMonth' },
  { label: 'Tháng trước', value: 'lastMonth' },
];
const presetValue = ref<string>('last30');

function applyPreset(p: string) {
  const now = new Date();
  switch (p) {
    case 'today':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      dateTo.value = now;
      break;
    case 'last7':
      dateFrom.value = new Date(now.getTime() - 6 * 24 * 3600 * 1000);
      dateTo.value = now;
      break;
    case 'last30':
      dateFrom.value = new Date(now.getTime() - 29 * 24 * 3600 * 1000);
      dateTo.value = now;
      break;
    case 'thisMonth':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1);
      dateTo.value = now;
      break;
    case 'lastMonth':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      dateTo.value = new Date(now.getFullYear(), now.getMonth(), 0);
      break;
  }
}

function fmt(d: Date): string {
  return d.toISOString().slice(0, 10);
}

async function reload() {
  loading.value = true;
  try {
    const [oResp, pResp] = await Promise.all([
      ordersApi.list({
        date_from: fmt(dateFrom.value),
        date_to: fmt(dateTo.value),
        per_page: 5000,
      }),
      paymentsApi.list({
        date_from: fmt(dateFrom.value),
        date_to: fmt(dateTo.value),
        per_page: 5000,
      }),
    ]);
    orders.value = oResp.data;
    payments.value = pResp.data;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

watch([dateFrom, dateTo], reload, { immediate: false });

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Báo cáo' }]);
  await lookup.ensureLoaded();
  await reload();
});
</script>

<template>
  <div>
    <div class="header">
      <h1 class="page-title">Báo cáo</h1>
      <div class="date-controls">
        <Dropdown
          v-model="presetValue"
          :options="PRESETS"
          option-label="label"
          option-value="value"
          @change="applyPreset(presetValue)"
          style="min-width: 140px"
        />
        <Calendar v-model="dateFrom" date-format="yy-mm-dd" show-icon />
        <span>→</span>
        <Calendar v-model="dateTo" date-format="yy-mm-dd" show-icon />
        <Button label="Áp dụng" icon="pi pi-refresh" @click="reload" :loading="loading" />
      </div>
    </div>

    <div v-if="loading" class="loading-banner">
      <ProgressSpinner style="width: 24px; height: 24px" />
      <span>Đang tổng hợp dữ liệu...</span>
    </div>
    <p v-else class="data-summary">
      Đang xem <strong>{{ orders.length }}</strong> đơn hàng và
      <strong>{{ payments.length }}</strong> giao dịch trong khoảng đã chọn.
    </p>

    <TabView v-model:value="activeTab">
      <TabPanel header="Doanh thu" value="revenue">
        <RevenueReport v-if="activeTab === 'revenue'" :orders="orders" :payments="payments" />
      </TabPanel>
      <TabPanel header="Theo khách sạn" value="by-hotel">
        <ByHotelReport v-if="activeTab === 'by-hotel'" :orders="orders" />
      </TabPanel>
      <TabPanel header="Theo nguồn" value="by-source">
        <BySourceReport v-if="activeTab === 'by-source'" :orders="orders" />
      </TabPanel>
      <TabPanel header="Theo sales" value="by-sales">
        <BySalesReport v-if="activeTab === 'by-sales'" :orders="orders" />
      </TabPanel>
      <TabPanel header="Công nợ" value="receivable">
        <ReceivableReport v-if="activeTab === 'receivable'" :orders="orders" />
      </TabPanel>
      <TabPanel header="Thực thu" value="received-cash">
        <ReceivedCashReport v-if="activeTab === 'received-cash'" :payments="payments" />
      </TabPanel>
    </TabView>
  </div>
</template>

<style scoped>
.header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;
}
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.date-controls { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.loading-banner { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; margin-bottom: 1rem; }
.data-summary { color: var(--p-text-muted-color); font-size: 0.9rem; margin: 0 0 1rem; }
</style>
