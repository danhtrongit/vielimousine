<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
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
import { useNotify } from '@/composables/useNotify';
import { useUIStore } from '@/stores/ui.store';
import { useAuthStore } from '@/stores/auth.store';
import { formatDateTime, formatVND, ymdLocal } from '@/composables/useFormat';
import type { BookingQuote, BookingQuoteDetail, BookingQuotePayload } from '@/types/bookingQuote';
import BookingQuotePreview from './BookingQuotePreview.vue';
import {
  createDefaultQuote,
  effectiveStatusLabels,
  lineTotal,
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

const quote = ref<BookingQuoteDetail | null>(null);
const form = ref<BookingQuotePayload>(createDefaultQuote());
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const loadError = ref(false);
const viewMode = ref<'edit' | 'preview'>('edit');
const skipNextRouteLoad = ref(false);

const isNew = computed(() => route.name === 'booking-quotes-new');
const quoteId = computed(() => isNew.value ? null : Number(route.params.id));
const canMutate = computed(() => auth.can('vie_create_booking_quotes'));
const editable = computed(() => (isNew.value || quote.value?.status === 'draft') && canMutate.value);
const amounts = computed(() => quoteAmounts(form.value));
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

function addLine() {
  if (form.value.lines.length >= 50) {
    notify.warn('Một báo giá có tối đa 50 dòng giá');
    return;
  }
  form.value.lines.push({ label: '', quantity: 1, unit: 'khách', unit_price: 0 });
}

function removeLine(index: number) {
  form.value.lines.splice(index, 1);
}

function validate(forPublish = false): boolean {
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
  if (saving.value) return null;
  if (!validate(false)) return null;
  saving.value = true;
  try {
    const response = quote.value
      ? await bookingQuotesApi.update(quote.value.id, form.value)
      : await bookingQuotesApi.create(form.value);
    quote.value = { ...response.data, payments: quote.value?.payments ?? [] };
    form.value = quoteToPayload(response.data);
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
  if (actionLoading.value || saving.value) return;
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
        <Button label="Lưu nháp" icon="pi pi-save" severity="secondary" :loading="saving" :disabled="actionLoading" @click="persistDraft()" />
        <Button label="Phát hành & sao chép link" icon="pi pi-send" :loading="actionLoading" :disabled="saving" @click="requestPublish" />
      </template>
      <template v-else-if="quote">
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
          <div class="field-grid">
            <div class="field span-2"><label for="quote-customer">Tên khách / đoàn <em>*</em></label><InputText id="quote-customer" v-model="form.customer_name" autocomplete="name" /></div>
            <div class="field"><label for="quote-phone">Điện thoại</label><InputText id="quote-phone" v-model="form.customer_phone" inputmode="tel" autocomplete="tel" /></div>
            <div class="field"><label for="quote-email">Email</label><InputText id="quote-email" v-model="form.customer_email" type="email" autocomplete="email" /></div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="trip-heading">
          <div class="section-heading"><span>2</span><div><h2 id="trip-heading">Chuyến đi / dịch vụ</h2><p>Nội dung khách sẽ thấy khi mở link.</p></div></div>
          <div class="field-grid">
            <div class="field span-2"><label for="quote-title">Tên dịch vụ / chuyến đi <em>*</em></label><InputText id="quote-title" v-model="form.title" placeholder="Ví dụ: Combo Hà Nội – Hạ Long 3N2Đ" /></div>
            <div class="field"><label for="trip-start">Ngày đi</label><DatePicker input-id="trip-start" v-model="tripStartDate" date-format="dd/mm/yy" show-icon /></div>
            <div class="field"><label for="trip-end">Ngày về</label><DatePicker input-id="trip-end" v-model="tripEndDate" date-format="dd/mm/yy" show-icon /></div>
            <div class="field span-2"><label for="quote-image">Ảnh đại diện (URL cùng website)</label><InputText id="quote-image" v-model="form.image_url" placeholder="/wp-content/uploads/..." /></div>
            <div class="field span-2"><label for="quote-greeting">Lời chào</label><Textarea id="quote-greeting" v-model="form.greeting" rows="3" auto-resize /></div>
            <div class="field span-2"><label for="quote-description">Mô tả</label><Textarea id="quote-description" v-model="form.description" rows="4" auto-resize /></div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="pricing-heading">
          <div class="section-heading section-heading-actions">
            <span>3</span><div><h2 id="pricing-heading">Bảng giá</h2><p>VND, số nguyên. Server sẽ tính lại toàn bộ thành tiền.</p></div>
            <Button label="Thêm dòng" icon="pi pi-plus" severity="secondary" outlined size="small" type="button" @click="addLine" />
          </div>
          <div class="line-list">
            <div v-for="(line, index) in form.lines" :key="index" class="line-row">
              <div class="line-index">{{ index + 1 }}</div>
              <div class="field line-label"><label :for="`line-label-${index}`">Nội dung</label><InputText :id="`line-label-${index}`" v-model="line.label" /></div>
              <div class="field quantity"><label :for="`line-qty-${index}`">Số lượng</label><InputNumber :input-id="`line-qty-${index}`" v-model="line.quantity" :min="1" :max="1000" :min-fraction-digits="0" :max-fraction-digits="0" /></div>
              <div class="field unit"><label :for="`line-unit-${index}`">Đơn vị</label><InputText :id="`line-unit-${index}`" v-model="line.unit" /></div>
              <div class="field price"><label :for="`line-price-${index}`">Đơn giá</label><InputNumber :input-id="`line-price-${index}`" v-model="line.unit_price" :min="0" :max="999999999999" :min-fraction-digits="0" :max-fraction-digits="0" suffix=" đ" :use-grouping="true" /></div>
              <div class="line-total"><span>Thành tiền</span><strong>{{ formatVND(lineTotal(line)) }}</strong></div>
              <Button icon="pi pi-trash" severity="danger" text rounded type="button" :aria-label="`Xóa dòng ${index + 1}`" @click="removeLine(index)" />
            </div>
          </div>
        </section>

        <section class="form-card" aria-labelledby="content-heading">
          <div class="section-heading"><span>4</span><div><h2 id="content-heading">Nội dung & điều kiện</h2><p>Mỗi ý nên xuống dòng để khách dễ đọc trên điện thoại.</p></div></div>
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
        <Button label="Lưu nháp" icon="pi pi-save" severity="secondary" :loading="saving" :disabled="actionLoading" type="submit" />
        <Button label="Phát hành" icon="pi pi-send" :loading="actionLoading" :disabled="saving" type="button" @click="requestPublish" />
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
.quote-page { max-width: var(--content-max-width); margin: 0 auto; }
.loading { min-height: 60vh; display: grid; place-content: center; justify-items: center; gap: var(--space-3); color: var(--app-text-muted); }
.review-warning { margin-bottom: var(--space-4); }
.status-strip { display: flex; flex-wrap: wrap; gap: var(--space-5); align-items: center; padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); border: 1px solid var(--app-card-border); border-radius: var(--radius-lg); background: var(--app-card-bg); }
.status-strip > div { display: flex; gap: var(--space-2); align-items: center; }
.status-strip span { color: var(--app-text-muted); font-size: .8rem; }
.form-layout { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: var(--space-5); align-items: start; }
.form-main { min-width: 0; display: grid; gap: var(--space-4); }
.form-card, .summary-card, .operations-card, .payments-card { background: var(--app-card-bg); border: 1px solid var(--app-card-border); border-radius: var(--radius-xl); padding: var(--space-5); box-shadow: var(--shadow-xs); }
.section-heading { display: flex; align-items: flex-start; gap: var(--space-3); margin-bottom: var(--space-5); }
.section-heading > span { flex: 0 0 30px; height: 30px; display: grid; place-items: center; color: var(--app-on-tint-primary); background: var(--app-tint-primary); border-radius: var(--radius-full); font-weight: 700; }
.section-heading h2, .summary-card h2, .operations-card h2, .payments-card h2 { margin: 0; font-size: 1.05rem; color: var(--app-text-strong); }
.section-heading p, .payments-heading p { margin: .25rem 0 0; font-size: .84rem; color: var(--app-text-muted); }
.section-heading-actions > :last-child { margin-left: auto; }
.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
.field-grid.three { grid-template-columns: repeat(3, 1fr); }
.span-2 { grid-column: 1 / -1; }
.field { display: flex; min-width: 0; flex-direction: column; gap: var(--space-2); }
.field label { color: var(--app-text); font-size: .82rem; font-weight: 500; }
.field em { color: var(--p-red-500); font-style: normal; }
.field :deep(.p-inputtext), .field :deep(.p-inputnumber), .field :deep(.p-datepicker), .field :deep(.p-select), .field :deep(textarea) { width: 100%; }
.line-list { display: grid; gap: var(--space-3); }
.line-row { display: grid; grid-template-columns: 28px minmax(180px, 1.6fr) 100px 100px minmax(150px, 1fr) 130px 42px; gap: var(--space-2); align-items: end; padding: var(--space-3); border: 1px solid var(--app-divider); border-radius: var(--radius-lg); background: var(--app-muted-bg); }
.line-index { align-self: center; display: grid; place-items: center; height: 28px; border-radius: var(--radius-full); background: var(--app-card-bg); color: var(--app-text-muted); font-size: .8rem; }
.line-total { display: flex; flex-direction: column; gap: var(--space-1); padding-bottom: .65rem; text-align: right; white-space: nowrap; }
.line-total span { color: var(--app-text-muted); font-size: .72rem; }
.summary-card { position: sticky; top: calc(var(--topbar-height) + var(--space-4)); display: grid; gap: var(--space-4); }
.summary-row, .operation-row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-3); }
.summary-row span, .operation-row span { color: var(--app-text-muted); font-size: .85rem; }
.summary-row.total { border-top: 1px solid var(--app-divider); padding-top: var(--space-4); font-size: 1.08rem; }
.deposit-box, .money-highlight { display: grid; gap: var(--space-1); padding: var(--space-4); background: var(--app-tint-primary); color: var(--app-on-tint-primary); border: 1px solid var(--app-tint-primary-border); border-radius: var(--radius-lg); }
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
