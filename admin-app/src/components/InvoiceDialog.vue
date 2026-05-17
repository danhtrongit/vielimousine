<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import ProgressSpinner from 'primevue/progressspinner';
import InvoicePreview from './InvoicePreview.vue';
import { invoiceApi, type InvoiceTemplate, type InvoiceData } from '@/api/invoice.api';

const props = defineProps<{
  visible: boolean;
  order: {
    id: number;
    code: string;
    invoice_number?: string | null;
    status: string;
  } | null;
}>();
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void;
  (e: 'assigned', invoiceNumber: string): void;
}>();

const template = ref<InvoiceTemplate>('receipt');
const loading = ref(false);
const error = ref<string>('');
const data = ref<InvoiceData | null>(null);
const needsRefresh = ref(false);

const templates: { value: InvoiceTemplate; label: string; hint: string }[] = [
  { value: 'receipt', label: 'Phiếu thu', hint: 'A5, đơn giản, không có VAT' },
  { value: 'tax_invoice', label: 'Hoá đơn bán hàng', hint: 'A4, có VAT, MST, ngân hàng' },
];

const isCancelled = computed(() => props.order?.status === 'cancelled');
const currentNumber = computed(() => data.value?.order?.invoice_number || props.order?.invoice_number || '');

async function loadData() {
  if (!props.order) return;
  error.value = '';
  loading.value = true;
  data.value = null;
  try {
    data.value = await invoiceApi.fetchData(props.order.id, template.value);
    needsRefresh.value = true;
  } catch (e: any) {
    const msg = e?.response?.data?.errors?.[0]?.message || e?.message || 'Không tải được dữ liệu hoá đơn';
    error.value = msg;
  } finally {
    loading.value = false;
  }
}

function doPrint() {
  // Native browser print → CSS @media print isolates the invoice. User can Save as PDF.
  window.print();
}

function close() {
  emit('update:visible', false);
}

watch(
  () => [props.visible, template.value, props.order?.id],
  ([vis]) => {
    if (vis && props.order) {
      loadData();
    } else if (!vis) {
      data.value = null;
      if (needsRefresh.value) {
        needsRefresh.value = false;
        emit('assigned', currentNumber.value);
      }
    }
  }
);
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :header="`Xuất hoá đơn — ${order?.code || ''}`"
    :style="{ width: '960px' }"
    :breakpoints="{ '1100px': '96vw' }"
    dismissable-mask
    :pt="{ root: { class: 'invoice-dialog-root' } }"
  >
    <div v-if="isCancelled" class="cancelled-banner">
      <i class="pi pi-info-circle" />
      Đơn hàng này đã huỷ — hoá đơn xuất ra sẽ có dấu "ĐÃ HUỶ".
    </div>

    <div class="dlg-grid">
      <aside class="dlg-controls no-print">
        <h4>Chọn mẫu</h4>
        <div v-for="t in templates" :key="t.value" class="tpl-opt" @click="template = t.value">
          <RadioButton v-model="template" :value="t.value" :input-id="`tpl-${t.value}`" />
          <label :for="`tpl-${t.value}`">
            <strong>{{ t.label }}</strong>
            <small>{{ t.hint }}</small>
          </label>
        </div>

        <div class="number-info">
          <h4>Số hoá đơn</h4>
          <p v-if="currentNumber" class="num-existing">
            <i class="pi pi-check-circle" /> {{ currentNumber }}
          </p>
          <p v-else class="num-new">
            <i class="pi pi-info-circle" /> Sẽ tự tạo khi tải dữ liệu
          </p>
        </div>

        <div class="hint">
          <i class="pi pi-info-circle" />
          Bấm <strong>In / Lưu PDF</strong> để mở hộp thoại in. Trong dialog có thể chọn
          <em>"Save as PDF"</em> để lưu thành file PDF.
        </div>
      </aside>

      <div class="dlg-preview">
        <div v-if="loading" class="state">
          <ProgressSpinner style="width: 36px; height: 36px" />
          <p>Đang tải dữ liệu hoá đơn…</p>
        </div>
        <div v-else-if="error" class="state state-error">
          <i class="pi pi-exclamation-triangle" style="font-size: 2rem" />
          <p>{{ error }}</p>
          <Button label="Thử lại" icon="pi pi-refresh" size="small" outlined @click="loadData" />
        </div>
        <div v-else-if="data" class="preview-frame">
          <InvoicePreview :data="data" />
        </div>
      </div>
    </div>

    <template #footer>
      <Button label="Đóng" severity="secondary" outlined @click="close" />
      <Button label="In / Lưu PDF" icon="pi pi-print" :disabled="!data || loading" @click="doPrint" />
    </template>
  </Dialog>
</template>

<style scoped>
.cancelled-banner {
  background: #fef3c7; border: 1px solid #fbbf24; color: #92400e;
  padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;
  font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;
}
.dlg-grid {
  display: grid; grid-template-columns: 240px 1fr; gap: 1rem;
  min-height: 540px;
}
.dlg-controls h4 { margin: 0 0 0.5rem; font-size: 0.92rem; font-weight: 600; }
.tpl-opt {
  display: flex; align-items: flex-start; gap: 0.5rem;
  padding: 0.55rem 0.7rem; border: 1px solid var(--p-surface-300, #e5e7eb);
  border-radius: 6px; margin-bottom: 0.5rem; cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.tpl-opt:hover { background: var(--p-surface-50, #fafafa); }
.tpl-opt label { display: flex; flex-direction: column; gap: 0.15rem; cursor: pointer; flex: 1; }
.tpl-opt label small { color: var(--p-text-muted-color, #6b7280); font-size: 0.76rem; }
.number-info { margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--p-surface-200, #f3f4f6); }
.num-existing { color: #047857; font-size: 0.88rem; font-weight: 600; margin: 0; }
.num-new { color: var(--p-text-muted-color, #6b7280); font-size: 0.85rem; margin: 0; }
.hint {
  margin-top: 1rem; padding: 0.55rem 0.7rem;
  background: var(--p-surface-50, #fafafa);
  border-left: 2px solid var(--p-surface-300, #d1d5db);
  font-size: 0.78rem; color: #4b5563; line-height: 1.5;
}
.hint .pi { margin-right: 0.25rem; }

.dlg-preview {
  background: #e5e7eb;
  border-radius: 6px;
  padding: 12px;
  overflow: auto;
  max-height: 70vh;
}
.preview-frame {
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.06);
}
.state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 0.6rem; min-height: 320px; text-align: center;
  color: var(--p-text-muted-color, #6b7280);
}
.state-error { color: #991b1b; }

@media (max-width: 1100px) {
  .dlg-grid { grid-template-columns: 1fr; }
  .dlg-preview { max-height: 60vh; }
}
</style>
