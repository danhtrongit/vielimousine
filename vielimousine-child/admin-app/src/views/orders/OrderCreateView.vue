<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Card from 'primevue/card';
import Steps from 'primevue/steps';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import Chips from 'primevue/chips';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore, ORDER_SOURCES } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND, formatDate, ymdLocal } from '@/composables/useFormat';
import { quoteApi, couponsApi, type PriceBreakdown } from '@/api/quote.api';
import { ordersApi } from '@/api/orders.api';
import { customersApi } from '@/api/customers.api';
import type { CustomerListItem } from '@/types/customer';

const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();

ui.setBreadcrumb([
  { label: 'Đơn hàng', to: '/orders' },
  { label: 'Tạo đơn mới' },
]);

const stepIndex = ref(0);
const steps = [
  { label: 'Khách hàng' },
  { label: 'Sản phẩm' },
  { label: 'Mã giảm giá' },
  { label: 'Thông tin' },
  { label: 'Xác nhận' },
];

const wizard = ref({
  customer: { phone: '', name: '', email: '' },
  item: {
    room_id: null as number | null,
    booking_type: 'room' as 'room' | 'combo',
    checkin: '',
    checkout: '',
    adults: 2,
    child_ages: [] as number[],
    user_rooms: 0,
  },
  couponCode: '',
  source: 'admin',
  customerNote: '',
});

const quote = ref<PriceBreakdown | null>(null);
const couponDiscount = ref(0);
const couponValid = ref<boolean | null>(null);
const couponMessages = ref<string[]>([]);
const submitting = ref(false);
const quoting = ref(false);
const idempotencyKey = ref(generateUuid());

// UUID v4 thủ công — crypto.randomUUID() chỉ work trong secure context (HTTPS/localhost),
// không work với http://vielimo.local của Local Sites.
function generateUuid(): string {
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

// --- Step 1: Customer phone autocomplete ---
const customerSearch = ref('');
const customerSuggestions = ref<CustomerListItem[]>([]);
let searchTimer: number | null = null;

watch(customerSearch, (val) => {
  if (searchTimer) window.clearTimeout(searchTimer);
  if (!val || val.length < 3) {
    customerSuggestions.value = [];
    return;
  }
  searchTimer = window.setTimeout(async () => {
    try {
      const resp = await customersApi.list({ q: val, per_page: 5 });
      customerSuggestions.value = resp.data;
    } catch { /* ignore */ }
  }, 300);
});

function pickCustomer(c: CustomerListItem) {
  wizard.value.customer.phone = c.phone;
  wizard.value.customer.name = c.name;
  wizard.value.customer.email = c.email ?? '';
  customerSuggestions.value = [];
  customerSearch.value = '';
}

// --- Step 2: Items + quote ---
const roomOptions = computed(() =>
  lookup.rooms.map((r) => ({
    label: `${r.name} — ${lookup.hotelById(r.hotel_id)?.name ?? 'Hotel ?'}`,
    value: r.id,
  }))
);

async function runQuote() {
  const it = wizard.value.item;
  if (!it.room_id || !it.checkin || !it.checkout) {
    quote.value = null;
    return;
  }
  quoting.value = true;
  try {
    const resp = await quoteApi.quote({
      room_id: it.room_id,
      checkin: toDateStr(it.checkin),
      checkout: toDateStr(it.checkout),
      adults: it.adults,
      child_ages: it.child_ages,
      user_rooms: it.user_rooms,
      booking_type: it.booking_type,
    });
    quote.value = resp.data;
    if (resp.data.requires_quote) {
      notify.warn('Liên hệ báo giá', resp.data.messages?.join(', ') ?? '');
    }
  } catch (e) {
    quote.value = null;
    notify.apiError(e, 'Không tính được giá');
  } finally {
    quoting.value = false;
  }
}

function toDateStr(v: string | Date): string {
  if (v instanceof Date) return ymdLocal(v);
  return v;
}

// --- Step 3: Coupon validate ---
async function validateCoupon() {
  if (!wizard.value.couponCode.trim() || !quote.value) return;
  try {
    const resp = await couponsApi.validate({
      code: wizard.value.couponCode.trim(),
      order_subtotal: quote.value.subtotal,
      room_id: wizard.value.item.room_id ?? undefined,
      booking_type: wizard.value.item.booking_type,
    });
    couponValid.value = resp.data.valid;
    couponDiscount.value = resp.data.discount;
    couponMessages.value = resp.data.messages;
  } catch (e) {
    couponValid.value = false;
    couponDiscount.value = 0;
    notify.apiError(e, 'Lỗi kiểm tra mã');
  }
}

// --- Wizard step transitions ---
function nextStep() {
  if (stepIndex.value === 0) {
    if (!wizard.value.customer.phone || !wizard.value.customer.name) {
      notify.warn('Vui lòng nhập SĐT và tên khách hàng');
      return;
    }
  }
  if (stepIndex.value === 1) {
    if (!quote.value || quote.value.requires_quote) {
      notify.warn('Vui lòng chọn phòng và tính giá hợp lệ');
      return;
    }
  }
  stepIndex.value++;
}

function prevStep() {
  if (stepIndex.value > 0) stepIndex.value--;
}

const totalAfterCoupon = computed(() => {
  if (!quote.value) return 0;
  return Math.max(0, quote.value.subtotal - couponDiscount.value);
});

// --- Step 5: Submit ---
async function submit() {
  if (!quote.value) return;
  submitting.value = true;
  try {
    const body = {
      customer: {
        phone: wizard.value.customer.phone,
        name: wizard.value.customer.name,
        email: wizard.value.customer.email || null,
      },
      source: wizard.value.source,
      customer_note: wizard.value.customerNote || null,
      items: [{
        room_id: wizard.value.item.room_id,
        booking_type: wizard.value.item.booking_type,
        checkin: toDateStr(wizard.value.item.checkin),
        checkout: toDateStr(wizard.value.item.checkout),
        adults: wizard.value.item.adults,
        child_ages: wizard.value.item.child_ages,
        user_rooms: wizard.value.item.user_rooms,
      }],
      coupon_code: wizard.value.couponCode.trim() || null,
    };
    const resp = await ordersApi.create(body, idempotencyKey.value);
    notify.success('Đã tạo đơn', resp.data.code);
    router.push(`/orders/${resp.data.id}`);
  } catch (e) {
    notify.apiError(e, 'Không tạo được đơn');
  } finally {
    submitting.value = false;
  }
}

onMounted(() => { lookup.ensureLoaded(); });
</script>

<template>
  <div>
    <PageHeader title="Tạo đơn mới" subtitle="Wizard nhập đơn" icon="pi pi-plus-circle" />

    <Steps :model="steps" :active-step="stepIndex" :readonly="true" class="wizard-steps" />

    <Card class="wizard-card">
      <template #content>

        <!-- Step 0: Customer -->
        <div v-if="stepIndex === 0" class="step-content">
          <h3>Thông tin khách hàng</h3>
          <div class="field">
            <label>Tìm khách (SĐT hoặc tên)</label>
            <InputText v-model="customerSearch" placeholder="Gõ 3+ ký tự để tìm" />
            <div v-if="customerSuggestions.length > 0" class="suggestions">
              <div v-for="c in customerSuggestions" :key="c.id" class="suggestion-item" @click="pickCustomer(c)">
                <strong>{{ c.name }}</strong> — {{ c.phone }}
                <small v-if="c.email">{{ c.email }}</small>
                <small> · {{ c.booking_count }} đơn</small>
              </div>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label>Số điện thoại <span class="required-mark">*</span></label>
              <InputText v-model="wizard.customer.phone" />
            </div>
            <div class="field">
              <label>Họ tên <span class="required-mark">*</span></label>
              <InputText v-model="wizard.customer.name" />
            </div>
            <div class="field grid-span-2">
              <label>Email</label>
              <InputText v-model="wizard.customer.email" />
            </div>
          </div>
        </div>

        <!-- Step 1: Item + Quote -->
        <div v-else-if="stepIndex === 1" class="step-content">
          <h3>Chọn phòng & ngày</h3>
          <div class="grid-2">
            <div class="field">
              <label>Phòng</label>
              <Select
                v-model="wizard.item.room_id"
                :options="roomOptions"
                option-label="label"
                option-value="value"
                filter
                placeholder="Chọn phòng"
                @change="runQuote"
              />
            </div>
            <div class="field">
              <label>Loại đặt</label>
              <Select
                v-model="wizard.item.booking_type"
                :options="[{label:'Phòng', value:'room'},{label:'Combo (phòng + vé)', value:'combo'}]"
                option-label="label"
                option-value="value"
                @change="runQuote"
              />
            </div>
            <div class="field">
              <label>Check-in</label>
              <DatePicker
                :model-value="wizard.item.checkin ? new Date(wizard.item.checkin) : null"
                @update:model-value="(v: any) => { wizard.item.checkin = v instanceof Date ? ymdLocal(v) : ''; runQuote(); }"
                date-format="yy-mm-dd" show-icon
              />
            </div>
            <div class="field">
              <label>Check-out</label>
              <DatePicker
                :model-value="wizard.item.checkout ? new Date(wizard.item.checkout) : null"
                @update:model-value="(v: any) => { wizard.item.checkout = v instanceof Date ? ymdLocal(v) : ''; runQuote(); }"
                date-format="yy-mm-dd" show-icon
              />
            </div>
            <div class="field">
              <label>Số người lớn</label>
              <InputNumber v-model="wizard.item.adults" :min="1" :max="20" show-buttons @input="runQuote" />
            </div>
            <div class="field">
              <label>Tuổi các bé</label>
              <Chips v-model="wizard.item.child_ages" separator="," @add="runQuote" @remove="runQuote" />
              <small class="muted">Nhập từng tuổi rồi Enter (vd: 5, 8)</small>
            </div>
            <div class="field">
              <label>Số phòng (0 = auto)</label>
              <InputNumber v-model="wizard.item.user_rooms" :min="0" :max="10" show-buttons @input="runQuote" />
            </div>
          </div>

          <div v-if="quoting" class="quote-loading"><ProgressSpinner style="width: 24px;height: 24px" /></div>

          <div v-else-if="quote" class="quote-panel">
            <h4>Bảng tính giá</h4>
            <Message v-if="quote.requires_quote" severity="warn" :closable="false">
              {{ quote.messages.join('. ') }}
            </Message>
            <div class="quote-grid">
              <div><span>Số phòng:</span> <strong>{{ quote.num_rooms }}</strong></div>
              <div><span>Số đêm:</span> <strong>{{ quote.nights }}</strong></div>
              <div><span>Người lớn quy đổi:</span> <strong>{{ quote.effective_adults }}</strong></div>
              <div><span>Trẻ em quy đổi:</span> <strong>{{ quote.effective_children }}</strong></div>
              <div v-if="wizard.item.booking_type === 'combo'">
                <span>Số vé tính phí / Tổng ghế:</span>
                <strong>{{ quote.billable_seats }} / {{ quote.seat_count }}</strong>
                (miễn {{ quote.free_child_seats }})
              </div>
              <div><span>Tiền phòng:</span> <strong>{{ formatVND(quote.room_subtotal) }}</strong></div>
              <div v-if="quote.extra_adult_subtotal > 0"><span>Giường phụ:</span> <strong>{{ formatVND(quote.extra_adult_subtotal) }}</strong></div>
              <div v-if="quote.child_surcharge_total > 0"><span>Phụ thu bé:</span> <strong>{{ formatVND(quote.child_surcharge_total) }}</strong></div>
              <div v-if="quote.ticket_subtotal > 0"><span>Vé xe:</span> <strong>{{ formatVND(quote.ticket_subtotal) }}</strong></div>
              <div class="total"><span>Tổng tạm tính:</span> <strong>{{ formatVND(quote.total) }}</strong></div>
            </div>
            <div v-if="quote.messages.length > 0" class="quote-messages">
              <i class="pi pi-info-circle" />
              <ul><li v-for="(m, i) in quote.messages" :key="i">{{ m }}</li></ul>
            </div>
          </div>
        </div>

        <!-- Step 2: Coupon -->
        <div v-else-if="stepIndex === 2" class="step-content">
          <h3>Mã giảm giá (tùy chọn)</h3>
          <div class="grid-2">
            <div class="field">
              <label>Mã</label>
              <InputText v-model="wizard.couponCode" placeholder="Vd: SUMMER10" />
            </div>
            <div class="field" style="display: flex; align-items: flex-end">
              <Button label="Kiểm tra" icon="pi pi-check" @click="validateCoupon" :disabled="!wizard.couponCode.trim()" />
            </div>
          </div>
          <Message v-if="couponValid === true" severity="success" :closable="false">
            Giảm giá: <strong>{{ formatVND(couponDiscount) }}</strong>
          </Message>
          <Message v-else-if="couponValid === false" severity="error" :closable="false">
            {{ couponMessages.join('. ') }}
          </Message>
          <p class="muted">Bỏ qua bước này nếu không có mã.</p>
        </div>

        <!-- Step 3: Source + note -->
        <div v-else-if="stepIndex === 3" class="step-content">
          <h3>Nguồn & ghi chú</h3>
          <div class="grid-2">
            <div class="field">
              <label>Nguồn đơn</label>
              <Select
                v-model="wizard.source"
                :options="ORDER_SOURCES"
                option-label="label"
                option-value="value"
              />
            </div>
            <div class="field grid-span-2">
              <label>Ghi chú</label>
              <Textarea v-model="wizard.customerNote" rows="3" />
            </div>
          </div>
        </div>

        <!-- Step 4: Confirm -->
        <div v-else-if="stepIndex === 4" class="step-content">
          <h3>Xác nhận đơn</h3>
          <Card>
            <template #content>
              <div class="confirm-kv"><span>Khách:</span> <strong>{{ wizard.customer.name }}</strong> ({{ wizard.customer.phone }})</div>
              <div class="confirm-kv">
                <span>Phòng:</span>
                <strong>{{ roomOptions.find(o => o.value === wizard.item.room_id)?.label }}</strong>
              </div>
              <div class="confirm-kv"><span>Ngày:</span> {{ formatDate(toDateStr(wizard.item.checkin)) }} → {{ formatDate(toDateStr(wizard.item.checkout)) }}</div>
              <div class="confirm-kv">
                <span>Khách:</span>
                <strong>{{ wizard.item.adults }} người lớn, {{ wizard.item.child_ages.length }} trẻ em</strong>
              </div>
              <div class="confirm-kv"><span>Loại đặt:</span> {{ wizard.item.booking_type === 'combo' ? 'Combo (phòng + vé)' : 'Phòng' }}</div>
              <div class="confirm-kv"><span>Tạm tính:</span> {{ formatVND(quote?.subtotal ?? 0) }}</div>
              <div v-if="couponDiscount > 0" class="confirm-kv">
                <span>Giảm giá ({{ wizard.couponCode }}):</span> <strong class="discount-amount">-{{ formatVND(couponDiscount) }}</strong>
              </div>
              <div class="confirm-kv confirm-total">
                <span>Tổng:</span> <strong>{{ formatVND(totalAfterCoupon) }}</strong>
              </div>
              <div class="confirm-kv"><span>Nguồn:</span> {{ wizard.source }}</div>
              <div v-if="wizard.customerNote" class="confirm-kv"><span>Note:</span> {{ wizard.customerNote }}</div>
            </template>
          </Card>
        </div>

      </template>

      <template #footer>
        <div class="wizard-footer">
          <Button label="Quay lại" severity="secondary" outlined :disabled="stepIndex === 0 || submitting" @click="prevStep" />
          <Button
            v-if="stepIndex < steps.length - 1"
            label="Tiếp"
            icon="pi pi-arrow-right"
            icon-pos="right"
            @click="nextStep"
          />
          <Button
            v-else
            label="Tạo đơn"
            icon="pi pi-check"
            :loading="submitting"
            severity="success"
            @click="submit"
          />
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.wizard-steps { margin-bottom: 1.5rem; }
.discount-amount { color: var(--p-red-600); }
.wizard-card :deep(.p-card-content) { padding: 0; }
.step-content { padding: 1.5rem; min-height: 320px; }
.step-content h3 { margin: 0 0 1.25rem; font-size: 1.1rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.suggestions { background: var(--p-surface-50); border: 1px solid var(--p-surface-200); border-radius: 0.375rem; margin-top: 0.25rem; max-height: 200px; overflow: auto; }
.suggestion-item { padding: 0.5rem 0.75rem; cursor: pointer; }
.suggestion-item:hover { background: var(--p-primary-50); }
.suggestion-item small { color: var(--p-text-muted-color); margin-left: 0.5rem; }
.quote-loading { padding: 1rem; text-align: center; }
.quote-panel { margin-top: 1.5rem; padding: 1rem; background: var(--p-surface-50); border-radius: 0.5rem; }
.quote-panel h4 { margin: 0 0 0.75rem; }
.quote-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem 1.5rem; font-size: 0.9rem; }
.quote-grid .total { grid-column: span 2; border-top: 1px solid var(--p-surface-200); padding-top: 0.5rem; margin-top: 0.5rem; }
.quote-grid .total strong { font-size: 1.1rem; color: var(--p-primary-700); }
.quote-messages { display: flex; gap: 0.5rem; margin-top: 0.75rem; color: var(--p-text-muted-color); font-size: 0.85rem; }
.quote-messages ul { margin: 0; padding-left: 1rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin-top: 0.5rem; }
.confirm-kv { padding: 0.4rem 0; display: flex; justify-content: space-between; border-bottom: 1px dashed var(--p-surface-200); }
.confirm-kv:last-child { border-bottom: 0; }
.confirm-total { border-top: 2px solid var(--p-surface-300); padding-top: 0.75rem; margin-top: 0.5rem; font-size: 1.05rem; }
.confirm-total strong { color: var(--p-primary-700); font-size: 1.2rem; }
.wizard-footer { display: flex; justify-content: space-between; padding: 1rem 1.5rem; background: var(--p-surface-50); border-top: 1px solid var(--p-surface-200); }
</style>
