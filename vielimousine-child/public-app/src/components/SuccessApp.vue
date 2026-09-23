<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import { api } from '@/api/client';
import type { OrderCheckoutResponse, OrderLookup, TransferInstructions } from '@/api/types';
import { fbTrack } from '@/composables/useFbPixel';
import { formatVND, formatDateVN } from '@/composables/useFormat';

const params = new URLSearchParams(window.location.search);
const code = params.get('code') || '';
const phone = params.get('phone') || '';

const order = ref<OrderLookup | null>(null);
const transfer = ref<TransferInstructions | null>(null);
const copiedField = ref('');
const error = ref('');
const refreshError = ref('');
const refreshing = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollCount = 0;
let lookupInFlight: Promise<OrderLookup | null> | null = null;
const MAX_POLLS = 15;

// Meta Pixel: chỉ fire Purchase khi đơn ĐÃ thanh toán (paid), 1 lần/mã đơn.
// (InitiateCheckout đã fire lúc bắt đầu thanh toán ở luồng đặt phòng.)
function firePurchaseIfPaid(o: OrderLookup): void {
  if (o.payment_status !== 'paid') return;
  const items = o.items || [];
  const isCombo = items.some((it) => it.booking_type === 'combo');
  const first = items[0];
  const contentName = first
    ? [first.hotel_name, first.room_name || first.name].filter(Boolean).join(' — ')
    : o.code;
  fbTrack('Purchase', {
    value: o.total,
    currency: 'VND',
    content_type: isCombo ? 'combo' : 'room',
    content_name: contentName,
    content_ids: [o.code],
    num_items: items.length || 1,
  }, { dedupKey: `vie_fb_purchase_${o.code}` });
}

function normalizeTransfer(value: OrderLookup['bank_transfer'], source: OrderLookup): TransferInstructions | null {
  if (!value) return null;
  return {
    bank_name: value.bank_name || '',
    bank_code: value.bank_code || null,
    bank_account: value.bank_account || '',
    bank_holder: value.bank_holder || null,
    amount: Number(value.amount ?? Math.max(0, source.total - source.paid_amount)),
    memo: value.memo || source.code,
    qr_url: value.qr_url || null,
    currency: value.currency || 'VND',
  };
}

async function copyTransferField(field: string, value: string | number): Promise<void> {
  const text = String(value ?? '').trim();
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    copiedField.value = field;
    window.setTimeout(() => { if (copiedField.value === field) copiedField.value = ''; }, 1800);
  } catch {
    copiedField.value = '';
  }
}

async function fetchOnce() {
  if (lookupInFlight) return lookupInFlight;

  if (!code || !phone) {
    const message = 'Thiếu mã đơn hoặc số điện thoại. Vui lòng kiểm tra email.';
    if (order.value) refreshError.value = message;
    else error.value = message;
    return null;
  }

  lookupInFlight = (async () => {
    try {
      const data = await api.get<OrderLookup>('orders/lookup', { code, phone });
      order.value = data;
      transfer.value = normalizeTransfer(data.bank_transfer, data);
      error.value = '';
      refreshError.value = '';
      firePurchaseIfPaid(data);
      return data;
    } catch (e: any) {
      const message = e?.errors?.[0]?.message || 'Không tìm thấy đơn';
      // Keep an already loaded order visible when a refresh/poll is temporary
      // (including the API's short rate-limit response).
      if (order.value) refreshError.value = message;
      else error.value = message;
      return null;
    } finally {
      lookupInFlight = null;
    }
  })();

  return lookupInFlight;
}

async function refresh() {
  refreshing.value = true;
  if (order.value) refreshError.value = '';
  try { await fetchOnce(); } finally { refreshing.value = false; }
}

const paying = ref(false);
const payError = ref('');
async function payNow() {
  payError.value = '';
  paying.value = true;
  try {
    const res = await api.post<OrderCheckoutResponse>('public/orders/checkout', { code, phone });
    if (!res?.transfer) {
      payError.value = 'Chưa lấy được thông tin chuyển khoản. Vui lòng thử lại sau.';
      return;
    }
    transfer.value = res.transfer;
  } catch (e: any) {
    payError.value = e?.errors?.[0]?.message || 'Không lấy được thông tin chuyển khoản';
  } finally {
    paying.value = false;
  }
}

const statusLabel = computed(() => ({
  pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', paid: 'Đã thanh toán',
  cancelled: 'Đã hủy', completed: 'Hoàn thành', no_show: 'Không đến',
} as Record<string, string>)[order.value?.status || ''] || order.value?.status || '');

const paymentLabel = computed(() => ({
  pending: 'Chờ thanh toán', partial: 'Thanh toán 1 phần',
  paid: 'Đã thanh toán', refunded: 'Đã hoàn tiền',
} as Record<string, string>)[order.value?.payment_status || ''] || order.value?.payment_status || '');

/** Tiêu đề + banner đi theo trạng thái THẬT của đơn — không mặc định "thành công". */
const heading = computed(() => {
  const o = order.value;
  if (!o) return 'Thông tin đơn đặt phòng';
  if (o.status === 'cancelled') return 'Đơn đã bị hủy';
  if (o.payment_status === 'paid') return 'Đặt phòng thành công';
  return 'Đã nhận đơn — chờ thanh toán';
});

const banner = computed(() => {
  if (!order.value) return null;
  const ps = order.value.payment_status;
  if (ps === 'paid') {
    return {
      cls: 'vh-success-banner-ok',
      icon: 'pi-check-circle',
      text: `Thanh toán thành công. Cảm ơn ${order.value.customer_name || 'Quý khách'}!`,
    };
  }
  if (order.value.status === 'cancelled') {
    return { cls: 'vh-success-banner-err', icon: 'pi-times-circle', text: 'Đơn đã bị hủy.' };
  }
  if (ps === 'pending' || ps === 'partial') {
    return {
      cls: 'vh-success-banner-warn',
      icon: 'pi-clock',
      text: 'Đơn đã được ghi nhận, đang chờ thanh toán. Trang này sẽ tự cập nhật khi có kết quả.',
    };
  }
  return { cls: 'vh-success-banner-ok', icon: 'pi-check-circle', text: 'Đặt phòng thành công!' };
});

const remaining = computed(() => {
  if (!order.value) return 0;
  return Math.max(0, order.value.total - order.value.paid_amount);
});

const pickupAddr = computed(() => order.value?.pickup?.address || '');
const dropoffAddr = computed(() => order.value?.dropoff?.address || '');
const vat = computed(() => {
  const v = order.value?.customer_vat;
  return v && (v.company_name || v.tax_code) ? v : null;
});
const paymentBlocked = (o: OrderLookup): boolean => ['cancelled', 'no_show', 'draft'].includes(o.status);
const canPay = computed(() => !!order.value && !paymentBlocked(order.value) && order.value.payment_status !== 'paid');
const isPendingOrder = (o: OrderLookup): boolean =>
  !paymentBlocked(o) && (o.payment_status === 'pending' || o.payment_status === 'partial' || o.status === 'pending');

onMounted(async () => {
  await fetchOnce();
  if (order.value && isPendingOrder(order.value)) {
    pollTimer = setInterval(async () => {
      pollCount++;
      const next = await fetchOnce();
      // A temporary lookup failure should not stop polling or hide the order.
      if (pollCount >= MAX_POLLS) { if (pollTimer) clearInterval(pollTimer); return; }
      if (!next) return;
      if (!isPendingOrder(next)) {
        if (pollTimer) clearInterval(pollTimer);
      }
    }, 8000);
  }
});

onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });
</script>

<template>
  <div class="vh-page">
    <h1>{{ heading }}</h1>

    <div v-if="error" class="vh-error">{{ error }}</div>
    <div v-if="error && !order" class="vh-success-actions">
      <button type="button" class="vh-btn vh-btn-secondary" :disabled="refreshing" @click="refresh">
        <i :class="['pi', refreshing ? 'pi-spin pi-spinner' : 'pi-refresh']" aria-hidden="true" />
        {{ refreshing ? 'Đang kiểm tra…' : 'Thử lại' }}
      </button>
    </div>

    <div v-else-if="!order" class="vh-empty">
      <p>Đang tải thông tin đơn hàng…</p>
    </div>

    <template v-else>
      <div v-if="refreshError" class="vh-error">{{ refreshError }}</div>
      <div :class="['vh-success-banner', banner?.cls]" role="status">
        <i :class="['pi', banner?.icon]" aria-hidden="true" />
        <span>{{ banner?.text }}</span>
      </div>

      <div class="vh-success-card">
        <div class="vh-success-head">
          <div>
            <div class="vh-muted">Mã đơn</div>
            <h2 class="vh-success-code">{{ order.code }}</h2>
          </div>
          <div class="vh-success-status">
            <span class="vh-tag">{{ statusLabel }}</span>
            <span class="vh-tag" :class="{
              'vh-tag-ok': order.payment_status === 'paid',
              'vh-tag-warn': order.payment_status === 'pending',
            }">{{ paymentLabel }}</span>
          </div>
        </div>

        <ul class="vie-public__order-items">
          <li v-for="it in order.items" :key="it.id" class="vie-public__order-item">
            <div><strong>{{ it.room_name || it.name }}</strong><span v-if="it.hotel_name"> — {{ it.hotel_name }}</span></div>
            <div class="vh-muted">
              {{ formatDateVN(it.checkin) }} → {{ formatDateVN(it.checkout) }}
              · {{ it.adults }} người lớn<span v-if="it.children">, {{ it.children }} trẻ em</span>
            </div>
            <div v-if="it.booking_type === 'combo' && (it.billable_seats ?? 0) > 0" class="vh-muted">
              <i class="pi pi-ticket" /> {{ it.billable_seats }} vé khứ hồi<span v-if="(it.free_child_seats ?? 0) > 0"> (miễn {{ it.free_child_seats }} bé)</span>
            </div>
            <div class="vh-muted">{{ formatVND(it.line_total) }}</div>
          </li>
        </ul>

        <div v-if="pickupAddr || dropoffAddr" class="vh-success-extra">
          <div v-if="pickupAddr"><span class="vh-muted">Điểm đón:</span> <strong>{{ pickupAddr }}</strong></div>
          <div v-if="dropoffAddr"><span class="vh-muted">Điểm trả:</span> <strong>{{ dropoffAddr }}</strong></div>
        </div>

        <div v-if="vat" class="vh-success-extra">
          <div><span class="vh-muted">Hóa đơn VAT:</span> <strong>{{ vat.company_name }}</strong></div>
          <div v-if="vat.tax_code"><span class="vh-muted">MST:</span> <strong>{{ vat.tax_code }}</strong></div>
        </div>

        <div class="vh-success-totals">
          <div><span>Tổng cộng</span><strong>{{ formatVND(order.total) }}</strong></div>
          <div><span>Đã thanh toán</span><strong>{{ formatVND(order.paid_amount) }}</strong></div>
          <div v-if="remaining > 0" class="vh-line-warn"><span>Còn lại</span><strong>{{ formatVND(remaining) }}</strong></div>
        </div>

        <div v-if="transfer && remaining > 0 && !paymentBlocked(order)" class="vh-success-extra vh-bank-box" aria-live="polite">
          <div><strong>Chuyển khoản ngân hàng</strong> <span class="vh-muted">— hệ thống sẽ tự cập nhật sau khi nhận được giao dịch</span></div>
          <div v-if="transfer.qr_url" class="vh-bank-qr">
            <img :src="transfer.qr_url" alt="Mã QR chuyển khoản" loading="lazy" />
          </div>
          <div v-if="transfer.bank_name"><span class="vh-muted">Ngân hàng:</span> <strong>{{ transfer.bank_name }}</strong></div>
          <div v-if="transfer.bank_code"><span class="vh-muted">Mã ngân hàng:</span> <strong>{{ transfer.bank_code }}</strong></div>
          <div class="vh-bank-copy-row"><span class="vh-muted">Số tài khoản:</span> <strong>{{ transfer.bank_account }}</strong> <button type="button" class="vh-copy-btn" @click="copyTransferField('account', transfer.bank_account)">{{ copiedField === 'account' ? 'Đã sao chép' : 'Sao chép' }}</button></div>
          <div v-if="transfer.bank_holder"><span class="vh-muted">Chủ tài khoản:</span> <strong>{{ transfer.bank_holder }}</strong></div>
          <div class="vh-bank-copy-row"><span class="vh-muted">Số tiền:</span> <strong>{{ formatVND(transfer.amount || remaining) }}</strong> <button type="button" class="vh-copy-btn" @click="copyTransferField('amount', transfer.amount || remaining)">{{ copiedField === 'amount' ? 'Đã sao chép' : 'Sao chép' }}</button></div>
          <div class="vh-bank-copy-row"><span class="vh-muted">Nội dung CK:</span> <strong>{{ transfer.memo }}</strong> <button type="button" class="vh-copy-btn" @click="copyTransferField('memo', transfer.memo)">{{ copiedField === 'memo' ? 'Đã sao chép' : 'Sao chép' }}</button></div>
        </div>

        <div v-if="payError" class="vh-error">{{ payError }}</div>

        <p class="vh-muted">
          Email xác nhận đã gửi đến
          <strong v-if="order.customer_email">{{ order.customer_email }}</strong>
          <span v-else>số điện thoại của bạn</span>.
          Vui lòng kiểm tra hộp thư (cả Spam).
        </p>

        <div v-if="canPay" class="vh-success-actions">
          <button type="button" class="vh-btn vh-btn-primary" :disabled="paying" @click="payNow">
            <i :class="['pi', paying ? 'pi-spin pi-spinner' : 'pi-credit-card']" aria-hidden="true" />
            {{ paying ? 'Đang lấy thông tin…' : (transfer ? 'Hiện lại thông tin chuyển khoản' : 'Lấy thông tin chuyển khoản') }}
          </button>
          <button type="button" class="vh-btn vh-btn-secondary" :disabled="refreshing" @click="refresh">
            <i :class="['pi', refreshing ? 'pi-spin pi-spinner' : 'pi-refresh']" aria-hidden="true" />
            {{ refreshing ? 'Đang kiểm tra…' : 'Kiểm tra trạng thái' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
