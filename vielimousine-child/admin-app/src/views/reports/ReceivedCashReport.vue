<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import { labelPaymentMethod } from '@/stores/lookup.store';
import type { Payment } from '@/types/order';

const props = defineProps<{ payments: Payment[] }>();
const csv = useCsvExport();

// Filter receiving payments only (positive cash inflows)
const inflows = computed(() =>
  props.payments.filter((p) => p.type === 'payment' || p.type === 'deposit')
);

// Aggregate by date × method
const aggregated = computed(() => {
  const byDate = new Map<string, Map<string, { txns: number; amount: number }>>();
  const methods = new Set<string>();

  for (const p of inflows.value) {
    const date = (p.paid_at ?? p.created_at ?? '').slice(0, 10);
    if (!date) continue;
    const method = p.method || 'unknown';
    methods.add(method);

    if (!byDate.has(date)) byDate.set(date, new Map());
    const dateMap = byDate.get(date)!;
    if (!dateMap.has(method)) dateMap.set(method, { txns: 0, amount: 0 });
    const row = dateMap.get(method)!;
    row.txns++;
    row.amount += p.amount ?? 0;
  }

  // Flatten to table rows
  const rows: Array<{ date: string; method: string; txns: number; amount: number }> = [];
  for (const [date, mMap] of byDate) {
    for (const [method, vals] of mMap) {
      rows.push({ date, method, txns: vals.txns, amount: vals.amount });
    }
  }
  rows.sort((a, b) => (a.date < b.date ? 1 : -1));
  return { rows, methods: [...methods].sort() };
});

const METHOD_COLORS: Record<string, string> = {
  sepay: '#3b82f6',
  bank_transfer: '#fa541c',
  cash: '#20af48',
  manual: '#a855f7',
};

const chartData = computed(() => {
  const dates = [...new Set(aggregated.value.rows.map((r) => r.date))].sort();
  return {
    labels: dates,
    datasets: aggregated.value.methods.map((method) => ({
      label: labelPaymentMethod(method),
      data: dates.map((d) => {
        const row = aggregated.value.rows.find((r) => r.date === d && r.method === method);
        return row?.amount ?? 0;
      }),
      backgroundColor: METHOD_COLORS[method] ?? '#94a3b8',
    })),
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: { stacked: true },
    y: {
      stacked: true,
      ticks: {
        callback: (v: number) => new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(v),
      },
    },
  },
};

const totalAmount = computed(() =>
  aggregated.value.rows.reduce((s, r) => s + r.amount, 0)
);

function exportCsv() {
  csv.downloadCsv(
    `vie-received-cash-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Ngày', 'Phương thức', 'Số giao dịch', 'Số tiền'],
    aggregated.value.rows.map((r) => [r.date, r.method, r.txns, r.amount])
  );
}
</script>

<template>
  <div class="report">
    <Card class="summary">
      <template #content>
        <div class="summary-value">{{ formatVND(totalAmount) }}</div>
        <div class="summary-label">
          Tổng thu trong khoảng — {{ aggregated.rows.length }} dòng / {{ aggregated.methods.length }} phương thức
        </div>
      </template>
    </Card>

    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <Card>
      <template #title>Thực thu theo ngày × phương thức</template>
      <template #content>
        <div class="chart-wrap"><Chart type="bar" :data="chartData" :options="chartOptions" /></div>
      </template>
    </Card>

    <Card>
      <template #title>Chi tiết</template>
      <template #content>
        <DataTable :value="aggregated.rows" :empty-message="'Không có giao dịch thu trong khoảng'">
          <Column field="date" header="Ngày" sortable />
          <Column field="method" header="Phương thức">
            <template #body="{ data }">{{ labelPaymentMethod(data.method) }}</template>
          </Column>
          <Column field="txns" header="Số giao dịch" sortable />
          <Column header="Số tiền" sortable>
            <template #body="{ data }">{{ formatVND(data.amount) }}</template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.summary { text-align: center; }
.summary-value { font-size: 2rem; font-weight: 700; color: var(--p-green-600); }
.summary-label { color: var(--p-text-muted-color); font-size: 0.9rem; margin-top: 0.25rem; }
.actions { display: flex; justify-content: flex-end; }
.chart-wrap { height: 360px; }
</style>
