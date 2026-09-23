<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { ApiError } from '@/api/client';
import vieLogo from '@/assets/logo-vie.png';
import {
  createBookingQuoteCheckout,
  getPublicBookingQuote,
  type BookingQuoteTransfer,
  type PublicBookingQuote,
} from '@/api/bookingQuote';

const props = defineProps<{ publicId: string }>();
const homeUrl = (window.VieRest as { homeUrl?: string } | undefined)?.homeUrl || '/';

const quote = ref<PublicBookingQuote | null>(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const errorKind = ref<'missing' | 'not-found' | 'rate-limit' | 'network' | ''>('');
const refreshError = ref('');
const paying = ref(false);
const payError = ref('');
const transfer = ref<BookingQuoteTransfer | null>(null);
const copiedField = ref('');
const imageFailed = ref(false);

let loadController: AbortController | null = null;
let checkoutController: AbortController | null = null;
let loadInFlight: Promise<PublicBookingQuote | null> | null = null;
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let pollCount = 0;
let disposed = false;
const MAX_POLLS = 36;
const statusNoticeEl = ref<HTMLElement | null>(null);

const moneyFormatter = new Intl.NumberFormat('vi-VN', {
  style: 'currency',
  currency: 'VND',
  maximumFractionDigits: 0,
});

function formatMoney(value: number | null | undefined): string {
  return moneyFormatter.format(Number.isFinite(Number(value)) ? Number(value) : 0);
}

function formatTripDate(value: string | null): string {
  if (!value) return '';
  const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (dateOnly) return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1]}`;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('vi-VN').format(date);
}

function formatExpiry(value: string | null): string {
  if (!value) return 'Không giới hạn';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('vi-VN', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date);
}

function messageForError(cause: unknown): { message: string; kind: typeof errorKind.value } {
  if (cause instanceof ApiError) {
    if (cause.status === 404) {
      return {
        message: 'Báo giá này không tồn tại hoặc không còn được chia sẻ.',
        kind: 'not-found',
      };
    }
    if (cause.status === 429) {
      return {
        message: 'Bạn đang kiểm tra hơi nhanh. Vui lòng đợi khoảng 15 giây rồi thử lại.',
        kind: 'rate-limit',
      };
    }
    return { message: cause.message || 'Chưa thể tải báo giá.', kind: 'network' };
  }
  return { message: 'Kết nối chưa ổn định. Vui lòng thử lại.', kind: 'network' };
}

async function loadQuote(background = false): Promise<PublicBookingQuote | null> {
  if (disposed) return null;
  if (loadInFlight) return loadInFlight;
  if (!props.publicId) {
    loading.value = false;
    error.value = 'Liên kết báo giá không hợp lệ.';
    errorKind.value = 'missing';
    return null;
  }

  loadController = new AbortController();
  if (!background && !quote.value) loading.value = true;

  loadInFlight = (async () => {
    try {
      const data = await getPublicBookingQuote(props.publicId, loadController?.signal);
      if (!data.can_checkout || data.due_amount !== transfer.value?.amount
        || (quote.value && data.payment_status !== quote.value.payment_status)) transfer.value = null;
      quote.value = data;
      imageFailed.value = false;
      error.value = '';
      errorKind.value = '';
      refreshError.value = '';
      return data;
    } catch (cause) {
      if (cause instanceof DOMException && cause.name === 'AbortError') return null;
      const friendly = messageForError(cause);
      // Revoked/removed links must immediately lose their stale checkout CTA.
      if (cause instanceof ApiError && cause.status === 404) {
        quote.value = null;
        error.value = friendly.message;
        errorKind.value = friendly.kind;
        if (pollTimer) clearTimeout(pollTimer);
      } else if (quote.value) refreshError.value = friendly.message;
      else {
        error.value = friendly.message;
        errorKind.value = friendly.kind;
      }
      return null;
    } finally {
      loading.value = false;
      refreshing.value = false;
      loadInFlight = null;
      loadController = null;
    }
  })();

  return loadInFlight;
}

function shouldPoll(current: PublicBookingQuote | null): boolean {
  return !disposed
    && !!current
    && current.effective_status === 'published'
    && ['unpaid', 'deposit_paid', 'review_required'].includes(current.payment_status)
    && pollCount < MAX_POLLS;
}

function schedulePoll(): void {
  if (pollTimer) clearTimeout(pollTimer);
  if (!shouldPoll(quote.value)) return;

  const delay = quote.value?.payment_status === 'review_required' ? 20000 : 10000;

  pollTimer = setTimeout(async () => {
    if (disposed) return;
    pollTimer = null;
    pollCount += 1;
    await loadQuote(true);
    if (!disposed) schedulePoll();
  }, delay);
}

async function refresh(): Promise<void> {
  if (refreshing.value || loadInFlight) return;
  refreshing.value = true;
  refreshError.value = '';
  const latest = await loadQuote(true);
  if (latest && !disposed) {
    pollCount = 0;
    schedulePoll();
  }
}

function safeSameOriginImage(value: string): string {
  if (!value) return '';
  try {
    const url = new URL(value, window.location.origin);
    if (url.origin !== window.location.origin) return '';
    if (url.protocol !== 'https:' && url.origin !== window.location.origin) return '';
    return url.href;
  } catch {
    return '';
  }
}

function phoneHref(value: string): string {
  const phone = value.replace(/[^\d+]/g, '');
  return phone ? `tel:${phone}` : '';
}

function zaloHref(value: string): string {
  const phone = value.replace(/\D/g, '');
  return phone ? `https://zalo.me/${phone}` : '';
}

function initials(value: string | null | undefined): string {
  const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return 'V';
  return parts.slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

async function payNow(): Promise<void> {
  if (paying.value || !quote.value?.can_checkout) return;
  payError.value = '';
  paying.value = true;
  checkoutController = new AbortController();
  try {
    const expectedAmount = quote.value.due_amount;
    const expectedPurpose = quote.value.payment_status === 'deposit_paid' ? 'balance' : 'deposit';
    const result = await createBookingQuoteCheckout(props.publicId, checkoutController.signal);
    if (!Number.isSafeInteger(result.amount)
      || result.amount <= 0
      || result.amount !== expectedAmount
      || result.purpose !== expectedPurpose) {
      await loadQuote(true);
      payError.value = 'Trạng thái hoặc số tiền đã thay đổi. Vui lòng kiểm tra lại trước khi thanh toán.';
      paying.value = false;
      return;
    }
    if (!result.transfer || result.transfer.amount !== result.amount || !result.transfer.memo) {
      throw new Error('Thông tin chuyển khoản chưa đầy đủ. Vui lòng thử lại.');
    }
    transfer.value = result.transfer;
    pollCount = 0;
    await nextTick();
    document.querySelector<HTMLElement>('#bq-transfer')?.focus();
    schedulePoll();
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === 'AbortError') return;
    if (cause instanceof ApiError && cause.status === 409) {
      await loadQuote(true);
      payError.value = 'Trạng thái báo giá vừa thay đổi. Vui lòng kiểm tra lại.';
    } else if (cause instanceof ApiError && cause.status === 503) {
      payError.value = 'Dịch vụ tạo hướng dẫn chuyển khoản đang tạm thời gián đoạn. Vui lòng thử lại sau.';
    } else {
      payError.value = cause instanceof Error && !(cause instanceof ApiError)
        ? cause.message
        : messageForError(cause).message || 'Chưa thể tạo hướng dẫn chuyển khoản.';
    }
    paying.value = false;
  } finally {
    paying.value = false;
    checkoutController = null;
  }
}

async function copyValue(value: string, field: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(value);
    copiedField.value = field;
    window.setTimeout(() => { if (copiedField.value === field) copiedField.value = ''; }, 1800);
  } catch {
    payError.value = 'Không thể sao chép tự động. Vui lòng chọn và sao chép nội dung chuyển khoản.';
  }
}

const coverImage = computed(() => safeSameOriginImage(quote.value?.image_url || ''));
const logoImage = computed(() => safeSameOriginImage(quote.value?.brand?.logo_url || '') || vieLogo);
const advisorName = computed(() => quote.value?.contact_name || 'Đội ngũ Vie Limo');
const customerInitials = computed(() => initials(quote.value?.customer_name));
const advisorInitials = computed(() => initials(advisorName.value));
const hasTripDates = computed(() => !!(quote.value?.trip_start || quote.value?.trip_end));
const roomItems = computed(() => quote.value?.items ?? []);
const hasDetails = computed(() => !!(
  quote.value?.description || quote.value?.inclusions || quote.value?.exclusions || quote.value?.terms
));
const canPay = computed(() => !!quote.value?.can_checkout && !paying.value);
const payLabel = computed(() => {
  if (!quote.value) return 'Thanh toán';
  return quote.value.payment_status === 'deposit_paid'
    ? `Thanh toán còn lại ${formatMoney(quote.value.due_amount)}`
    : `Đặt cọc ${formatMoney(quote.value.due_amount)}`;
});
const paymentLabel = computed(() => {
  switch (quote.value?.payment_status) {
    case 'deposit_paid': return 'Đã nhận cọc';
    case 'paid': return 'Đã thanh toán đủ';
    case 'review_required': return 'Đang đối soát';
    default: return 'Chờ thanh toán';
  }
});
const statusTone = computed(() => {
  switch (quote.value?.payment_status) {
    case 'paid': return 'success';
    case 'deposit_paid': return 'primary';
    case 'review_required': return 'warning';
    default: return quote.value?.effective_status === 'expired' ? 'muted' : 'neutral';
  }
});
const statusNotice = computed(() => {
  const current = quote.value;
  if (!current) return '';
  if (current.payment_status === 'paid') {
    return 'Hệ thống đã ghi nhận đủ số tiền của báo giá. Nhân viên phụ trách sẽ xác nhận dịch vụ với bạn.';
  }
  if (current.payment_status === 'deposit_paid') {
    return 'Hệ thống đã nhận cọc. Đây chưa phải xác nhận giữ chỗ; nhân viên phụ trách sẽ liên hệ xác nhận dịch vụ.';
  }
  if (current.payment_status === 'review_required') {
    return 'Khoản thanh toán đang được nhân viên đối soát. Vui lòng chưa thực hiện thêm giao dịch.';
  }
  if (current.effective_status === 'expired') {
    return 'Báo giá đã hết hạn và không thể thanh toán. Vui lòng liên hệ nhân viên phụ trách để nhận báo giá mới.';
  }
  return 'Báo giá đang chờ thanh toán cọc. Thanh toán không tự động xác nhận giữ chỗ.';
});

function printQuote(): void {
  window.print();
}

onMounted(async () => {
  await loadQuote();
  if (disposed) return;
  schedulePoll();
});

onBeforeUnmount(() => {
  disposed = true;
  if (pollTimer) clearTimeout(pollTimer);
  loadController?.abort();
  checkoutController?.abort();
});
</script>

<template>
  <main class="bq-page" :aria-busy="loading">
    <div v-if="loading && !quote" class="bq-state bq-state-loading" role="status" aria-live="polite">
      <span class="bq-spinner" aria-hidden="true" />
      <h1>Đang mở báo giá…</h1>
      <p>Vui lòng chờ trong giây lát.</p>
    </div>

    <div v-else-if="error && !quote" class="bq-state" role="alert">
      <div class="bq-state-icon" aria-hidden="true"><i class="pi pi-file" /></div>
      <h1>{{ errorKind === 'not-found' ? 'Báo giá không khả dụng' : 'Chưa thể mở báo giá' }}</h1>
      <p>{{ error }}</p>
      <button
        v-if="errorKind !== 'not-found' && errorKind !== 'missing'"
        type="button"
        class="bq-btn bq-btn-secondary"
        :disabled="refreshing"
        @click="refresh"
      >
        <i :class="['pi', refreshing ? 'pi-spin pi-spinner' : 'pi-refresh']" aria-hidden="true" />
        {{ refreshing ? 'Đang thử lại…' : 'Thử lại' }}
      </button>
    </div>

    <template v-else-if="quote">
      <header class="bq-header">
        <a class="bq-brand" :href="homeUrl" aria-label="Về trang chủ">
          <img v-if="logoImage" :src="logoImage" :alt="quote.brand.company_name || 'Vie Limo'">
          <span v-else class="bq-brand-mark" aria-hidden="true">V</span>
          <span v-if="!logoImage">{{ quote.brand.company_name || 'Vie Limo' }}</span>
        </a>
        <div class="bq-header-actions">
          <span class="bq-code">{{ quote.code }}</span>
          <button type="button" class="bq-print" aria-label="In báo giá" @click="printQuote">
            <i class="pi pi-print" aria-hidden="true" />
            <span>In báo giá</span>
          </button>
        </div>
      </header>

      <div class="bq-shell">
        <div class="bq-main">
          <section class="bq-hero bq-card bq-service-hero">
            <div class="bq-hero-copy">
              <span class="bq-kicker">Báo giá dành riêng cho {{ quote.customer_name }}</span>
              <h1>{{ quote.title }}</h1>
              <p v-if="quote.greeting" class="bq-greeting">{{ quote.greeting }}</p>
              <div v-if="hasTripDates" class="bq-service-meta" aria-label="Thời gian hành trình">
                <span v-if="quote.trip_start"><i class="pi pi-calendar" aria-hidden="true" /> Khởi hành {{ formatTripDate(quote.trip_start) }}</span>
                <span v-if="quote.trip_end"><i class="pi pi-flag" aria-hidden="true" /> Kết thúc {{ formatTripDate(quote.trip_end) }}</span>
              </div>
            </div>
            <div v-if="coverImage && !imageFailed" class="bq-cover">
              <img :src="coverImage" :alt="quote.title" @error="imageFailed = true">
            </div>
          </section>

          <section class="bq-identity" aria-label="Thông tin người nhận và nhân viên phụ trách">
            <div class="bq-identity-card">
              <span class="bq-identity-avatar" aria-hidden="true">{{ customerInitials }}</span>
              <div class="bq-identity-copy">
                <span>Khách hàng</span>
                <strong>{{ quote.customer_name }}</strong>
              </div>
            </div>
            <div class="bq-identity-card bq-identity-card--advisor">
              <span class="bq-identity-avatar" aria-hidden="true">{{ advisorInitials }}</span>
              <div class="bq-identity-copy">
                <span>Nhân viên phụ trách</span>
                <strong>{{ advisorName }}</strong>
              </div>
            </div>
          </section>

          <section class="bq-card bq-service" aria-labelledby="bq-service-title">
            <div class="bq-section-heading">
              <span class="bq-section-icon" aria-hidden="true"><i class="pi pi-map-marker" /></span>
              <div>
                <span class="bq-eyebrow">Dịch vụ & hành trình</span>
                <h2 id="bq-service-title">Thông tin chuyến đi</h2>
              </div>
            </div>
            <div v-if="roomItems.length" class="bq-room-items">
              <article v-for="(item, index) in roomItems" :key="`${item.room_id}-${index}`" class="bq-room-item">
                <div class="bq-room-heading">
                  <div><span class="bq-eyebrow">{{ item.hotel_name }}</span><h3>{{ item.room_name }}</h3></div>
                  <span class="bq-room-type">{{ item.booking_type === 'combo' ? 'Combo phòng + xe' : 'Phòng khách sạn' }}</span>
                </div>
                <dl class="bq-room-facts">
                  <div><dt>Nhận / trả phòng</dt><dd>{{ formatTripDate(item.checkin) }} – {{ formatTripDate(item.checkout) }}</dd></div>
                  <div><dt>Số phòng / số đêm</dt><dd>{{ item.num_rooms }} phòng · {{ item.nights }} đêm</dd></div>
                  <div><dt>Khách lưu trú</dt><dd>{{ item.adults }} người lớn<template v-if="item.child_ages.length"> · {{ item.child_ages.length }} trẻ em ({{ item.child_ages.join(', ') }} tuổi)</template></dd></div>
                </dl>
                <dl class="bq-room-prices">
                  <div><dt>Tiền phòng</dt><dd>{{ formatMoney(item.room_subtotal) }}</dd></div>
                  <div v-if="item.extra_adult_total > 0"><dt>Phụ thu người lớn</dt><dd>{{ formatMoney(item.extra_adult_total) }}</dd></div>
                  <div v-if="item.child_surcharge_total > 0"><dt>Phụ thu trẻ em</dt><dd>{{ formatMoney(item.child_surcharge_total) }}</dd></div>
                  <div v-if="item.booking_type === 'combo'"><dt>Vé xe khứ hồi · {{ item.ticket_count }} vé</dt><dd>{{ formatMoney(item.ticket_subtotal) }}</dd></div>
                  <div class="bq-room-total"><dt>Tạm tính</dt><dd>{{ formatMoney(item.line_total) }}</dd></div>
                </dl>
              </article>
            </div>
            <div v-else-if="quote.lines.length" class="bq-guest-summary" aria-label="Tóm tắt dịch vụ">
              <span><i class="pi pi-list" aria-hidden="true" /> {{ quote.lines.length }} hạng mục dịch vụ</span>
            </div>
            <p v-if="quote.description" class="bq-preline">{{ quote.description }}</p>
          </section>

          <section class="bq-card" aria-labelledby="bq-pricing-title">
            <div class="bq-section-heading">
              <span class="bq-section-icon" aria-hidden="true"><i class="pi pi-receipt" /></span>
              <div>
                <span class="bq-eyebrow">Chi tiết chi phí</span>
                <h2 id="bq-pricing-title">Bảng giá</h2>
              </div>
            </div>
            <div class="bq-lines" role="table" aria-label="Các hạng mục báo giá">
              <div class="bq-line bq-line-head" role="row">
                <span role="columnheader">Hạng mục</span>
                <span role="columnheader">SL</span>
                <span role="columnheader">Đơn giá</span>
                <span role="columnheader">Thành tiền</span>
              </div>
              <div v-for="(line, index) in quote.lines" :key="`${line.label}-${index}`" class="bq-line" role="row">
                <span class="bq-line-name" role="cell">
                  <strong>{{ line.label }}</strong>
                  <small>{{ line.quantity }} {{ line.unit }}</small>
                </span>
                <span class="bq-line-qty" role="cell">{{ line.quantity }} {{ line.unit }}</span>
                <span class="bq-line-unit-price" role="cell"><small class="bq-line-price-label">Đơn giá</small>{{ formatMoney(line.unit_price) }}</span>
                <strong class="bq-line-total" role="cell"><small class="bq-line-price-label">Thành tiền</small>{{ formatMoney(line.line_total) }}</strong>
              </div>
            </div>
            <div class="bq-table-totals">
              <div><span>Tạm tính</span><strong>{{ formatMoney(quote.subtotal) }}</strong></div>
              <div v-if="quote.discount > 0" class="bq-discount"><span>Ưu đãi</span><strong>− {{ formatMoney(quote.discount) }}</strong></div>
              <div class="bq-grand-total"><span>Tổng báo giá</span><strong>{{ formatMoney(quote.total) }}</strong></div>
            </div>
          </section>

          <section v-if="hasDetails" class="bq-card" aria-labelledby="bq-details-title">
            <div class="bq-section-heading">
              <span class="bq-section-icon" aria-hidden="true"><i class="pi pi-list-check" /></span>
              <div><span class="bq-eyebrow">Thông tin cần biết</span><h2 id="bq-details-title">Nội dung dịch vụ</h2></div>
            </div>
            <div class="bq-detail-grid">
              <article v-if="quote.inclusions" class="bq-detail bq-detail-included">
                <h3><i class="pi pi-check-circle" aria-hidden="true" /> Dịch vụ bao gồm</h3>
                <p class="bq-preline">{{ quote.inclusions }}</p>
              </article>
              <article v-if="quote.exclusions" class="bq-detail">
                <h3><i class="pi pi-info-circle" aria-hidden="true" /> Không bao gồm</h3>
                <p class="bq-preline">{{ quote.exclusions }}</p>
              </article>
              <article v-if="quote.terms" class="bq-detail bq-detail-wide">
                <h3><i class="pi pi-file-edit" aria-hidden="true" /> Điều kiện & chính sách</h3>
                <p class="bq-preline">{{ quote.terms }}</p>
              </article>
            </div>
          </section>

          <section v-if="quote.contact_name || quote.contact_phone || quote.contact_zalo" class="bq-card bq-contact" aria-labelledby="bq-contact-title">
            <div class="bq-contact-avatar" aria-hidden="true"><i class="pi pi-user" /></div>
            <div class="bq-contact-copy">
              <span class="bq-eyebrow">Nhân viên phụ trách</span>
              <h2 id="bq-contact-title">{{ quote.contact_name || 'Đội ngũ Vie Limo' }}</h2>
              <p>Liên hệ để được hỗ trợ lịch trình và xác nhận dịch vụ.</p>
            </div>
            <div class="bq-contact-actions">
              <a v-if="quote.contact_phone && phoneHref(quote.contact_phone)" class="bq-btn bq-btn-secondary" :href="phoneHref(quote.contact_phone)">
                <i class="pi pi-phone" aria-hidden="true" /> Gọi điện
              </a>
              <a v-if="quote.contact_zalo && zaloHref(quote.contact_zalo)" class="bq-btn bq-btn-secondary" :href="zaloHref(quote.contact_zalo)" target="_blank" rel="noopener noreferrer">
                <i class="pi pi-comments" aria-hidden="true" /> Zalo
              </a>
            </div>
          </section>
        </div>

        <aside class="bq-summary" aria-label="Tóm tắt thanh toán">
          <div class="bq-card bq-summary-card">
            <div class="bq-summary-top">
              <span>Tình trạng thanh toán</span>
              <span :class="['bq-status', `bq-status-${statusTone}`]">{{ paymentLabel }}</span>
            </div>
            <div ref="statusNoticeEl" :class="['bq-notice', `bq-notice-${statusTone}`]" role="status" aria-live="polite" tabindex="-1">
              <i :class="['pi', quote.payment_status === 'paid' ? 'pi-check-circle' : quote.payment_status === 'review_required' ? 'pi-exclamation-triangle' : 'pi-info-circle']" aria-hidden="true" />
              <span>{{ statusNotice }}</span>
            </div>

            <dl class="bq-money-summary">
              <div><dt>Tổng báo giá</dt><dd>{{ formatMoney(quote.total) }}</dd></div>
              <div><dt>Tiền cọc</dt><dd>{{ formatMoney(quote.deposit_amount) }}</dd></div>
              <div><dt>Đã thanh toán</dt><dd>{{ formatMoney(quote.paid_amount) }}</dd></div>
              <div class="bq-money-due"><dt>{{ quote.payment_status === 'deposit_paid' ? 'Còn lại' : 'Cần thanh toán' }}</dt><dd>{{ formatMoney(quote.due_amount) }}</dd></div>
            </dl>

            <div class="bq-expiry">
              <i class="pi pi-clock" aria-hidden="true" />
              <span><small>Hiệu lực báo giá</small><strong><time v-if="quote.expires_at" :datetime="quote.expires_at">{{ formatExpiry(quote.expires_at) }}</time><template v-else>Không giới hạn</template></strong></span>
            </div>

            <section v-if="transfer && quote.can_checkout" id="bq-transfer" class="bq-transfer" aria-labelledby="bq-transfer-title" tabindex="-1">
              <div class="bq-transfer-heading">
                <span class="bq-section-icon" aria-hidden="true"><i class="pi pi-qrcode" /></span>
                <div><span class="bq-eyebrow">Chuyển khoản ngân hàng</span><h3 id="bq-transfer-title">Quét mã để thanh toán</h3></div>
              </div>
              <img v-if="transfer.qr_url" class="bq-qr" :src="transfer.qr_url" alt="Mã QR chuyển khoản với số tiền và nội dung đã điền sẵn" loading="lazy">
              <p class="bq-transfer-amount">{{ formatMoney(transfer.amount) }}</p>
              <dl class="bq-transfer-details">
                <div><dt>Ngân hàng</dt><dd>{{ transfer.bank_name }}<small v-if="transfer.bank_code"> · {{ transfer.bank_code }}</small></dd></div>
                <div><dt>Số tài khoản</dt><dd><span>{{ transfer.bank_account }}</span><button type="button" class="bq-copy" :aria-label="copiedField === 'account' ? 'Đã sao chép số tài khoản' : 'Sao chép số tài khoản'" @click="copyValue(transfer.bank_account, 'account')"><i :class="['pi', copiedField === 'account' ? 'pi-check' : 'pi-copy']" aria-hidden="true" /> {{ copiedField === 'account' ? 'Đã sao chép' : 'Sao chép' }}</button></dd></div>
                <div><dt>Chủ tài khoản</dt><dd>{{ transfer.bank_holder }}</dd></div>
                <div class="bq-transfer-memo"><dt>Nội dung chuyển khoản</dt><dd><code>{{ transfer.memo }}</code><button type="button" class="bq-copy" :aria-label="copiedField === 'memo' ? 'Đã sao chép nội dung chuyển khoản' : 'Sao chép nội dung chuyển khoản'" @click="copyValue(transfer.memo, 'memo')"><i :class="['pi', copiedField === 'memo' ? 'pi-check' : 'pi-copy']" aria-hidden="true" /> {{ copiedField === 'memo' ? 'Đã sao chép' : 'Sao chép' }}</button></dd></div>
              </dl>
              <p class="bq-transfer-hint">Giữ nguyên nội dung chuyển khoản để hệ thống tự nhận diện. Trạng thái sẽ cập nhật sau khi ngân hàng xác nhận.</p>
            </section>

            <div v-if="refreshError" class="bq-alert" role="alert">{{ refreshError }}</div>
            <div v-if="payError" class="bq-alert" role="alert">{{ payError }}</div>

            <button v-if="quote.can_checkout && !transfer" type="button" class="bq-btn bq-btn-primary bq-pay-desktop" :disabled="!canPay" :aria-busy="paying" @click="payNow">
              <i :class="['pi', paying ? 'pi-spin pi-spinner' : 'pi-qrcode']" aria-hidden="true" />
              {{ paying ? 'Đang tạo mã QR…' : payLabel }}
            </button>
            <button
              v-if="quote.payment_status !== 'paid'"
              type="button"
              class="bq-check-status"
              :disabled="refreshing"
              :aria-busy="refreshing"
              @click="refresh"
            >
              <i :class="['pi', refreshing ? 'pi-spin pi-spinner' : 'pi-refresh']" aria-hidden="true" />
              {{ refreshing ? 'Đang kiểm tra…' : 'Kiểm tra trạng thái' }}
            </button>
            <p class="bq-safe-note"><i class="pi pi-shield" aria-hidden="true" /> Trạng thái chỉ được cập nhật sau khi hệ thống xác thực giao dịch từ ngân hàng.</p>
          </div>

          <div class="bq-company-card">
            <strong>{{ quote.brand.company_name }}</strong>
            <span v-if="quote.brand.company_address">{{ quote.brand.company_address }}</span>
            <a v-if="quote.brand.company_phone && phoneHref(quote.brand.company_phone)" :href="phoneHref(quote.brand.company_phone)">{{ quote.brand.company_phone }}</a>
            <a v-if="quote.brand.company_email" :href="`mailto:${quote.brand.company_email}`">{{ quote.brand.company_email }}</a>
            <span v-if="quote.brand.company_tax_id">MST: {{ quote.brand.company_tax_id }}</span>
          </div>
        </aside>
      </div>

      <div v-if="quote.can_checkout && !transfer" class="bq-mobile-cta">
        <div><span>Cần thanh toán</span><strong>{{ formatMoney(quote.due_amount) }}</strong></div>
        <button type="button" class="bq-btn bq-btn-primary" :disabled="!canPay" :aria-busy="paying" @click="payNow">
          <i :class="['pi', paying ? 'pi-spin pi-spinner' : 'pi-qrcode']" aria-hidden="true" />
          {{ paying ? 'Đang tạo mã…' : quote.payment_status === 'deposit_paid' ? 'Thanh toán còn lại' : 'Đặt cọc' }}
        </button>
      </div>
    </template>
  </main>
</template>
