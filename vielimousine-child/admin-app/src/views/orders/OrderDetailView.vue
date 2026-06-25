<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ProgressSpinner from 'primevue/progressspinner';
import StatusTag from '@/components/StatusTag.vue';
import Can from '@/components/Can.vue';
import InvoiceDialog from '@/components/InvoiceDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { useUIStore } from '@/stores/ui.store';
import { labelBookingType, labelPaymentMethod, labelPaymentType, labelGateway } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { useAuthStore } from '@/stores/auth.store';
import { useCostVisibility } from '@/composables/useCostVisibility';
import { formatVND, formatDate, formatDateTime } from '@/composables/useFormat';
import type { OrderDetail } from '@/types/order';

const route = useRoute();
const ui = useUIStore();
const notify = useNotify();
const auth = useAuthStore();
const canManageOrders = computed(() => auth.can('vie_manage_orders'));
const canPrintInvoice = computed(() => auth.can('vie_print_order'));
const { canViewCost } = useCostVisibility();

const invoiceDialog = ref(false);
function openInvoiceDialog() {
  invoiceDialog.value = true;
}
function onInvoiceAssigned() {
  // Refresh order to pick up newly-assigned invoice_number.
  load();
}

const order = ref<OrderDetail | null>(null);
const loading = ref(true);
const orderId = computed(() => Number(route.params.id));

function locationAddress(loc: Record<string, unknown> | null | undefined): string {
  return loc && typeof loc.address === 'string' ? loc.address : '';
}
const pickupAddress = computed(() => locationAddress(order.value?.pickup));
const dropoffAddress = computed(() => locationAddress(order.value?.dropoff));
const vat = computed(() => {
  const v = order.value?.customer_vat as Record<string, string> | null | undefined;
  if (!v || typeof v !== 'object') return null;
  return (v.company_name || v.tax_code || v.address || v.email) ? v : null;
});

const cancelDialog = ref(false);
const cancelReason = ref('');
const cancelRefundAmount = ref(0);
const cancelling = ref(false);

function openCancelDialog() {
  cancelReason.value = '';
  cancelRefundAmount.value = order.value?.paid_amount ?? 0;
  cancelDialog.value = true;
}

const paymentDialog = ref(false);
const paymentForm = ref({ type: 'payment', amount: 0, method: 'bank_transfer', note: '', transaction_id: '' });
const saving = ref(false);

async function load() {
  loading.value = true;
  try {
    const resp = await ordersApi.get(orderId.value);
    order.value = resp.data;
    costDraft.value = resp.data.cost_total ?? 0;
    checkinCodeInput.value = resp.data.checkin_code ?? '';
    ui.setBreadcrumb([
      { label: 'Đơn hàng', to: '/orders' },
      { label: order.value.code ?? '' },
    ]);
  } catch (e) {
    notify.apiError(e, 'Không tải được đơn hàng');
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function doCancel() {
  if (!cancelReason.value.trim()) {
    notify.warn('Vui lòng nhập lý do hủy');
    return;
  }
  const refund = Math.max(0, Math.floor(cancelRefundAmount.value || 0));
  const paid = order.value?.paid_amount ?? 0;
  if (refund > paid) {
    notify.warn('Số tiền hoàn không được vượt quá số đã thanh toán');
    return;
  }
  cancelling.value = true;
  try {
    await ordersApi.cancel(orderId.value, cancelReason.value.trim(), refund);
    notify.success('Đã hủy đơn');
    cancelDialog.value = false;
    cancelReason.value = '';
    cancelRefundAmount.value = 0;
    await load();
  } catch (e) {
    notify.apiError(e, 'Không hủy được đơn');
  } finally {
    cancelling.value = false;
  }
}

async function addPayment() {
  if (paymentForm.value.amount <= 0) {
    notify.warn('Số tiền phải > 0');
    return;
  }
  saving.value = true;
  try {
    await paymentsApi.create({
      order_id: orderId.value,
      type: paymentForm.value.type,
      amount: paymentForm.value.amount,
      method: paymentForm.value.method,
      transaction_id: paymentForm.value.transaction_id || null,
      note: paymentForm.value.note || null,
    });
    notify.success('Đã ghi nhận thanh toán');
    paymentDialog.value = false;
    paymentForm.value = { type: 'payment', amount: 0, method: 'bank_transfer', note: '', transaction_id: '' };
    await load();
  } catch (e) {
    notify.apiError(e, 'Không ghi nhận được');
  } finally {
    saving.value = false;
  }
}

const canCancel = computed(() =>
  order.value && (order.value.status === 'pending' || order.value.status === 'confirmed')
);

const remaining = computed(() =>
  order.value ? Math.max(0, order.value.total - order.value.paid_amount) : 0
);

function roundVND(n: number): number {
  return Math.round(n / 1000) * 1000;
}

const paymentTypeOptions = [
  { label: 'Đặt cọc', value: 'deposit' },
  { label: 'Thanh toán', value: 'payment' },
  { label: 'Hoàn tiền', value: 'refund' },
  { label: 'Hủy giao dịch', value: 'void' },
];
const paymentMethodOptions = [
  { label: 'Chuyển khoản', value: 'bank_transfer' },
  { label: 'Tiền mặt', value: 'cash' },
  { label: 'SePay', value: 'sepay' },
  { label: 'Khác', value: 'manual' },
];

const quickAmounts = computed<Array<{ label: string; value: number }>>(() => {
  if (!order.value) return [];
  const total = order.value.total;
  const paid = order.value.paid_amount;
  if (paymentForm.value.type === 'refund' || paymentForm.value.type === 'void') {
    return [
      { label: 'Toàn bộ', value: paid },
      { label: '50%', value: roundVND(paid * 0.5) },
    ];
  }
  // deposit / payment
  return [
    { label: 'Còn lại', value: remaining.value },
    { label: '30%', value: roundVND(total * 0.3) },
    { label: '50%', value: roundVND(total * 0.5) },
    { label: '70%', value: roundVND(total * 0.7) },
    { label: '100%', value: total },
  ];
});

const transitioning = ref<string | null>(null);

const checkinCodeInput = ref('');
const sendingCheckinCode = ref(false);
const canSendCheckinCode = computed(() =>
  !!order.value
  && order.value.payment_status === 'paid'
  && order.value.status !== 'cancelled'
);
const checkinCodeDirty = computed(() =>
  !!order.value && checkinCodeInput.value.trim() !== (order.value.checkin_code ?? '')
);

async function sendCheckinCode() {
  const code = checkinCodeInput.value.trim();
  if (!code) {
    notify.warn('Vui lòng nhập mã nhận phòng');
    return;
  }
  if (!order.value?.customer_email) {
    notify.warn('Đơn không có email khách hàng để gửi');
    return;
  }
  const savedCode = order.value?.checkin_code ?? '';
  const email = order.value!.customer_email;
  let confirmMsg: string;
  if (!savedCode) {
    confirmMsg = `Gửi mã nhận phòng (${code}) cho ${email}?`;
  } else if (code === savedCode) {
    confirmMsg = `Gửi lại email mã nhận phòng (${code}) cho ${email}?`;
  } else {
    confirmMsg = `Cập nhật mã nhận phòng từ "${savedCode}" → "${code}" và gửi email cho ${email}?`;
  }
  if (!confirm(confirmMsg)) return;

  sendingCheckinCode.value = true;
  try {
    await ordersApi.sendCheckinCode(orderId.value, code);
    notify.success('Đã gửi mã nhận phòng cho khách');
    await load();
  } catch (e) {
    notify.apiError(e, 'Không gửi được mã nhận phòng');
  } finally {
    sendingCheckinCode.value = false;
  }
}

const costDraft = ref(0);
const savingCost = ref(false);
const previewProfit = computed(() => (order.value ? order.value.total - costDraft.value : 0));

async function saveCost() {
  if (!order.value) return;
  savingCost.value = true;
  try {
    await ordersApi.update(orderId.value, {
      cost_total: costDraft.value,
      profit_total: order.value.total - costDraft.value,
    });
    notify.success('Đã lưu giá vốn');
    await load();
  } catch (e) {
    notify.apiError(e, 'Không lưu được giá vốn');
  } finally {
    savingCost.value = false;
  }
}

// Available next-state transitions per current status (mirrors backend state machine).
const allowedTransitions = computed<Array<{ key: 'confirmed' | 'completed' | 'no_show'; label: string; icon: string; severity: string; confirmMsg: string }>>(() => {
  const status = order.value?.status;
  const opts: Array<{ key: 'confirmed' | 'completed' | 'no_show'; label: string; icon: string; severity: string; confirmMsg: string }> = [];
  if (status === 'pending') {
    opts.push({ key: 'confirmed', label: 'Xác nhận đơn', icon: 'pi pi-check', severity: 'info', confirmMsg: 'Xác nhận đơn này?' });
    opts.push({ key: 'no_show', label: 'No-show', icon: 'pi pi-user-minus', severity: 'secondary', confirmMsg: 'Đánh dấu khách không đến (no-show)?' });
  }
  if (status === 'confirmed') {
    opts.push({ key: 'completed', label: 'Hoàn tất', icon: 'pi pi-flag', severity: 'success', confirmMsg: 'Đánh dấu đơn này đã hoàn tất?' });
  }
  return opts;
});

async function doTransition(target: 'confirmed' | 'completed' | 'no_show', confirmMsg: string) {
  if (!order.value) return;
  if (!confirm(confirmMsg)) return;
  transitioning.value = target;
  try {
    await ordersApi.transition(orderId.value, target);
    notify.success('Đã cập nhật trạng thái');
    await load();
  } catch (e) {
    notify.apiError(e, 'Không đổi được trạng thái');
  } finally {
    transitioning.value = null;
  }
}
</script>

<template>
  <div v-if="loading" class="loading">
    <ProgressSpinner />
  </div>
  <div v-else-if="order">
    <PageHeader :title="order.code ?? ''" subtitle="Chi tiết đơn hàng" icon="pi pi-shopping-cart">
      <Can cap="vie_manage_orders">
        <Button
          v-for="t in allowedTransitions"
          :key="t.key"
          :label="t.label"
          :icon="t.icon"
          :severity="t.severity"
          :loading="transitioning === t.key"
          :disabled="transitioning !== null"
          @click="doTransition(t.key, t.confirmMsg)"
        />
      </Can>
      <Can :cap-any="['vie_manage_payments', 'vie_view_all_orders']">
        <Button
          label="Ghi thanh toán"
          icon="pi pi-wallet"
          severity="success"
          @click="paymentDialog = true"
          :disabled="order.status === 'cancelled'"
        />
      </Can>
      <Button
        v-if="canPrintInvoice"
        label="Xuất hoá đơn"
        icon="pi pi-file-pdf"
        severity="secondary"
        outlined
        @click="openInvoiceDialog"
      />
      <Can cap="vie_cancel_orders">
        <Button
          label="Hủy đơn"
          icon="pi pi-times"
          severity="danger"
          outlined
          :disabled="!canCancel"
          @click="openCancelDialog"
        />
      </Can>
    </PageHeader>
    <div class="header-tags">
      <StatusTag :value="order.status" />
      <StatusTag :value="order.payment_status" kind="payment" />
    </div>

    <div class="summary-grid">
      <Card>
        <template #title>Tổng quan</template>
        <template #content>
          <div class="kv"><span>Tạm tính:</span><strong>{{ formatVND(order.subtotal) }}</strong></div>
          <div class="kv"><span>Giảm giá:</span><strong>{{ formatVND(order.discount) }}</strong></div>
          <div class="kv kv-total"><span>Tổng:</span><strong>{{ formatVND(order.total) }}</strong></div>
          <div class="kv"><span>Đã thanh toán:</span><strong>{{ formatVND(order.paid_amount) }}</strong></div>
          <div class="kv kv-remaining"><span>Còn lại:</span><strong>{{ formatVND(remaining) }}</strong></div>
        </template>
      </Card>

      <Card v-if="canViewCost">
        <template #title>Giá vốn &amp; Lợi nhuận</template>
        <template #content>
          <div class="field">
            <label>Giá vốn (VND)</label>
            <InputNumber
              v-model="costDraft"
              :min="0"
              :step="10000"
              :disabled="!canManageOrders"
              show-buttons
              fluid
            />
          </div>
          <div class="kv"><span>Doanh thu:</span><strong>{{ formatVND(order.total) }}</strong></div>
          <div class="kv"><span>Giá vốn:</span><strong>{{ formatVND(costDraft) }}</strong></div>
          <div class="kv kv-total" :class="{ 'kv-negative': previewProfit < 0 }">
            <span>Lợi nhuận:</span>
            <strong>{{ formatVND(previewProfit) }}</strong>
          </div>
          <div v-if="canManageOrders" class="actions-row">
            <Button
              label="Lưu giá vốn"
              icon="pi pi-save"
              size="small"
              :loading="savingCost"
              :disabled="costDraft === order.cost_total"
              @click="saveCost"
            />
            <Button
              v-if="costDraft !== order.cost_total"
              label="Khôi phục"
              size="small"
              text
              @click="costDraft = order.cost_total"
            />
          </div>
        </template>
      </Card>

      <Card>
        <template #title>Khách hàng</template>
        <template #content>
          <div class="kv"><span>Tên:</span><strong>{{ order.customer_name }}</strong></div>
          <div class="kv"><span>SĐT:</span><strong>{{ order.customer_phone }}</strong></div>
          <div v-if="order.customer_email" class="kv"><span>Email:</span><strong>{{ order.customer_email }}</strong></div>
          <div v-if="order.customer && order.customer.booking_count > 0" class="kv">
            <span>Lịch sử:</span><strong>{{ order.customer.booking_count }} đơn</strong>
          </div>
          <template v-if="vat">
            <div class="kv"><span>VAT · Công ty:</span><strong>{{ vat.company_name || '—' }}</strong></div>
            <div class="kv"><span>VAT · MST:</span><strong>{{ vat.tax_code || '—' }}</strong></div>
            <div v-if="vat.address" class="kv"><span>VAT · Địa chỉ:</span><strong>{{ vat.address }}</strong></div>
            <div v-if="vat.email" class="kv"><span>VAT · Email:</span><strong>{{ vat.email }}</strong></div>
          </template>
        </template>
      </Card>

      <Card>
        <template #title>Lịch trình</template>
        <template #content>
          <div class="kv"><span>Check-in:</span><strong>{{ formatDate(order.checkin) }}</strong></div>
          <div class="kv"><span>Check-out:</span><strong>{{ formatDate(order.checkout) }}</strong></div>
          <div class="kv"><span>Số đêm:</span><strong>{{ order.nights }}</strong></div>
          <div class="kv"><span>Người lớn:</span><strong>{{ order.adults }}</strong></div>
          <div class="kv"><span>Trẻ em:</span><strong>{{ order.children }}</strong></div>
          <div v-if="pickupAddress" class="kv"><span>Điểm đón:</span><strong>{{ pickupAddress }}</strong></div>
          <div v-if="dropoffAddress" class="kv"><span>Điểm trả:</span><strong>{{ dropoffAddress }}</strong></div>
        </template>
      </Card>

      <Can cap="vie_manage_orders">
        <Card v-if="canSendCheckinCode">
          <template #title>Mã nhận phòng</template>
          <template #content>
            <p class="muted" style="margin: 0 0 0.75rem;">
              Mã do khách sạn cấp sau khi đơn được thanh toán. Nhập và gửi email cho khách.
            </p>
            <div v-if="order.checkin_code_sent_at" class="kv">
              <span>Đã gửi lúc:</span>
              <strong>{{ formatDateTime(order.checkin_code_sent_at) }}</strong>
            </div>
            <div v-if="!order.customer_email" class="muted" style="color: var(--p-red-600); margin-bottom: 0.5rem;">
              Đơn không có email khách — không thể gửi.
            </div>
            <div class="field">
              <label>Mã nhận phòng</label>
              <InputText
                v-model="checkinCodeInput"
                placeholder="VD: ABC123"
                :disabled="sendingCheckinCode || !order.customer_email"
                maxlength="100"
                fluid
              />
            </div>
            <div class="actions-row">
              <Button
                :label="!order.checkin_code ? 'Gửi mã cho khách' : checkinCodeDirty ? 'Cập nhật & Gửi' : 'Gửi lại mã'"
                icon="pi pi-send"
                size="small"
                :loading="sendingCheckinCode"
                :disabled="!checkinCodeInput.trim() || !order.customer_email"
                @click="sendCheckinCode"
              />
              <Button
                v-if="checkinCodeDirty && order.checkin_code"
                label="Khôi phục"
                size="small"
                text
                :disabled="sendingCheckinCode"
                @click="checkinCodeInput = order.checkin_code ?? ''"
              />
            </div>
          </template>
        </Card>
      </Can>
    </div>

    <Tabs value="items">
      <TabList>
        <Tab value="items">Sản phẩm</Tab>
        <Tab value="payments">Thanh toán</Tab>
        <Tab value="notes">Ghi chú</Tab>
      </TabList>
      <TabPanels>
      <TabPanel value="items">
        <DataTable :value="order.items" data-key="id">
          <Column field="name" header="Phòng" />
          <Column field="booking_type" header="Loại">
            <template #body="{ data }">{{ labelBookingType(data.booking_type) }}</template>
          </Column>
          <Column field="checkin" header="Check-in">
            <template #body="{ data }">{{ formatDate(data.checkin) }} → {{ formatDate(data.checkout) }}</template>
          </Column>
          <Column field="quantity" header="Số phòng" />
          <Column field="nights" header="Đêm" />
          <Column header="Người lớn">
            <template #body="{ data }">{{ data.adults }}</template>
          </Column>
          <Column header="Trẻ em">
            <template #body="{ data }">{{ data.children }}</template>
          </Column>
          <Column field="ticket_count" header="Vé" />
          <Column field="line_total" header="Tổng dòng">
            <template #body="{ data }">{{ formatVND(data.line_total) }}</template>
          </Column>
          <Column field="status" header="Trạng thái">
            <template #body="{ data }">
              <StatusTag :value="data.status" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel value="payments">
        <DataTable :value="order.payments" data-key="id" :empty-message="'Chưa có giao dịch'">
          <Column field="type" header="Loại">
            <template #body="{ data }">{{ labelPaymentType(data.type) }}</template>
          </Column>
          <Column field="amount" header="Số tiền">
            <template #body="{ data }">{{ formatVND(data.amount) }}</template>
          </Column>
          <Column field="method" header="Phương thức">
            <template #body="{ data }">{{ labelPaymentMethod(data.method) }}</template>
          </Column>
          <Column field="gateway" header="Cổng">
            <template #body="{ data }">{{ labelGateway(data.gateway) }}</template>
          </Column>
          <Column field="transaction_id" header="Mã giao dịch">
            <template #body="{ data }">{{ data.transaction_id ?? '—' }}</template>
          </Column>
          <Column field="paid_at" header="Thời gian">
            <template #body="{ data }">{{ formatDateTime(data.paid_at ?? data.created_at) }}</template>
          </Column>
          <Column field="note" header="Ghi chú" />
        </DataTable>
      </TabPanel>

      <TabPanel value="notes">
        <div v-if="order.customer_note" class="note-block">
          <h4>Khách ghi chú</h4>
          <p>{{ order.customer_note }}</p>
        </div>
        <div v-if="order.internal_note" class="note-block">
          <h4>Ghi chú nội bộ</h4>
          <p>{{ order.internal_note }}</p>
        </div>
        <div v-if="order.cancel_reason" class="note-block note-cancel">
          <h4>Lý do hủy</h4>
          <p>{{ order.cancel_reason }}</p>
        </div>
        <p v-if="!order.customer_note && !order.internal_note && !order.cancel_reason" class="muted">
          Không có ghi chú
        </p>
      </TabPanel>
      </TabPanels>
    </Tabs>

    <!-- Cancel dialog -->
    <Dialog v-model:visible="cancelDialog" header="Hủy đơn hàng" :modal="true" :style="{ width: '480px' }">
      <div class="dialog-content">
        <p>Bạn có chắc muốn hủy đơn <strong>{{ order.code }}</strong>?</p>
        <p class="muted">Đã thanh toán: <strong>{{ formatVND(order.paid_amount) }}</strong>. Nhập số tiền cần hoàn cho khách (có thể để 0).</p>
        <div class="field">
          <label>Lý do hủy <span class="required-mark">*</span></label>
          <Textarea v-model="cancelReason" rows="3" autoResize />
        </div>
        <div class="field">
          <label>Số tiền hoàn (VND)</label>
          <InputNumber v-model="cancelRefundAmount" :min="0" :max="order.paid_amount" :step="10000" show-buttons fluid />
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" severity="secondary" text @click="cancelDialog = false" />
        <Button label="Xác nhận hủy" severity="danger" :loading="cancelling" @click="doCancel" />
      </template>
    </Dialog>

    <!-- Payment dialog -->
    <Dialog v-model:visible="paymentDialog" header="Ghi nhận thanh toán" :modal="true" :style="{ width: '480px' }">
      <div class="dialog-content">
        <div class="field">
          <label>Loại</label>
          <Select
            v-model="paymentForm.type"
            :options="paymentTypeOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>
        <div class="field">
          <label>Phương thức</label>
          <Select
            v-model="paymentForm.method"
            :options="paymentMethodOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>
        <div class="field">
          <label>Số tiền (VND)</label>
          <InputNumber v-model="paymentForm.amount" :min="0" :step="10000" show-buttons fluid />
          <div class="quick-amounts">
            <Button
              v-for="q in quickAmounts"
              :key="q.label"
              :label="q.label"
              size="small"
              severity="secondary"
              outlined
              :disabled="q.value <= 0"
              @click="paymentForm.amount = q.value"
            />
          </div>
          <small v-if="paymentForm.amount > 0" class="muted">≈ {{ formatVND(paymentForm.amount) }}</small>
        </div>
        <div class="field">
          <label>Mã giao dịch (tùy chọn)</label>
          <input type="text" v-model="paymentForm.transaction_id" />
        </div>
        <div class="field">
          <label>Ghi chú</label>
          <Textarea v-model="paymentForm.note" rows="2" />
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" severity="secondary" text @click="paymentDialog = false" />
        <Button label="Lưu" :loading="saving" @click="addPayment" />
      </template>
    </Dialog>

    <InvoiceDialog
      v-if="order"
      v-model:visible="invoiceDialog"
      :order="{ id: order.id, code: order.code ?? '', invoice_number: order.invoice_number, status: order.status }"
      @assigned="onInvoiceAssigned"
    />
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.header-tags { display: flex; gap: 0.5rem; margin: -0.75rem 0 1.25rem; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kv { display: flex; justify-content: space-between; padding: 0.35rem 0; font-size: 0.9rem; }
.kv-total { border-top: 1px solid var(--p-surface-200); margin-top: 0.5rem; padding-top: 0.5rem; }
.kv-total strong { color: var(--p-primary-700); font-size: 1.1rem; }
.kv-remaining strong { color: var(--p-red-600); }
.dialog-content { display: flex; flex-direction: column; gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.field input, .field select {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--p-surface-300);
  border-radius: 0.375rem;
  font-size: 0.95rem;
}
.note-block { margin-bottom: 1rem; }
.note-block h4 { margin: 0 0 0.5rem; font-size: 0.9rem; }
.note-cancel h4 { color: var(--p-red-600); }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
.quick-amounts { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.4rem; }
.kv-negative strong { color: var(--p-red-600); }
.actions-row { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
</style>
