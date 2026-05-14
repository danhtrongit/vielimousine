<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import Button from 'primevue/button';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import type { Order, Payment } from '@/types/order';

const props = defineProps<{ orders: Order[]; payments: Payment[] }>();
const csv = useCsvExport();

// Match Dashboard logic: filter out cancelled orders
const filtered = computed(() => props.orders.filter((o) => o.status !== 'cancelled'));

const summary = computed(() => {
  const f = filtered.value;
  const revenue = f.reduce((s, o) => s + (o.total ?? 0), 0);
  const paid = props.payments
    .filter((p) => p.type === 'payment' || p.type === 'deposit')
    .reduce((s, p) => s + (p.amount ?? 0), 0);
  const outstanding = f.reduce((s, o) => s + Math.max(0, (o.total ?? 0) - (o.paid_amount ?? 0)), 0);
  const cost = f.reduce((s, o) => s + ((o as any).cost_total ?? 0), 0);
  const profit = revenue - cost;
  return {
    orders: f.length,
    revenue,
    paid,
    outstanding,
    cost,
    profit,
  };
});

const chartData = computed(() => {
  const buckets = new Map<string, number>();
  for (const o of filtered.value) {
    const d = o.created_at.slice(0, 10);
    buckets.set(d, (buckets.get(d) ?? 0) + (o.total ?? 0));
  }
  const labels = [...buckets.keys()].sort();
  return {
    labels,
    datasets: [
      {
        label: 'Doanh thu (VND)',
        data: labels.map((l) => buckets.get(l) ?? 0),
        borderColor: '#fa541c',
        backgroundColor: 'rgba(250, 84, 28, 0.1)',
        fill: true,
        tension: 0.3,
      },
    ],
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

function exportCsv() {
  const s = summary.value;
  csv.downloadCsv(
    `vie-revenue-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Chỉ tiêu', 'Giá trị'],
    [
      ['Tổng số đơn (không tính hủy)', s.orders],
      ['Doanh thu', s.revenue],
      ['Đã thu', s.paid],
      ['Còn phải thu', s.outstanding],
      ['Chi phí', s.cost],
      ['Lợi nhuận', s.profit],
    ]
  );
}
</script>

<template>
  <div class="revenue-report">
    <div class="cards">
      <Card class="metric"><template #title>Số đơn</template>
        <template #content><div class="value">{{ summary.orders }}</div></template>
      </Card>
      <Card class="metric"><template #title>Doanh thu</template>
        <template #content><div class="value">{{ formatVND(summary.revenue) }}</div></template>
      </Card>
      <Card class="metric"><template #title>Đã thu</template>
        <template #content><div class="value success">{{ formatVND(summary.paid) }}</div></template>
      </Card>
      <Card class="metric"><template #title>Còn phải thu</template>
        <template #content><div class="value warn">{{ formatVND(summary.outstanding) }}</div></template>
      </Card>
      <Card class="metric"><template #title>Chi phí</template>
        <template #content><div class="value">{{ formatVND(summary.cost) }}</div></template>
      </Card>
      <Card class="metric"><template #title>Lợi nhuận</template>
        <template #content><div class="value primary">{{ formatVND(summary.profit) }}</div></template>
      </Card>
    </div>

    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <Card>
      <template #title>Doanh thu theo ngày</template>
      <template #content>
        <div class="chart-wrap">
          <Chart type="line" :data="chartData" :options="chartOptions" />
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.revenue-report { display: flex; flex-direction: column; gap: 1rem; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
.metric { padding: 0; }
.metric :deep(.p-card-title) { font-size: 0.85rem; color: var(--p-text-muted-color); font-weight: 500; }
.value { font-size: 1.5rem; font-weight: 700; }
.value.success { color: var(--p-green-600); }
.value.warn { color: var(--p-orange-600); }
.value.primary { color: var(--p-primary-700); }
.actions { display: flex; justify-content: flex-end; }
.chart-wrap { height: 320px; }
</style>
