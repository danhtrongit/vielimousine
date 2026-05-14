<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import ProgressSpinner from 'primevue/progressspinner';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { useUIStore } from '@/stores/ui.store';
import { formatVND } from '@/composables/useFormat';
import type { Order, Payment } from '@/types/order';

const orders30d = ref<Order[]>([]);
const payments30d = ref<Payment[]>([]);
const loading = ref(true);

const ui = useUIStore();
ui.setBreadcrumb([{ label: 'Dashboard' }]);

function ymd(d: Date): string {
  return d.toISOString().slice(0, 10);
}

const today = new Date();
const todayStr = ymd(today);
const thirtyDaysAgo = ymd(new Date(today.getTime() - 30 * 24 * 3600 * 1000));

onMounted(async () => {
  try {
    const [ordersResp, paymentsResp] = await Promise.all([
      ordersApi.list({ date_from: thirtyDaysAgo, date_to: todayStr, per_page: 200 }),
      paymentsApi.list({ date_from: thirtyDaysAgo, date_to: todayStr, per_page: 500 }),
    ]);
    orders30d.value = ordersResp.data;
    payments30d.value = paymentsResp.data;
  } finally {
    loading.value = false;
  }
});

const todayOrdersCount = computed(() =>
  orders30d.value.filter((o) => o.created_at.startsWith(todayStr)).length
);

const revenue30d = computed(() =>
  orders30d.value
    .filter((o) => o.status !== 'cancelled')
    .reduce((sum, o) => sum + (o.total ?? 0), 0)
);

const paid30d = computed(() =>
  payments30d.value
    .filter((p) => p.type === 'payment' || p.type === 'deposit')
    .reduce((sum, p) => sum + (p.amount ?? 0), 0)
);

const chartData = computed(() => {
  const buckets: Record<string, number> = {};
  for (let i = 29; i >= 0; i--) {
    const d = ymd(new Date(today.getTime() - i * 24 * 3600 * 1000));
    buckets[d] = 0;
  }
  for (const o of orders30d.value) {
    if (o.status === 'cancelled') continue;
    const d = o.created_at.slice(0, 10);
    if (buckets[d] !== undefined) buckets[d] += o.total;
  }
  const labels = Object.keys(buckets).map((d) => d.slice(5));
  return {
    labels,
    datasets: [{
      label: 'Doanh thu (VND)',
      data: Object.values(buckets),
      borderColor: '#fa541c',
      backgroundColor: 'rgba(250, 84, 28, 0.1)',
      fill: true,
      tension: 0.3,
    }],
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: {
      ticks: {
        callback: (v: number) => new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(v),
      },
    },
  },
};
</script>

<template>
  <div v-if="loading" class="loading">
    <ProgressSpinner />
  </div>
  <div v-else>
    <h1 class="page-title">Tổng quan</h1>

    <div class="cards">
      <Card class="card-orders">
        <template #title><span>Đơn hôm nay</span></template>
        <template #content>
          <div class="card-value">{{ todayOrdersCount }}</div>
          <div class="card-sub">đơn hàng mới</div>
        </template>
      </Card>

      <Card class="card-revenue">
        <template #title><span>Doanh thu 30 ngày</span></template>
        <template #content>
          <div class="card-value">{{ formatVND(revenue30d) }}</div>
          <div class="card-sub">đã xác nhận + chờ</div>
        </template>
      </Card>

      <Card class="card-paid">
        <template #title><span>Đã thu 30 ngày</span></template>
        <template #content>
          <div class="card-value">{{ formatVND(paid30d) }}</div>
          <div class="card-sub">deposit + payment</div>
        </template>
      </Card>
    </div>

    <Card class="chart-card">
      <template #title><span>Doanh thu hàng ngày (30 ngày qua)</span></template>
      <template #content>
        <div class="chart-wrap">
          <Chart type="line" :data="chartData" :options="chartOptions" />
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.page-title { margin: 0 0 1.25rem; font-size: 1.5rem; font-weight: 600; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
.card-value { font-size: 1.75rem; font-weight: 700; color: var(--p-primary-color); }
.card-sub { color: var(--p-text-muted-color); font-size: 0.85rem; margin-top: 0.25rem; }
.chart-wrap { height: 320px; }
</style>
