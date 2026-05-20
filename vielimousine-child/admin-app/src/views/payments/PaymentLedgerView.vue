<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { useCsvExport } from '@/composables/useCsvExport';
import { paymentsApi } from '@/api/payments.api';
import { formatVND, formatDateTime } from '@/composables/useFormat';
import { labelPaymentMethod, labelGateway } from '@/stores/lookup.store';

const ui = useUIStore();
const notify = useNotify();
const csv = useCsvExport();
const exporting = ref(false);

const filterSchema: FilterDef[] = [
  { key: 'order_id', label: 'Mã đơn', type: 'string' },
  { key: 'type', label: 'Loại', type: 'enum', options: [
    { label: 'Đặt cọc', value: 'deposit' },
    { label: 'Thanh toán', value: 'payment' },
    { label: 'Hoàn tiền', value: 'refund' },
    { label: 'Hủy bút toán', value: 'void' },
  ]},
  { key: 'method', label: 'Phương thức', type: 'enum', options: [
    { label: 'Chuyển khoản', value: 'bank_transfer' },
    { label: 'SePay', value: 'sepay' },
    { label: 'Tiền mặt', value: 'cash' },
    { label: 'Khác', value: 'manual' },
  ]},
  { key: 'gateway', label: 'Cổng thanh toán', type: 'string' },
  { key: 'date_from', label: 'Từ ngày', type: 'date' },
  { key: 'date_to', label: 'Đến ngày', type: 'date' },
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Sổ thanh toán' }]);
});

function typeSeverity(type: string): string {
  return {
    deposit: 'info',
    payment: 'success',
    refund: 'danger',
    void: 'secondary',
  }[type] ?? 'secondary';
}

function typeLabel(type: string): string {
  return {
    deposit: 'Cọc',
    payment: 'Thanh toán',
    refund: 'Hoàn',
    void: 'Hủy',
  }[type] ?? type;
}

async function exportAll() {
  exporting.value = true;
  try {
    const resp = await paymentsApi.list({ per_page: 5000 });
    const rows = resp.data.map((p) => [
      formatDateTime(p.created_at),
      p.order_id,
      typeLabel(p.type),
      labelPaymentMethod(p.method),
      labelGateway(p.gateway),
      p.transaction_id ?? '',
      p.amount,
      p.note ?? '',
    ]);
    const today = new Date().toISOString().slice(0, 10);
    csv.downloadCsv(
      `vie-payments-${today}.csv`,
      ['Thời gian', 'Order ID', 'Loại', 'Phương thức', 'Cổng', 'Mã giao dịch', 'Số tiền', 'Ghi chú'],
      rows
    );
    notify.success('Đã xuất CSV', `${rows.length} dòng`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    exporting.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Sổ thanh toán" subtitle="Lịch sử giao dịch" icon="pi pi-wallet">
      <Button label="Xuất CSV" icon="pi pi-download" :loading="exporting" outlined @click="exportAll" />
    </PageHeader>

    <DataTablePanel
      endpoint="/payments"
      :defaults="{ sort: 'created_at', order: 'desc' }"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="created_at" header="Thời gian" sortable>
        <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
      </Column>
      <Column field="order_id" header="Đơn hàng">
        <template #body="{ data }">
          <RouterLink :to="`/orders/${data.order_id}`" class="link">#{{ data.order_id }}</RouterLink>
        </template>
      </Column>
      <Column field="type" header="Loại">
        <template #body="{ data }">
          <Tag :severity="typeSeverity(data.type) as any" :value="typeLabel(data.type)" />
        </template>
      </Column>
      <Column field="method" header="Phương thức">
        <template #body="{ data }">{{ labelPaymentMethod(data.method) }}</template>
      </Column>
      <Column field="gateway" header="Cổng">
        <template #body="{ data }">{{ labelGateway(data.gateway) }}</template>
      </Column>
      <Column field="transaction_id" header="Mã giao dịch">
        <template #body="{ data }">
          <code v-if="data.transaction_id" class="txn">{{ data.transaction_id }}</code>
          <span v-else>—</span>
        </template>
      </Column>
      <Column field="amount" header="Số tiền" sortable>
        <template #body="{ data }">
          <span :class="data.type === 'refund' || data.type === 'void' ? 'amount-neg' : 'amount-pos'">
            {{ data.type === 'refund' || data.type === 'void' ? '−' : '+' }}{{ formatVND(data.amount) }}
          </span>
        </template>
      </Column>
      <Column field="note" header="Ghi chú">
        <template #body="{ data }">
          <span class="truncate">{{ data.note ?? '—' }}</span>
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.txn { font-family: monospace; font-size: 0.85rem; background: var(--p-surface-100); padding: 2px 5px; border-radius: 3px; }
.amount-pos { color: var(--p-green-700); font-weight: 600; }
.amount-neg { color: var(--p-red-600); font-weight: 600; }
.truncate { display: inline-block; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
</style>
