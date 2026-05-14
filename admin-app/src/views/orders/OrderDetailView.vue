<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import ProgressSpinner from 'primevue/progressspinner';
import StatusTag from '@/components/StatusTag.vue';
import Can from '@/components/Can.vue';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { useUIStore } from '@/stores/ui.store';
import { labelBookingType, labelPaymentMethod, labelPaymentType, labelGateway } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND, formatDate, formatDateTime } from '@/composables/useFormat';
import type { OrderDetail } from '@/types/order';

const route = useRoute();
const ui = useUIStore();
const notify = useNotify();

const order = ref<OrderDetail | null>(null);
const loading = ref(true);
const orderId = computed(() => Number(route.params.id));

const cancelDialog = ref(false);
const cancelReason = ref('');
const cancelling = ref(false);

const paymentDialog = ref(false);
const paymentForm = ref({ type: 'payment', amount: 0, method: 'bank_transfer', note: '', transaction_id: '' });
const saving = ref(false);

async function load() {
  loading.value = true;
  try {
    const resp = await ordersApi.get(orderId.value);
    order.value = resp.data;
    ui.setBreadcrumb([
      { label: 'Đơn hàng', to: '/orders' },
      { label: order.value.code },
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
  cancelling.value = true;
  try {
    await ordersApi.cancel(orderId.value, cancelReason.value.trim());
    notify.success('Đã hủy đơn');
    cancelDialog.value = false;
    cancelReason.value = '';
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
</script>

<template>
  <div v-if="loading" class="loading">
    <ProgressSpinner />
  </div>
  <div v-else-if="order">
    <div class="header">
      <div>
        <h1 class="page-title">{{ order.code }}</h1>
        <div class="header-tags">
          <StatusTag :value="order.status" />
          <StatusTag :value="order.payment_status" kind="payment" />
        </div>
      </div>
      <div class="header-actions">
        <Can :cap-any="['vie_manage_payments', 'vie_view_all_orders']">
          <Button
            label="Ghi thanh toán"
            icon="pi pi-wallet"
            severity="success"
            @click="paymentDialog = true"
            :disabled="order.status === 'cancelled'"
          />
        </Can>
        <Can cap="vie_cancel_orders">
          <Button
            label="Hủy đơn"
            icon="pi pi-times"
            severity="danger"
            outlined
            :disabled="!canCancel"
            @click="cancelDialog = true"
          />
        </Can>
      </div>
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

      <Card>
        <template #title>Khách hàng</template>
        <template #content>
          <div class="kv"><span>Tên:</span><strong>{{ order.customer_name }}</strong></div>
          <div class="kv"><span>SĐT:</span><strong>{{ order.customer_phone }}</strong></div>
          <div v-if="order.customer_email" class="kv"><span>Email:</span><strong>{{ order.customer_email }}</strong></div>
          <div v-if="order.customer && order.customer.booking_count > 0" class="kv">
            <span>Lịch sử:</span><strong>{{ order.customer.booking_count }} đơn</strong>
          </div>
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
        </template>
      </Card>
    </div>

    <TabView>
      <TabPanel header="Sản phẩm" value="items">
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

      <TabPanel header="Thanh toán" value="payments">
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

      <TabPanel header="Ghi chú" value="notes">
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
    </TabView>

    <!-- Cancel dialog -->
    <Dialog v-model:visible="cancelDialog" header="Hủy đơn hàng" :modal="true" :style="{ width: '480px' }">
      <div class="dialog-content">
        <p>Bạn có chắc muốn hủy đơn <strong>{{ order.code }}</strong>?</p>
        <p class="muted">Hệ thống sẽ tính refund dựa trên chính sách hủy của khách sạn.</p>
        <div class="field">
          <label>Lý do hủy <span style="color: red">*</span></label>
          <Textarea v-model="cancelReason" rows="3" autoResize />
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
          <select v-model="paymentForm.type">
            <option value="deposit">Đặt cọc</option>
            <option value="payment">Thanh toán</option>
            <option value="refund">Hoàn tiền</option>
            <option value="void">Hủy</option>
          </select>
        </div>
        <div class="field">
          <label>Phương thức</label>
          <select v-model="paymentForm.method">
            <option value="bank_transfer">Chuyển khoản</option>
            <option value="cash">Tiền mặt</option>
            <option value="sepay">SePay</option>
            <option value="manual">Khác</option>
          </select>
        </div>
        <div class="field">
          <label>Số tiền (VND)</label>
          <input type="number" v-model.number="paymentForm.amount" min="0" />
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
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.header-tags { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
.header-actions { display: flex; gap: 0.5rem; }
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
</style>
