<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Chart from 'primevue/chart';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import SectionCard from '@/components/SectionCard.vue';
import LoadingState from '@/components/LoadingState.vue';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { useUIStore } from '@/stores/ui.store';
import { formatVND, ymdLocal } from '@/composables/useFormat';
import type { Order, Payment } from '@/types/order';

const orders30d = ref<Order[]>([]);
const payments30d = ref<Payment[]>([]);
const loading = ref(true);

const ui = useUIStore();
ui.setBreadcrumb([{ label: 'Tổng quan' }]);

const today = new Date();
const todayStr = ymdLocal(today);
const thirtyDaysAgo = ymdLocal(new Date(today.getTime() - 30 * 24 * 3600 * 1000));

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
  orders30d.value.filter((o) => o.created_at.startsWith(todayStr)).length,
);

const revenue30d = computed(() =>
  orders30d.value
    .filter((o) => o.status !== 'cancelled')
    .reduce((sum, o) => sum + (o.total ?? 0), 0),
);

const paid30d = computed(() =>
  payments30d.value
    .filter((p) => p.type === 'payment' || p.type === 'deposit')
    .reduce((sum, p) => sum + (p.amount ?? 0), 0),
);

const confirmedCount = computed(() =>
  orders30d.value.filter((o) => o.status === 'confirmed' || o.status === 'completed').length,
);

function readToken(name: string, fallback: string): string {
  if (typeof window === 'undefined') return fallback;
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

const chartTick = ref(0);
watch(() => ui.theme, () => { chartTick.value++; });

const chartData = computed(() => {
  // Force recompute when theme changes
  void chartTick.value;

  const buckets: Record<string, number> = {};
  for (let i = 29; i >= 0; i--) {
    const d = ymdLocal(new Date(today.getTime() - i * 24 * 3600 * 1000));
    buckets[d] = 0;
  }
  for (const o of orders30d.value) {
    if (o.status === 'cancelled') continue;
    const d = o.created_at.slice(0, 10);
    if (buckets[d] !== undefined) buckets[d] += o.total;
  }
  const labels = Object.keys(buckets).map((d) => d.slice(5));

  const primary = readToken('--p-primary-500', '#fa541c');
  const primarySoft = `${primary}1a`;

  return {
    labels,
    datasets: [{
      label: 'Doanh thu (VND)',
      data: Object.values(buckets),
      borderColor: primary,
      backgroundColor: primarySoft,
      pointBackgroundColor: primary,
      pointRadius: 0,
      pointHoverRadius: 5,
      fill: true,
      tension: 0.35,
      borderWidth: 2,
    }],
  };
});

const chartOptions = computed(() => {
  void chartTick.value;
  const axis = readToken('--app-chart-axis', '#64748b');
  const grid = readToken('--app-chart-grid', '#e2e8f0');
  const tooltipBg = ui.theme === 'dark'
    ? readToken('--p-surface-100', '#f1f5f9')
    : readToken('--p-surface-900', '#0f172a');
  const tooltipColor = ui.theme === 'dark'
    ? readToken('--p-surface-900', '#0f172a')
    : '#ffffff';
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' as const },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: tooltipBg,
        titleColor: tooltipColor,
        bodyColor: tooltipColor,
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
        callbacks: {
          label: (ctx: any) => ' ' + new Intl.NumberFormat('vi-VN').format(ctx.parsed.y) + ' đ',
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: axis, font: { size: 11 } },
      },
      y: {
        grid: { color: grid, drawBorder: false },
        ticks: {
          color: axis,
          font: { size: 11 },
          callback: (v: number) =>
            new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(v),
        },
      },
    },
  };
});
</script>

<template>
  <div class="dashboard">
    <PageHeader title="Tổng quan" subtitle="Số liệu 30 ngày gần nhất" icon="pi pi-th-large" />

    <LoadingState v-if="loading" variant="skeleton-cards" :rows="3" />

    <template v-else>
      <div class="kpi-grid">
        <StatCard
          label="Đơn hôm nay"
          :value="todayOrdersCount"
          sub="đơn hàng mới"
          icon="pi pi-shopping-cart"
          accent="primary"
        />
        <StatCard
          label="Doanh thu 30 ngày"
          :value="formatVND(revenue30d)"
          sub="đã xác nhận + chờ"
          icon="pi pi-chart-line"
          accent="info"
        />
        <StatCard
          label="Đã thu 30 ngày"
          :value="formatVND(paid30d)"
          sub="deposit + payment"
          icon="pi pi-wallet"
          accent="success"
        />
        <StatCard
          label="Đơn xác nhận"
          :value="confirmedCount"
          sub="trong 30 ngày"
          icon="pi pi-check-circle"
          accent="neutral"
          :mono="true"
        />
      </div>

      <SectionCard title="Doanh thu hàng ngày" subtitle="30 ngày qua" icon="pi pi-chart-line">
        <div class="chart-wrap">
          <Chart type="line" :data="chartData" :options="chartOptions" />
        </div>
      </SectionCard>
    </template>
  </div>
</template>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: var(--space-4); }
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: var(--space-4);
}
.chart-wrap { height: 320px; }
</style>
