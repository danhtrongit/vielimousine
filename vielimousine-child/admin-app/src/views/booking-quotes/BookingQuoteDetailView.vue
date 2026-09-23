<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Chips from 'primevue/chips';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import ProgressSpinner from 'primevue/progressspinner';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '@/components/PageHeader.vue';
import { bookingQuotesApi } from '@/api/bookingQuotes.api';
import { customersApi } from '@/api/customers.api';
import { quoteApi, type PriceBreakdown } from '@/api/quote.api';
import { useNotify } from '@/composables/useNotify';
import { useUIStore } from '@/stores/ui.store';
import { useAuthStore } from '@/stores/auth.store';
import { useLookupStore } from '@/stores/lookup.store';
import { formatDateTime, formatVND, ymdLocal } from '@/composables/useFormat';
import type { BookingQuote, BookingQuoteDetail, BookingQuoteItemPayload, BookingQuotePayload } from '@/types/bookingQuote';
import type { CustomerListItem } from '@/types/customer';
import BookingQuotePreview from './BookingQuotePreview.vue';
import {
  createDefaultQuote,
  effectiveStatusLabels,
  paymentStatusLabels,
  quoteAmounts,
  quoteToPayload,
  toLocalDateTime,
  validateQuote,
} from './bookingQuoteForm';

const route = useRoute();
const router = useRouter();
const confirm = useConfirm();
const notify = useNotify();
const ui = useUIStore();
const auth = useAuthStore();
const lookup = useLookupStore();

const quote = ref<BookingQuoteDetail | null>(null);
const form = ref<BookingQuotePayload>(createDefaultQuote());
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const loadError = ref(false);
const viewMode = ref<'edit' | 'preview'>('edit');
const skipNextRouteLoad = ref(false);
const pricing = ref<PriceBreakdown | null>(null);
const pricingPending = ref(false);
const customerSearch = ref('');
const customerSuggestions = ref<CustomerListItem[]>([]);
const customerSearching = ref(false);
let customerSearchTimer: number | null = null;
let pricingRequestId = 0;

function emptyItem(): BookingQuoteItemPayload {
  return { room_id: 0, booking_type: 'room', checkin: '', checkout: '', adults: 2, child_ages: [], user_rooms: 0 };
}
const selection = ref<BookingQuoteItemPayload>(emptyItem());
let autoFilledRoomId: number | null = null;

const isNew = computed(() => route.name === 'booking-quotes-new');
const quoteId = computed(() => isNew.value ? null : Number(route.params.id));
const canMutate = computed(() => auth.can('vie_create_booking_quotes'));
const editable = computed(() => (isNew.value || quote.value?.status === 'draft') && canMutate.value);
const roomOptions = computed(() => lookup.rooms.map((room) => ({
  label: `${room.name} — ${lookup.hotelById(room.hotel_id)?.name ?? 'Hotel ?'}`,
  value: room.id,
})));
const multiItemQuote = computed(() => form.value.items.length > 1);
const linkedCustomer = computed(() => form.value.customer_id !== null && form.value.customer_id > 0);

watch(customerSearch, (value) => {
  if (customerSearchTimer !== null) window.clearTimeout(customerSearchTimer);
  customerSuggestions.value = [];
  const query = value.trim();
  if (query.length < 2 || linkedCustomer.value) return;
  customerSearchTimer = window.setTimeout(async () => {
    customerSearching.value = true;
    try {
      const response = await customersApi.list({ q: query, per_page: 8 });
      customerSuggestions.value = response.data;
    } catch {
      customerSuggestions.value = [];
    } finally {
      customerSearching.value = false;
    }
  }, 250);
});

function selectCustomer(customer: CustomerListItem): void {
  form.value.customer_id = customer.id;
  form.value.customer_name = customer.name;
  form.value.customer_phone = customer.phone;
  form.value.customer_email = customer.email ?? '';
  customerSearch.value = customer.name + ' · ' + customer.phone;
  customerSuggestions.value = [];
}

function unlinkCustomer(): void {
  form.value.customer_id = null;
  customerSearch.value = '';
}
const amounts = computed(() => {
  const subtotal = pricing.value?.subtotal ?? (!editable.value && quote.value ? quote.value.subtotal : quoteAmounts(form.value).subtotal);
  const total = pricing.value
    ? Math.max(0, Math.round((subtotal - Math.max(0, Math.round(form.value.discount || 0))) / 1000) * 1000)
    : (!editable.value && quote.value ? quote.value.total : quoteAmounts(form.value).total);
  const rawDeposit = form.value.deposit_type === 'percent'
    ? Math.ceil(total * Math.max(0, form.value.deposit_value || 0) / 100)
    : Math.max(0, Math.round(form.value.deposit_value || 0));
  const deposit = Math.min(total, rawDeposit);
  return { subtotal, total, deposit, remaining: Math.max(0, total - deposit) };
});
const reviewReceipts = computed(() => (quote.value?.payments ?? [])
  .flatMap((payment) => payment.receipts ?? [])
  .filter((receipt) => receipt.outcome === 'review'));

const tripStartDate = computed({
  get: () => form.value.trip_start ? new Date(`${form.value.trip_start}T00:00:00`) : null,
  set: (value: Date | null) => { form.value.trip_start = value ? ymdLocal(value) : null; },
});
const tripEndDate = computed({
  get: () => form.value.trip_end ? new Date(`${form.value.trip_end}T00:00:00`) : null,
  set: (value: Date | null) => { form.value.trip_end = value ? ymdLocal(value) : null; },
});
const validUntilDate = computed({
  get: () => form.value.valid_until ? new Date(form.value.valid_until.replace(' ', 'T')) : null,
  set: (value: Date | null) => { form.value.valid_until = value ? toLocalDateTime(value) : ''; },
});

const lifecycleSeverity = computed(() => {
  switch (quote.value?.effective_status) {
    case 'published': return 'success';
    case 'expired': return 'warn';
    case 'revoked': return 'danger';
    default: return 'secondary';
  }
});
const paymentSeverity = computed(() => {
  switch (quote.value?.payment_status) {
    case 'paid': return 'success';
    case 'deposit_paid': return 'info';
    case 'review_required': return 'danger';
    default: return 'secondary';
  }
});

function setBreadcrumb(item?: BookingQuote) {
  ui.setBreadcrumb([
    { label: 'Báo giá', to: '/booking-quotes' },
    { label: item?.code || 'Tạo báo giá' },
  ]);
}

async function load() {
  setBreadcrumb();
  if (isNew.value) {
    loadError.value = false;
    quote.value = null;
    form.value = createDefaultQuote();
    customerSearch.value = '';
    customerSuggestions.value = [];
    selection.value = emptyItem();
    autoFilledRoomId = null;
    pricing.value = null;
    void lookup.ensureLoaded();
    viewMode.value = 'edit';
    return;
  }
  if (!quoteId.value || Number.isNaN(quoteId.value)) {
    quote.value = null;
    loadError.value = true;
    return;
  }
  loading.value = true;
  try {
    const response = await bookingQuotesApi.get(quoteId.value);
    loadError.value = false;
    quote.value = { ...response.data, payments: response.data.payments ?? [] };
    form.value = quoteToPayload(response.data);
    customerSearch.value = response.data.customer_id
      ? response.data.customer_name + ' · ' + response.data.customer_phone
      : '';
    selection.value = form.value.items[0] ? { ...form.value.items[0], child_ages: [...form.value.items[0].child_ages] } : emptyItem();
    autoFilledRoomId = selection.value.room_id || null;
    pricing.value = null;
    void lookup.ensureLoaded();
    viewMode.value = response.data.status === 'draft' ? 'edit' : 'preview';
    setBreadcrumb(response.data);
  } catch (error) {
    quote.value = null;
    loadError.value = true;
    notify.apiError(error, 'Không tải được báo giá');
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(() => [route.name, route.params.id] as const, (next, previous) => {
  if (next[0] === previous?.[0] && next[1] === previous?.[1]) return;
  if (skipNextRouteLoad.value) {
    skipNextRouteLoad.value = false;
    return;
  }
  load();
});

function syncSelection() {
  selection.value.child_ages = selection.value.child_ages
    .map((age) => typeof age === 'number' ? age : Number(age))
    .filter((age) => Number.isInteger(age) && age >= 0 && age <= 17);
  if (multiItemQuote.value) return;
  applyRoomDefaults();
  const first = selection.value.room_id && selection.value.checkin && selection.value.checkout
    ? { ...selection.value, child_ages: [...selection.value.child_ages] }
    : null;
  if (first) {
    // The public quote trip dates must describe the selected room stay.
    form.value.trip_start = first.checkin;
    form.value.trip_end = first.checkout;
  }
  form.value.items = first ? [first, ...form.value.items.slice(1)] : form.value.items.slice(1);
}

function applyRoomDefaults(): void {
  const roomId = selection.value.room_id;
  if (!roomId || roomId === autoFilledRoomId) return;
  const room = lookup.roomById(roomId);
  const hotel = room ? lookup.hotelById(room.hotel_id) : undefined;
  if (!room) return;
  const amenities = Array.isArray(room.amenities)
    ? room.amenities.map((item) => typeof item === 'string' ? item : item.label || item.name || '').filter(Boolean)
    : [];
  form.value.title = `${hotel?.name ? `${hotel.name} — ` : ''}${room.name}`;
  form.value.image_url = room.thumbnail_url || '';
  form.value.description = [
    room.description,
    room.area ? `${room.area} m²` : '',
    room.bed_type ? `Giường: ${room.bed_type}` : '',
    room.bed_count ? `${room.bed_count} giường` : '',
    room.view ? `View: ${room.view}` : '',
    amenities.length ? `Tiện nghi: ${amenities.join(', ')}` : '',
  ].filter(Boolean).join('\n');
  form.value.inclusions = hotel?.pricing_policy?.text || '';
  form.value.terms = hotel?.cancellation_policy?.text || '';
  autoFilledRoomId = roomId;
}

async function runQuote() {
  const requestId = ++pricingRequestId;
  syncSelection();
  if (multiItemQuote.value) {
    pricing.value = null;
    pricingPending.value = false;
    return;
  }
  const item = selection.value;
  if (!item.room_id || !item.checkin || !item.checkout || item.checkout <= item.checkin) {
    pricing.value = null;
    pricingPending.value = false;
    return;
  }
  pricingPending.value = true;
  try {
    const response = await quoteApi.quote(item);
    if (requestId !== pricingRequestId) return;
    pricing.value = response.data;
  } catch (error) {
    if (requestId === pricingRequestId) {
      pricing.value = null;
      notify.apiError(error, 'Không tính được giá phòng');
    }
  } finally {
    if (requestId === pricingRequestId) pricingPending.value = false;
  }
}

function validate(forPublish = false): boolean {
  if (forPublish && form.value.items.length > 0 && form.value.lines.length === 0 && !pricing.value) {
    notify.warn('Vui lòng chờ bảng giá phòng tải xong trước khi phát hành.');
    return false;
  }
  if (forPublish && pricing.value?.requires_quote) {
    notify.warn('Lựa chọn phòng này cần liên hệ báo giá trước khi phát hành.');
    return false;
  }
  const errors = validateQuote(form.value, forPublish);
  if (errors.length) {
    notify.warn(errors[0], errors.length > 1 ? `Còn ${errors.length - 1} mục cần kiểm tra.` : undefined);
    return false;
  }
  return true;
}

async function persistDraft(showToast = true): Promise<BookingQuoteDetail | null> {
  if (!isNew.value && !quote.value) {
    notify.error('Không thể lưu khi báo giá chưa tải được.');
    return null;
  }
  if (saving.value || pricingPending.value) return null;
  syncSelection();
  if (!validate(false)) return null;
  saving.value = true;
  try {
    const response = quote.value
      ? await bookingQuotesApi.update(quote.value.id, form.value)
      : await bookingQuotesApi.create(form.value);
    quote.value = { ...response.data, payments: quote.value?.payments ?? [] };
    form.value = quoteToPayload(response.data);
    selection.value = form.value.items[0] ? { ...form.value.items[0], child_ages: [...form.value.items[0].child_ages] } : emptyItem();
    pricing.value = null;
    setBreadcrumb(response.data);
    if (isNew.value) {
      skipNextRouteLoad.value = true;
      await router.replace(`/booking-quotes/${response.data.id}`);
    }
    if (showToast) notify.success('Đã lưu bản nháp');
    return quote.value;
  } catch (error) {
    notify.apiError(error, 'Không lưu được báo giá');
    return null;
  } finally {
    saving.value = false;
  }
}

async function copyPublicUrl(url = quote.value?.public_url): Promise<boolean> {
  if (!url) return false;
  try {
    await navigator.clipboard.writeText(url);
    notify.success('Đã sao chép link báo giá', 'Chỉ gửi cho đúng khách vì bất kỳ ai có link đều có thể xem báo giá.');
    return true;
  } catch {
    notify.warn('Không thể tự sao chép', url);
    return false;
  }
}

function requestPublish() {
  if (actionLoading.value || saving.value || pricingPending.value) return;
  syncSelection();
  if (!validate(true)) return;
  confirm.require({
    header: 'Phát hành báo giá?',
    message: 'Giá và điều khoản sẽ được khóa. Nếu cần sửa sau đó, hãy nhân bản thành báo giá mới.',
    icon: 'pi pi-send',
    rejectLabel: 'Quay lại',
    acceptLabel: 'Phát hành',
    accept: publishQuote,
  });
}

async function publishQuote() {
  actionLoading.value = true;
  try {
    const saved = await persistDraft(false);
    if (!saved) return;
    const response = await bookingQuotesApi.publish(saved.id);
    quote.value = { ...response.data, payments: quote.value?.payments ?? [] };
    form.value = quoteToPayload(response.data);
    selection.value = form.value.items[0] ? { ...form.value.items[0], child_ages: [...form.value.items[0].child_ages] } : emptyItem();
    pricing.value = null;
    viewMode.value = 'preview';
    notify.success('Đã phát hành báo giá');
    await copyPublicUrl(response.data.public_url);
  } catch (error) {
    notify.apiError(error, 'Không phát hành được báo giá');
  } finally {
    actionLoading.value = false;
  }
}

async function duplicateQuote() {
  if (!quote.value || actionLoading.value || saving.value) return;
  actionLoading.value = true;
  try {
    const response = await bookingQuotesApi.duplicate(quote.value.id);
    notify.success('Đã tạo bản nháp mới');
    await router.push(`/booking-quotes/${response.data.id}`);
  } catch (error) {
    notify.apiError(error, 'Không nhân bản được báo giá');
  } finally {
    actionLoading.value = false;
  }
}

async function createOrderDraft() {
  if (!quote.value || actionLoading.value || saving.value || !quote.value.items?.length) return;
  actionLoading.value = true;
  try {
    const response = await bookingQuotesApi.orderDraft(quote.value.id);
    notify.success('Đã tạo đơn nháp từ báo giá', 'Kiểm tra lại tồn kho và xác nhận trước khi tạo đơn chính thức.');
    await router.push(`/orders/new?draft=${response.data.id}`);
  } catch (error) {
    notify.apiError(error, 'Không tạo được đơn từ báo giá');
  } finally {
    actionLoading.value = false;
  }
}

function requestRevoke() {
  if (!quote.value || actionLoading.value || saving.value) return;
  confirm.require({
    header: 'Thu hồi báo giá?',
    message: 'Khách sẽ không thể mở link hoặc thực hiện thanh toán mới. Lịch sử thanh toán vẫn được giữ lại.',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Giữ báo giá',
    acceptLabel: 'Thu hồi',
    acceptClass: 'p-button-danger',
    accept: revokeQuote,
  });
}

async function revokeQuote() {
  if (!quote.value || actionLoading.value || saving.value) return;
  actionLoading.value = true;
  try {
    const response = await bookingQuotesApi.revoke(quote.value.id);
    quote.value = { ...response.data, payments: quote.value?.payments ?? [] };
    form.value = quoteToPayload(response.data);
    notify.success('Đã thu hồi báo giá');
  } catch (error) {
    notify.apiError(error, 'Không thu hồi được báo giá');
  } finally {
    actionLoading.value = false;
  }
}
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /><span>Đang tải báo giá…</span></div>
  <Message v-else-if="loadError" severity="error" :closable="false">
    Không tải được báo giá này. Kiểm tra lại đường dẫn hoặc quay về danh sách để thử lại.
    <template #icon><i class="pi pi-exclamation-triangle" /></template>
  </Message>
  <div v-else class="quote-page">
    <PageHeader
      :title="quote?.code || 'Tạo báo giá'"
      :subtitle="editable ? 'Nhập thông tin, xem trước rồi phát hành link cho khách' : (quote?.title || '')"
      icon="pi pi-file-edit"
    >
      <Button label="Danh sách" icon="pi pi-arrow-left" severity="secondary" text @click="router.push('/booking-quotes')" />
      <template v-if="editable">
        <Button
          :label="viewMode === 'edit' ? 'Xem trước' : 'Tiếp tục sửa'"
          :icon="viewMode === 'edit' ? 'pi pi-eye' : 'pi pi-pencil'"
          severity="secondary"
          outlined
          @click="viewMode = viewMode === 'edit' ? 'preview' : 'edit'"
        />
        <Button label="Lưu nháp" icon="pi pi-save" severity="secondary" :loading="saving" :disabled="actionLoading || pricingPending" @click="persistDraft()" />
        <Button label="Phát hành & sao chép link" icon="pi pi-send" :loading="actionLoading" :disabled="saving || pricingPending" @click="requestPublish" />
      </template>
      <template v-else-if="quote">
        <Button
          v-if="auth.can('vie_create_orders') && quote.status !== 'revoked' && quote.items?.length"
          label="Tạo đơn từ báo giá"
          icon="pi pi-shopping-cart"
          severity="success"
          :loading="actionLoading"
          :disabled="saving"
          @click="createOrderDraft"
        />
        <Button v-if="quote.public_url && quote.status === 'published'" label="Sao chép link" icon="pi pi-copy" @click="copyPublicUrl()" />
        <Button v-if="canMutate" label="Nhân bản để sửa" icon="pi pi-clone" severity="secondary" outlined :loading="actionLoading" @click="duplicateQuote" />
        <Button v-if="canMutate && quote.status === 'published'" label="Thu hồi" icon="pi pi-ban" severity="danger" text :disabled="actionLoading || saving" @click="requestRevoke" />
      </template>
    </PageHeader>

    <Message v-if="quote?.payment_review" severity="error" :closable="false" class="review-warning">
      <strong>Cần đối soát thanh toán.</strong> Có khoản tiền không khớp hoặc đến sau khi báo giá không còn khả dụng. Không yêu cầu khách thanh toán thêm trước khi kiểm tra.
    </Message>

    <div v-if="quote" class="status-strip" aria-label="Trạng thái báo giá">
      <div><span>Trạng thái</span><Tag :value="effectiveStatusLabels[quote.effective_status]" :severity="lifecycleSeverity" /></div>
      <div><span>Thanh toán</span><Tag :value="paymentStatusLabels[quote.payment_status]" :severity="paymentSeverity" /></div>
      <div><span>Đã thu</span><strong>{{ formatVND(quote.paid_amount) }}</strong></div>
      <div><span>Còn lại</span><strong>{{ formatVND(quote.remaining_amount) }}</strong></div>
    </div>

    <form v-if="editable && viewMode === 'edit'" class="form-layout" @submit.prevent="persistDraft()">
      <main class="form-main">
        <section class="form-card" aria-labelledby="customer-heading">
          <div class="section-heading"><span>1</span><div><h2 id="customer-heading">Khách hàng</h2><p>Thông tin liên hệ chỉ hiển thị trong quản trị.</p></div></div>
          <div class="customer-link-field">
            <label for="quote-customer-search">Liên kết khách hàng trong hệ thống Orders</label>
            <div class="customer-search-wrap">
              <InputText
                id="quote-customer-search"
                v-model="customerSearch"
                :disabled="linkedCustomer"
                placeholder="Tìm theo tên, số điện thoại hoặc email"
                autocomplete="off"
              />
              <Button v-if="linkedCustomer" type="button" label="Bỏ liên kết" severity="secondary" text @click="unlinkCustomer" />
              <ProgressSpinner v-else-if="customerSearching" style="width: 20px;height: 20px" />
              <div v-if="customerSuggestions.length && !linkedCustomer" class="customer-suggestions" role="listbox">
                <button
                  v-for="customer in customerSuggestions"
                  :key="customer.id"
                  type="button"
                  role="option"
                  class="customer-suggestion"
                  @click="selectCustomer(customer)"
                >
                  <strong>{{ customer.name || 'Chưa có tên' }}</strong>
                  <span>{{ customer.phone }}<template v-if="customer.email"> · {{ customer.email }}</template></span>
                </button>
              </div>
            </div>
            <small class="muted">{{ linkedCustomer ? 'Đã liên kết hồ sơ khách hàng; thông tin bên dưới lấy theo bản ghi này.' : 'Có thể nhập thủ công nếu chưa có hồ sơ khách hàng.' }}</small>
          </div>
          <div class="field-grid">
            <div class="field span-2"><label for="quote-customer">Tên khách / đoàn <em>*</em></label><InputText id="quote-customer" v-model="form.customer_name" :disabled="linkedCustomer" autocomplete="name" /></div>
            <div class="field"><label for="quote-phone">Điện thoại</label><InputText id="quote-phone" v-model="form.customer_phone" :disabled="linkedCustomer" inputmode="tel" autocomplete="tel" /></div>
            <div class="field"><label for="quote-email">Email</label><InputText id="quote-email" v-model="form.customer_email" :disabled="linkedCustomer" type="email" autocomplete="email" /></div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="trip-heading">
          <div class="section-heading"><span>2</span><div><h2 id="trip-heading">Chuyến đi / dịch vụ</h2><p>Nội dung khách sẽ thấy khi mở link.</p></div></div>
          <div class="field-grid">
            <div class="field span-2"><label for="quote-title">Phòng / dịch vụ <em>*</em></label><InputText id="quote-title" v-model="form.title" readonly /><small class="muted">Tên và ảnh được lấy tự động từ phòng đã chọn.</small></div>
            <div class="field"><label for="trip-start">Ngày đi</label><DatePicker input-id="trip-start" v-model="tripStartDate" date-format="dd/mm/yy" show-icon /></div>
            <div class="field"><label for="trip-end">Ngày về</label><DatePicker input-id="trip-end" v-model="tripEndDate" date-format="dd/mm/yy" show-icon /></div>
            <div class="field span-2"><label for="quote-greeting">Lời chào</label><Textarea id="quote-greeting" v-model="form.greeting" rows="3" auto-resize /></div>
            <div class="field span-2"><label for="quote-description">Mô tả</label><Textarea id="quote-description" v-model="form.description" rows="4" auto-resize /></div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="pricing-heading">
          <div class="section-heading"><span>3</span><div><h2 id="pricing-heading">Phòng và bảng giá</h2><p>Chọn đúng thông tin như khi tạo đơn. Giá và thành tiền do máy chủ tính.</p></div></div>
          <div class="field-grid">
            <div class="field span-2"><label for="quote-room">Phòng <em>*</em></label><Select input-id="quote-room" v-model="selection.room_id" :options="roomOptions" option-label="label" option-value="value" filter placeholder="Chọn phòng" @change="runQuote" /></div>
            <div class="field"><label for="quote-booking-type">Loại đặt</label><Select input-id="quote-booking-type" v-model="selection.booking_type" :options="[{ label: 'Phòng', value: 'room' }, { label: 'Combo (phòng + vé)', value: 'combo' }]" option-label="label" option-value="value" @change="runQuote" /></div>
            <div class="field"><label for="quote-user-rooms">Số phòng (0 = tự động)</label><InputNumber input-id="quote-user-rooms" v-model="selection.user_rooms" :min="0" :max="10" show-buttons @input="runQuote" /></div>
            <div class="field"><label for="quote-checkin">Check-in <em>*</em></label><DatePicker input-id="quote-checkin" :model-value="selection.checkin ? new Date(`${selection.checkin}T00:00:00`) : null" date-format="yy-mm-dd" show-icon @update:model-value="(v: any) => { selection.checkin = v instanceof Date ? ymdLocal(v) : ''; runQuote(); }" /></div>
            <div class="field"><label for="quote-checkout">Check-out <em>*</em></label><DatePicker input-id="quote-checkout" :model-value="selection.checkout ? new Date(`${selection.checkout}T00:00:00`) : null" date-format="yy-mm-dd" show-icon @update:model-value="(v: any) => { selection.checkout = v instanceof Date ? ymdLocal(v) : ''; runQuote(); }" /></div>
            <div class="field"><label for="quote-adults">Số người lớn</label><InputNumber input-id="quote-adults" v-model="selection.adults" :min="1" :max="20" show-buttons @input="runQuote" /></div>
            <div class="field"><label for="quote-child-ages">Tuổi các bé</label><Chips input-id="quote-child-ages" v-model="selection.child_ages" separator="," @add="runQuote" @remove="runQuote" /><small class="muted">Nhập từng tuổi rồi Enter (ví dụ: 5, 8)</small></div>
          </div>
          <Message v-if="multiItemQuote" severity="info" :closable="false">Báo giá này có nhiều lựa chọn phòng. Các lựa chọn và giá đã lưu được giữ nguyên; phần xem lại chi tiết hiển thị bên dưới.</Message>

          <div v-if="pricingPending" class="quote-loading"><ProgressSpinner style="width: 24px;height: 24px" /><span>Đang tính giá…</span></div>
          <div v-else-if="pricing" class="quote-panel">
            <h4>Bảng tính giá</h4>
            <Message v-if="pricing.requires_quote" severity="warn" :closable="false">{{ pricing.messages.join('. ') }}</Message>
            <div class="quote-grid">
              <div><span>Số phòng:</span> <strong>{{ pricing.num_rooms }}</strong></div>
              <div><span>Số đêm:</span> <strong>{{ pricing.nights }}</strong></div>
              <div><span>Người lớn quy đổi:</span> <strong>{{ pricing.effective_adults }}</strong></div>
              <div><span>Trẻ em quy đổi:</span> <strong>{{ pricing.effective_children }}</strong></div>
              <div v-if="selection.booking_type === 'combo'"><span>Số vé tính phí / Tổng ghế:</span> <strong>{{ pricing.billable_seats }} / {{ pricing.seat_count }}</strong> (miễn {{ pricing.free_child_seats }})</div>
              <div><span>Tiền phòng:</span> <strong>{{ formatVND(pricing.room_subtotal) }}</strong></div>
              <div v-if="pricing.extra_adult_subtotal > 0"><span>Giường phụ:</span> <strong>{{ formatVND(pricing.extra_adult_subtotal) }}</strong></div>
              <div v-if="pricing.child_surcharge_total > 0"><span>Phụ thu bé:</span> <strong>{{ formatVND(pricing.child_surcharge_total) }}</strong></div>
              <div v-if="pricing.ticket_subtotal > 0"><span>Vé xe:</span> <strong>{{ formatVND(pricing.ticket_subtotal) }}</strong></div>
              <div class="total"><span>Tổng tạm tính:</span> <strong>{{ formatVND(pricing.total) }}</strong></div>
            </div>
            <div v-if="pricing.messages.length" class="quote-messages"><i class="pi pi-info-circle" /><ul><li v-for="(message, i) in pricing.messages" :key="i">{{ message }}</li></ul></div>
          </div>
          <div v-else-if="!form.items.length && !form.lines.length" class="empty-pricing">Chọn phòng và ngày để hệ thống tính bảng giá.</div>

          <div v-if="form.lines.length" class="line-list generated-lines">
            <h4>Chi tiết giá từ máy chủ</h4>
            <div v-for="(line, index) in form.lines" :key="index" class="line-row">
              <div class="line-index">{{ index + 1 }}</div>
              <div class="generated-label"><strong>{{ line.label || line.room_name || 'Dòng giá' }}</strong><small v-if="line.hotel_name">{{ line.hotel_name }} · {{ line.room_name }}</small></div>
              <div class="line-total"><span>Thành tiền</span><strong>{{ formatVND(line.line_total) }}</strong></div>
            </div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="content-heading">
          <div class="section-heading"><span>4</span><div><h2 id="content-heading">Nội dung & điều kiện</h2><p>Chính sách phòng được điền sẵn từ khách sạn; bạn có thể chỉnh sửa trước khi lưu.</p></div></div>
          <div class="field-grid">
            <div class="field"><label for="quote-inclusions">Dịch vụ bao gồm</label><Textarea id="quote-inclusions" v-model="form.inclusions" rows="5" auto-resize /></div>
            <div class="field"><label for="quote-exclusions">Không bao gồm</label><Textarea id="quote-exclusions" v-model="form.exclusions" rows="5" auto-resize /></div>
            <div class="field span-2"><label for="quote-terms">Điều kiện đặt / hủy</label><Textarea id="quote-terms" v-model="form.terms" rows="5" auto-resize /></div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="contact-heading">
          <div class="section-heading"><span>5</span><div><h2 id="contact-heading">Người phụ trách</h2><p>Lưu cùng báo giá để khách luôn có đúng đầu mối hỗ trợ.</p></div></div>
          <div class="field-grid three">
            <div class="field"><label for="contact-name">Tên nhân viên</label><InputText id="contact-name" v-model="form.contact_name" /></div>
            <div class="field"><label for="contact-phone">Điện thoại</label><InputText id="contact-phone" v-model="form.contact_phone" inputmode="tel" /></div>
            <div class="field"><label for="contact-zalo">Số Zalo</label><InputText id="contact-zalo" v-model="form.contact_zalo" inputmode="tel" placeholder="Chỉ nhập số điện thoại" /></div>
          </div>
        </section>
      </main>

      <aside class="summary-card">
        <h2>Tóm tắt báo giá</h2>
        <div class="summary-row"><span>Tạm tính</span><strong>{{ formatVND(amounts.subtotal) }}</strong></div>
        <div class="field"><label for="quote-discount">Giảm giá</label><InputNumber input-id="quote-discount" v-model="form.discount" :min="0" :max="amounts.subtotal" :min-fraction-digits="0" :max-fraction-digits="0" suffix=" đ" /></div>
        <div class="summary-row total"><span>Tổng cộng</span><strong>{{ formatVND(amounts.total) }}</strong></div>
        <div class="field"><label for="deposit-type">Cách tính cọc</label><Select input-id="deposit-type" v-model="form.deposit_type" :options="[{ label: 'Theo phần trăm', value: 'percent' }, { label: 'Số tiền cố định', value: 'fixed' }]" option-label="label" option-value="value" /></div>
        <div class="field"><label for="deposit-value">{{ form.deposit_type === 'percent' ? 'Tỷ lệ cọc' : 'Số tiền cọc' }}</label><InputNumber input-id="deposit-value" v-model="form.deposit_value" :min="0" :max="form.deposit_type === 'percent' ? 100 : amounts.total" :min-fraction-digits="0" :max-fraction-digits="0" :suffix="form.deposit_type === 'percent' ? '%' : ' đ'" /></div>
        <div class="deposit-box"><span>Khách thanh toán cọc</span><strong>{{ formatVND(amounts.deposit) }}</strong></div>
        <div class="summary-row"><span>Còn lại sau cọc</span><strong>{{ formatVND(amounts.remaining) }}</strong></div>
        <div class="field"><label for="valid-until">Hạn báo giá <em>*</em></label><DatePicker input-id="valid-until" v-model="validUntilDate" date-format="dd/mm/yy" show-time hour-format="24" show-icon /></div>
        <p class="summary-note"><i class="pi pi-info-circle" /> Phát hành không giữ phòng và không tự tạo đơn booking.</p>
      </aside>

      <div class="mobile-actions">
        <Button label="Lưu nháp" icon="pi pi-save" severity="secondary" :loading="saving" :disabled="actionLoading || pricingPending" type="submit" />
        <Button label="Phát hành" icon="pi pi-send" :loading="actionLoading" :disabled="saving || pricingPending" type="button" @click="requestPublish" />
      </div>
    </form>

    <template v-else>
      <div class="preview-layout">
        <BookingQuotePreview :model="form" :quote="quote" />
        <aside v-if="quote && !editable" class="operations-card">
          <h2>Thanh toán</h2>
          <div class="money-highlight"><span>Đã thu</span><strong>{{ formatVND(quote.paid_amount) }}</strong></div>
          <div class="operation-row"><span>Tiền cọc</span><strong>{{ formatVND(quote.deposit_amount) }}</strong></div>
          <div class="operation-row"><span>Còn lại</span><strong>{{ formatVND(quote.remaining_amount) }}</strong></div>
          <div class="operation-row"><span>Hạn báo giá</span><strong>{{ formatDateTime(quote.expires_at) }}</strong></div>
          <p class="immutable-note"><i class="pi pi-lock" /> {{ quote.status === 'draft' ? 'Bạn chỉ có quyền xem báo giá này.' : 'Báo giá đã phát hành hoặc thu hồi là chỉ đọc. Nhân bản nếu cần thay đổi nội dung.' }}</p>
        </aside>
      </div>

      <section v-if="quote && !editable" class="payments-card" aria-labelledby="payments-heading">
        <div class="payments-heading"><div><h2 id="payments-heading">Lịch sử thanh toán</h2><p>Dữ liệu xác nhận từ hệ thống thanh toán, tách riêng khỏi đơn booking.</p></div></div>
        <div class="payments-table">
          <DataTable :value="quote.payments" data-key="id" striped-rows :empty-message="'Chưa có giao dịch'" table-style="min-width: 760px">
            <Column field="created_at" header="Khởi tạo"><template #body="{ data }">{{ formatDateTime(data.created_at) }}</template></Column>
            <Column field="invoice" header="Mã thanh toán" />
            <Column field="purpose" header="Mục đích"><template #body="{ data }">{{ data.purpose === 'deposit' ? 'Thanh toán cọc' : 'Thanh toán còn lại' }}</template></Column>
            <Column field="expected_amount" header="Dự kiến"><template #body="{ data }">{{ formatVND(data.expected_amount) }}</template></Column>
            <Column field="received_amount" header="Đã nhận"><template #body="{ data }">{{ formatVND(data.received_amount) }}</template></Column>
            <Column field="transaction_id" header="Mã giao dịch"><template #body="{ data }">{{ data.transaction_id || '—' }}</template></Column>
            <Column field="status" header="Trạng thái"><template #body="{ data }"><Tag :value="data.status === 'paid' ? 'Đã nhận' : data.status === 'review' ? 'Cần đối soát' : 'Đang chờ'" :severity="data.status === 'paid' ? 'success' : data.status === 'review' ? 'danger' : 'secondary'" /></template></Column>
            <Column field="paid_at" header="Nhận lúc"><template #body="{ data }">{{ formatDateTime(data.paid_at) }}</template></Column>
          </DataTable>
        </div>
        <div v-if="reviewReceipts.length" class="review-receipts">
          <h3>Khoản tiền cần đối soát</h3>
          <article v-for="receipt in reviewReceipts" :key="receipt.id">
            <div><span>Mã giao dịch</span><strong>{{ receipt.transaction_id }}</strong></div>
            <div><span>Đã nhận</span><strong>{{ formatVND(receipt.received_amount) }}</strong></div>
            <div><span>Số tiền trên lệnh</span><strong>{{ formatVND(receipt.order_amount) }}</strong></div>
            <div><span>Nhận lúc</span><strong>{{ formatDateTime(receipt.paid_at) }}</strong></div>
            <p v-if="receipt.reason">Lý do hệ thống: {{ receipt.reason }}</p>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.quote-page {
  --quote-brand: #00a651;
  --quote-brand-hover: #00793a;
  --quote-brand-soft: #e8f8ef;
  --quote-brand-border: #b6e4c8;
  max-width: var(--content-max-width);
  margin: 0 auto;
}
.loading { min-height: 60vh; display: grid; place-content: center; justify-items: center; gap: var(--space-3); color: var(--app-text-muted); }
.review-warning { margin-bottom: var(--space-4); }
.status-strip { display: flex; flex-wrap: wrap; gap: var(--space-5); align-items: center; padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); border: 1px solid var(--app-card-border); border-radius: var(--radius-lg); background: var(--app-card-bg); }
.status-strip > div { display: flex; gap: var(--space-2); align-items: center; }
.status-strip span { color: var(--app-text-muted); font-size: .8rem; }
.form-layout { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: var(--space-5); align-items: start; }
.form-main { min-width: 0; display: grid; gap: var(--space-4); }
.form-card, .summary-card, .operations-card, .payments-card { background: var(--app-card-bg); border: 1px solid var(--app-card-border); border-radius: var(--radius-xl); padding: 18px; box-shadow: var(--shadow-xs); }
.section-heading { display: flex; align-items: flex-start; gap: var(--space-3); margin-bottom: 16px; }
.section-heading > span { flex: 0 0 28px; height: 28px; display: grid; place-items: center; color: #fff; background: var(--quote-brand); border-radius: var(--radius-full); font-size: .82rem; font-weight: 700; }
.section-heading h2, .summary-card h2, .operations-card h2, .payments-card h2 { margin: 0; font-size: 1.05rem; color: var(--app-text-strong); }
.section-heading p, .payments-heading p { margin: .25rem 0 0; font-size: .84rem; color: var(--app-text-muted); }
.section-heading-actions > :last-child { margin-left: auto; }
.customer-link-field { display: grid; gap: var(--space-2); margin-bottom: var(--space-4); }
.customer-link-field > label { color: var(--app-text); font-size: .82rem; font-weight: 500; }
.customer-search-wrap { position: relative; display: flex; align-items: center; gap: var(--space-2); }
.customer-search-wrap > .p-inputtext { flex: 1; min-width: 0; }
.customer-suggestions { position: absolute; z-index: 10; top: calc(100% + 4px); left: 0; right: 0; display: grid; gap: 2px; padding: 4px; border: 1px solid var(--app-card-border); border-radius: var(--radius-lg); background: var(--app-card-bg); box-shadow: var(--shadow-md); }
.customer-suggestion { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; width: 100%; padding: .65rem .75rem; border: 0; border-radius: var(--radius-md); background: transparent; color: var(--app-text); text-align: left; cursor: pointer; }
.customer-suggestion:hover, .customer-suggestion:focus-visible { background: var(--app-muted-bg); outline: none; }
.customer-suggestion span { color: var(--app-text-muted); font-size: .78rem; }
.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
.field-grid.three { grid-template-columns: repeat(3, 1fr); }
.span-2 { grid-column: 1 / -1; }
.field { display: flex; min-width: 0; flex-direction: column; gap: 6px; }
.field label { color: var(--app-text); font-size: .82rem; font-weight: 500; }
.field em { color: var(--p-red-500); font-style: normal; }
.field :deep(.p-inputtext), .field :deep(.p-inputnumber), .field :deep(.p-datepicker), .field :deep(.p-select), .field :deep(textarea) { width: 100%; }
.field :deep(.p-inputtext), .field :deep(.p-inputnumber-input), .field :deep(.p-datepicker-input), .field :deep(.p-select) { min-height: 38px; }
.field :deep(textarea) { line-height: 1.45; }
.line-list { display: grid; gap: var(--space-3); }
.line-row { display: grid; grid-template-columns: 28px minmax(180px, 1.6fr) 100px 100px minmax(150px, 1fr) 130px 42px; gap: var(--space-2); align-items: end; padding: var(--space-3); border: 1px solid var(--app-divider); border-radius: var(--radius-lg); background: var(--app-muted-bg); }
.line-index { align-self: center; display: grid; place-items: center; height: 28px; border-radius: var(--radius-full); background: var(--app-card-bg); color: var(--app-text-muted); font-size: .8rem; }
.line-total { display: flex; flex-direction: column; gap: var(--space-1); padding-bottom: .65rem; text-align: right; white-space: nowrap; }
.line-total span { color: var(--app-text-muted); font-size: .72rem; }
.quote-panel { margin-top: 14px; padding: 14px; border: 1px solid var(--quote-brand-border); border-radius: var(--radius-lg); background: var(--quote-brand-soft); color: var(--app-text); }
.quote-panel h4 { margin: 0 0 10px; color: var(--quote-brand-hover); font-size: .95rem; line-height: 1.3; font-weight: 700; }
.quote-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px 20px; font-size: .86rem; line-height: 1.4; }
.quote-grid > div { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; min-width: 0; }
.quote-grid > div > span { color: var(--app-text-muted); }
.quote-grid > div > strong { color: var(--app-text-strong); font-weight: 600; text-align: right; white-space: nowrap; }
.quote-grid .total { grid-column: 1 / -1; margin-top: 5px; padding-top: 9px; border-top: 1px solid var(--quote-brand-border); font-size: .93rem; }
.quote-grid .total span { color: var(--app-text); font-weight: 600; }
.quote-grid .total strong { color: var(--quote-brand-hover); font-size: 1.08rem; }
.quote-messages { display: flex; gap: 7px; margin-top: 10px; color: var(--app-text-muted); font-size: .8rem; line-height: 1.4; }
.quote-messages ul { margin: 0; padding-left: 16px; }
.quote-panel :deep(.p-message) { margin: 0 0 10px; padding: 8px 10px; font-size: .8rem; }
.empty-pricing { margin-top: 14px; padding: 12px 14px; border: 1px dashed var(--app-card-border); border-radius: var(--radius-md); color: var(--app-text-muted); font-size: .82rem; }
.generated-lines { margin-top: 14px; }
.generated-lines h4 { margin: 0 0 10px; color: var(--app-text-strong); font-size: .9rem; }
.summary-card { position: sticky; top: calc(var(--topbar-height) + var(--space-4)); display: grid; gap: var(--space-4); }
.summary-row, .operation-row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-3); }
.summary-row span, .operation-row span { color: var(--app-text-muted); font-size: .85rem; }
.summary-row.total { border-top: 1px solid var(--app-divider); padding-top: var(--space-4); font-size: 1.08rem; }
.deposit-box, .money-highlight { display: grid; gap: var(--space-1); padding: 14px; background: var(--quote-brand-soft); color: var(--quote-brand-hover); border: 1px solid var(--quote-brand-border); border-radius: var(--radius-lg); }
.deposit-box strong, .money-highlight strong { font-size: 1.35rem; }
.summary-note, .immutable-note { display: flex; gap: var(--space-2); margin: 0; color: var(--app-text-muted); font-size: .8rem; line-height: 1.5; }
.mobile-actions { display: none; }
.preview-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: var(--space-5); align-items: start; }
.operations-card { position: sticky; top: calc(var(--topbar-height) + var(--space-4)); display: grid; gap: var(--space-4); }
.payments-card { margin-top: var(--space-5); overflow: hidden; }
.payments-heading { margin-bottom: var(--space-4); }
.payments-table { overflow-x: auto; }
.review-receipts { margin-top: var(--space-5); padding-top: var(--space-4); border-top: 1px solid var(--app-divider); }
.review-receipts h3 { margin: 0 0 var(--space-3); font-size: .95rem; color: var(--app-on-tint-danger); }
.review-receipts article { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--space-3); padding: var(--space-4); margin-top: var(--space-3); border: 1px solid var(--app-tint-danger-border); border-radius: var(--radius-lg); background: var(--app-tint-danger); }
.review-receipts article > div { min-width: 0; display: flex; flex-direction: column; gap: var(--space-1); }
.review-receipts article span { color: var(--app-text-muted); font-size: .75rem; }
.review-receipts article strong { overflow-wrap: anywhere; }
.review-receipts article p { grid-column: 1 / -1; margin: 0; color: var(--app-on-tint-danger); font-size: .82rem; }
@media (max-width: 1120px) {
  .form-layout, .preview-layout { grid-template-columns: 1fr; }
  .summary-card, .operations-card { position: static; order: -1; }
  .line-row { grid-template-columns: 28px minmax(170px, 1fr) 90px 90px minmax(140px, 1fr) 120px 42px; overflow-x: auto; }
}
@media (max-width: 720px) {
  .form-card, .summary-card, .operations-card, .payments-card { padding: var(--space-4); border-radius: var(--radius-lg); }
  .field-grid, .field-grid.three { grid-template-columns: 1fr; }
  .quote-grid { grid-template-columns: 1fr; gap: 7px; }
  .quote-grid .total { grid-column: auto; }
  .span-2 { grid-column: auto; }
  .line-row { grid-template-columns: 28px 1fr 42px; align-items: end; }
  .line-row .field, .line-total { grid-column: 2 / -1; text-align: left; }
  .line-row > button { grid-column: 3; grid-row: 1; }
  .line-index { grid-column: 1; grid-row: 1; }
  .section-heading-actions { flex-wrap: wrap; }
  .section-heading-actions > :last-child { margin-left: 42px; }
  .status-strip { gap: var(--space-3); }
  .mobile-actions { display: grid; grid-template-columns: 1fr 1fr; position: sticky; bottom: 0; z-index: var(--z-sticky); padding: var(--space-3); margin: 0 calc(-1 * var(--space-3)); background: color-mix(in srgb, var(--app-bg), transparent 5%); border-top: 1px solid var(--app-card-border); backdrop-filter: blur(8px); }
  .review-receipts article { grid-template-columns: 1fr 1fr; }
}
</style>
