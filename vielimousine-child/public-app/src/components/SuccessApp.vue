<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import { api } from '@/api/client';
import type { OrderLookup, CheckoutForm } from '@/api/types';
import { submitCheckoutForm } from '@/composables/useCheckout';
import { formatVND, formatDateVN } from '@/composables/useFormat';

const params = new URLSearchParams(window.location.search);
const code = params.get('code') || '';
const phone = params.get('phone') || '';

const order = ref<OrderLookup | null>(null);
const error = ref('');
const refreshing = ref(false);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollCount = 0;
const MAX_POLLS = 15;

async function fetchOnce() {
  if (!code || !phone) {
    error.value = 'Thiếu mã đơn hoặc số điện thoại. Vui lòng kiểm tra email.';
    return null;
  }
  try {
    const data = await api.get<OrderLookup>('orders/lookup', { code, phone });
    order.value = data;
    error.value = '';
    return data;
  } catch (e: any) {
    error.value = e?.errors?.[0]?.message || 'Không tìm thấy đơn';
    return null;
  }
}

async function refresh() {
  refreshing.value = true;
  try { await fetchOnce(); } finally { refreshing.value = false; }
}

const paying = ref(false);
const payError = ref('');
async function payNow() {
  payError.value = '';
  paying.value = true;
  try {
    const res = await api.post<{ checkout: CheckoutForm | null }>('public/orders/checkout', { code, phone });
    if (res.checkout) {
      submitCheckoutForm(res.checkout); // điều hướng sang SePay (POST form)
    } else {
      payError.value = 'Cổng thanh toán chưa sẵn sàng. Vui lòng thử lại sau.';
      paying.value = false;
    }
  } catch (e: any) {
    payError.value = e?.errors?.[0]?.message || 'Không tạo được phiên thanh toán';
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
  if (ps === 'pending') {
    return {
      cls: 'vh-success-banner-warn',
      icon: 'pi-clock',
      text: 'Đang chờ thanh toán. Trang này sẽ tự cập nhật khi có kết quả.',
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
const canPay = computed(() => !!order.value && order.value.status !== 'cancelled' && order.value.payment_status !== 'paid');

onMounted(async () => {
  await fetchOnce();
  if (order.value && (order.value.payment_status === 'pending' || order.value.status === 'pending')) {
    pollTimer = setInterval(async () => {
      pollCount++;
      const next = await fetchOnce();
      if (!next || pollCount >= MAX_POLLS) { if (pollTimer) clearInterval(pollTimer); return; }
      if (next.payment_status !== 'pending' && next.status !== 'pending') {
        if (pollTimer) clearInterval(pollTimer);
      }
    }, 8000);
  }
});

onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });
</script>

<template>
  <div class="vh-page">
    <h1>Cảm ơn bạn đã đặt phòng</h1>

    <div v-if="error" class="vh-error">{{ error }}</div>

    <div v-else-if="!order" class="vh-empty">
      <p>Đang tải thông tin đơn hàng…</p>
    </div>

    <template v-else>
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
            {{ paying ? 'Đang chuyển tới cổng thanh toán…' : 'Thanh toán ngay' }}
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
