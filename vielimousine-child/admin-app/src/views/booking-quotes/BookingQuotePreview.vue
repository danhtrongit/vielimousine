<script setup lang="ts">
import { computed } from 'vue';
import Tag from 'primevue/tag';
import type { BookingQuote, BookingQuotePayload } from '@/types/bookingQuote';
import { formatDate, formatVND } from '@/composables/useFormat';
import { effectiveStatusLabels, lineTotal, quoteAmounts } from './bookingQuoteForm';

const props = defineProps<{
  model: BookingQuotePayload;
  quote?: BookingQuote | null;
}>();

const amounts = computed(() => props.quote
  ? { subtotal: props.quote.subtotal, total: props.quote.total, deposit: props.quote.deposit_amount, remaining: props.quote.remaining_amount }
  : quoteAmounts(props.model));
const statusSeverity = computed(() => {
  switch (props.quote?.effective_status) {
    case 'published': return 'success';
    case 'expired': return 'warn';
    case 'revoked': return 'danger';
    default: return 'secondary';
  }
});
</script>

<template>
  <article class="quote-preview" aria-label="Xem trước báo giá">
    <header class="preview-head">
      <div>
        <div class="eyebrow">VIE LIMO · BÁO GIÁ DỊCH VỤ</div>
        <h2>{{ model.title || 'Tên dịch vụ / chuyến đi' }}</h2>
        <p v-if="quote?.code" class="code">Mã {{ quote.code }}</p>
      </div>
      <Tag
        :value="quote ? effectiveStatusLabels[quote.effective_status] : 'Bản xem trước'"
        :severity="statusSeverity"
      />
    </header>

    <section class="customer-block">
      <p>Kính gửi <strong>{{ model.customer_name || 'Quý khách' }}</strong>,</p>
      <p class="preline">{{ model.greeting }}</p>
    </section>

    <div class="trip-meta">
      <div><span>Ngày đi</span><strong>{{ formatDate(model.trip_start) }}</strong></div>
      <div><span>Ngày về</span><strong>{{ formatDate(model.trip_end) }}</strong></div>
      <div><span>Hiệu lực đến</span><strong>{{ formatDate(model.valid_until) }}</strong></div>
    </div>

    <p v-if="model.description" class="preline description">{{ model.description }}</p>

    <div class="price-table-wrap">
      <table class="price-table">
        <thead>
          <tr><th>Nội dung</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr>
        </thead>
        <tbody>
          <tr v-for="(line, index) in model.lines" :key="index">
            <td>{{ line.label || 'Dòng giá' }} <small v-if="line.unit">/ {{ line.unit }}</small></td>
            <td>{{ line.quantity }}</td>
            <td>{{ formatVND(line.unit_price) }}</td>
            <td><strong>{{ formatVND(props.quote ? line.line_total : lineTotal(line)) }}</strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="summary">
      <div><span>Tạm tính</span><strong>{{ formatVND(amounts.subtotal) }}</strong></div>
      <div v-if="model.discount > 0"><span>Giảm giá</span><strong>− {{ formatVND(model.discount) }}</strong></div>
      <div class="grand-total"><span>Tổng cộng</span><strong>{{ formatVND(amounts.total) }}</strong></div>
      <div class="deposit"><span>Tiền cọc cần thanh toán</span><strong>{{ formatVND(amounts.deposit) }}</strong></div>
      <div><span>Còn lại sau cọc</span><strong>{{ formatVND(amounts.remaining) }}</strong></div>
    </div>

    <div class="details-grid">
      <section v-if="model.inclusions"><h3>Bao gồm</h3><p class="preline">{{ model.inclusions }}</p></section>
      <section v-if="model.exclusions"><h3>Không bao gồm</h3><p class="preline">{{ model.exclusions }}</p></section>
      <section v-if="model.terms" class="full"><h3>Điều kiện</h3><p class="preline">{{ model.terms }}</p></section>
    </div>

    <footer v-if="model.contact_name || model.contact_phone || model.contact_zalo" class="contact">
      <strong>{{ model.contact_name || 'Vie Limo' }}</strong>
      <span v-if="model.contact_phone">Điện thoại: {{ model.contact_phone }}</span>
      <span v-if="model.contact_zalo">Zalo: {{ model.contact_zalo }}</span>
    </footer>
  </article>
</template>

<style scoped>
.quote-preview {
  max-width: 880px;
  margin: 0 auto;
  padding: clamp(1rem, 3vw, 2.25rem);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-xl);
  background: var(--app-card-bg);
  box-shadow: var(--shadow-sm);
  color: var(--app-text);
}
.preview-head { display: flex; justify-content: space-between; gap: var(--space-4); align-items: flex-start; border-bottom: 2px solid var(--p-primary-500); padding-bottom: var(--space-4); }
.eyebrow { color: var(--p-primary-600); font-size: .72rem; letter-spacing: .12em; font-weight: 700; }
h2 { margin: .35rem 0 0; font-size: clamp(1.35rem, 3vw, 2rem); color: var(--app-text-strong); }
.code { margin: .25rem 0 0; color: var(--app-text-muted); }
.customer-block { padding: var(--space-5) 0; }
.customer-block p { margin: 0 0 .55rem; }
.preline { white-space: pre-line; line-height: 1.65; }
.trip-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3); padding: var(--space-4); background: var(--app-muted-bg); border-radius: var(--radius-lg); }
.trip-meta div { display: flex; flex-direction: column; gap: var(--space-1); }
.trip-meta span { font-size: .75rem; color: var(--app-text-muted); }
.description { margin: var(--space-5) 0; }
.price-table-wrap { overflow-x: auto; margin-top: var(--space-5); }
.price-table { width: 100%; border-collapse: collapse; min-width: 560px; }
.price-table th, .price-table td { text-align: left; padding: .8rem .65rem; border-bottom: 1px solid var(--app-divider); }
.price-table th { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--app-text-muted); }
.price-table th:nth-child(n+2), .price-table td:nth-child(n+2) { text-align: right; }
.price-table small { color: var(--app-text-muted); }
.summary { margin: var(--space-5) 0 0 auto; max-width: 430px; display: grid; gap: var(--space-2); }
.summary > div { display: flex; justify-content: space-between; gap: var(--space-4); }
.summary .grand-total { font-size: 1.15rem; border-top: 1px solid var(--app-card-border); padding-top: var(--space-3); }
.summary .deposit { padding: var(--space-3); margin: var(--space-1) calc(-1 * var(--space-3)); color: var(--app-on-tint-primary); background: var(--app-tint-primary); border-radius: var(--radius-md); }
.details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-top: var(--space-7); }
.details-grid section { padding-top: var(--space-4); border-top: 1px solid var(--app-divider); }
.details-grid .full { grid-column: 1 / -1; }
.details-grid h3 { margin: 0 0 var(--space-2); font-size: 1rem; }
.details-grid p { margin: 0; color: var(--app-text-muted); }
.contact { margin-top: var(--space-6); padding: var(--space-4); display: flex; flex-wrap: wrap; gap: var(--space-3); background: var(--app-muted-bg); border-radius: var(--radius-lg); }
@media (max-width: 640px) {
  .trip-meta, .details-grid { grid-template-columns: 1fr; }
  .details-grid .full { grid-column: auto; }
  .preview-head { align-items: flex-start; }
}
</style>
