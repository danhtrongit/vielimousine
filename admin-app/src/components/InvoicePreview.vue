<script setup lang="ts">
import { computed } from 'vue';
import { formatVND, formatDate } from '@/composables/useFormat';

interface InvoiceData {
  order: Record<string, any>;
  items: Array<Record<string, any>>;
  settings: Record<string, any>;
  amount_in_words: string;
  template: 'receipt' | 'tax_invoice';
}

const props = defineProps<{ data: InvoiceData }>();

const order = computed(() => props.data.order);
const items = computed(() => props.data.items);
const cfg = computed(() => props.data.settings);
const isTax = computed(() => props.data.template === 'tax_invoice');
const isCancelled = computed(() => order.value.status === 'cancelled');

const issueDate = computed(() => formatDate(order.value.created_at));
const dayMonthYear = computed(() => {
  const d = new Date(order.value.created_at || Date.now());
  return { d: d.getDate(), m: d.getMonth() + 1, y: d.getFullYear() };
});

const totalsRaw = computed(() => {
  const o = order.value;
  const total = Number(o.total ?? 0);
  const discount = Number(o.discount ?? 0);
  const tax = Number(o.tax ?? 0);
  const paid = Number(o.paid_amount ?? 0);
  const vatRate = Number(cfg.value.vat_rate ?? 0);
  const subtotal = Number(o.subtotal ?? 0);

  // For tax invoice: split total → pre-tax + VAT
  let preTax = total - tax;
  let taxAmount = tax;
  if (isTax.value && taxAmount === 0 && vatRate > 0) {
    const base = Math.max(0, subtotal - discount);
    taxAmount = Math.round((base * vatRate) / 100);
    preTax = Math.max(0, total - taxAmount);
  }

  return {
    subtotal,
    discount,
    tax: taxAmount,
    preTax,
    total,
    paid,
    remaining: Math.max(0, total - paid),
    vatRate,
  };
});

function bookingTypeLabel(t: string) {
  return t === 'combo' ? 'Combo phòng + vé xe' : 'Chỉ phòng';
}

function unitPrice(it: any): number {
  const lt = Number(it.line_total ?? 0);
  const qty = Number(it.quantity ?? 1);
  return qty > 0 ? Math.round(lt / qty) : lt;
}
</script>

<template>
  <article class="invoice" :class="[`tpl-${data.template}`, { 'is-cancelled': isCancelled }]">
    <div v-if="isCancelled" class="watermark">ĐÃ HUỶ</div>

    <!-- ============== HEADER ============== -->
    <header class="head">
      <div class="head-l">
        <div class="company">{{ cfg.company_name || 'Công ty' }}</div>
        <div v-if="isTax && cfg.company_tax_id" class="meta">
          <span class="lbl">Mã số thuế:</span> {{ cfg.company_tax_id }}
        </div>
        <div v-if="cfg.company_address" class="meta">
          <span class="lbl">Địa chỉ:</span> {{ cfg.company_address }}
        </div>
        <div v-if="cfg.company_phone || cfg.company_email" class="meta">
          <span v-if="cfg.company_phone"><span class="lbl">Điện thoại:</span> {{ cfg.company_phone }}</span>
          <span v-if="cfg.company_phone && cfg.company_email" class="sep">·</span>
          <span v-if="cfg.company_email"><span class="lbl">Email:</span> {{ cfg.company_email }}</span>
        </div>
      </div>
      <div v-if="isTax" class="head-r">
        <div class="meta-r">Mẫu số: 01GTKT</div>
        <div class="meta-r">Ký hiệu: {{ (cfg.invoice_prefix || 'HD').toUpperCase() }}/{{ String(dayMonthYear.y).slice(-2) }}</div>
        <div class="meta-r">Số: <span class="inv-num">{{ order.invoice_number }}</span></div>
      </div>
    </header>

    <!-- ============== TITLE ============== -->
    <div class="title-block">
      <h1 class="doc-title">{{ isTax ? 'HOÁ ĐƠN BÁN HÀNG' : 'PHIẾU THU' }}</h1>
      <p v-if="isTax" class="doc-sub">
        Ngày {{ dayMonthYear.d }} tháng {{ dayMonthYear.m }} năm {{ dayMonthYear.y }}
      </p>
      <p v-else class="doc-sub">
        Số: <strong>{{ order.invoice_number }}</strong> · Ngày {{ issueDate }}
      </p>
    </div>

    <!-- ============== CUSTOMER ============== -->
    <section class="customer-block">
      <div class="kv-row">
        <div class="kv"><span class="kv-l">Mã đơn:</span><span class="kv-v">{{ order.code }}</span></div>
        <div class="kv"><span class="kv-l">Ngày đặt:</span><span class="kv-v">{{ issueDate }}</span></div>
      </div>
      <div class="kv-row">
        <div class="kv kv-wide">
          <span class="kv-l">{{ isTax ? 'Họ tên người mua:' : 'Khách hàng:' }}</span>
          <span class="kv-v">{{ order.customer_name }}</span>
        </div>
      </div>
      <div class="kv-row">
        <div class="kv"><span class="kv-l">Điện thoại:</span><span class="kv-v">{{ order.customer_phone }}</span></div>
        <div v-if="order.customer_email" class="kv">
          <span class="kv-l">Email:</span><span class="kv-v">{{ order.customer_email }}</span>
        </div>
      </div>
      <div v-if="isTax && order.customer_vat" class="kv-row">
        <div class="kv kv-wide"><span class="kv-l">Mã số thuế khách hàng:</span><span class="kv-v">{{ order.customer_vat }}</span></div>
      </div>
      <div class="kv-row">
        <div class="kv"><span class="kv-l">Nhận phòng:</span><span class="kv-v">{{ formatDate(order.checkin) }}</span></div>
        <div class="kv"><span class="kv-l">Trả phòng:</span><span class="kv-v">{{ formatDate(order.checkout) }}</span></div>
        <div class="kv"><span class="kv-l">Số đêm:</span><span class="kv-v">{{ order.nights }} đêm</span></div>
        <div class="kv">
          <span class="kv-l">Số khách:</span>
          <span class="kv-v">
            {{ order.adults }} người lớn<span v-if="order.children > 0">, {{ order.children }} trẻ em</span>
          </span>
        </div>
      </div>
    </section>

    <!-- ============== ITEMS ============== -->
    <table class="items">
      <thead>
        <tr>
          <th class="c-idx">Số thứ tự</th>
          <th class="c-name">Hạng mục</th>
          <th class="c-unit">Đơn vị tính</th>
          <th class="c-qty">Số lượng</th>
          <th v-if="isTax" class="c-unit-price">Đơn giá</th>
          <th v-if="isTax" class="c-vat">Thuế suất</th>
          <th class="c-total">Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(it, idx) in items" :key="it.id ?? idx">
          <td class="c-idx">{{ idx + 1 }}</td>
          <td class="c-name">
            <div class="row-hotel">{{ it.hotel_name || it.name }}</div>
            <div class="row-sub">
              <span>{{ it.name }}</span>
              <span class="dot">·</span>
              <span>{{ bookingTypeLabel(it.booking_type) }}</span>
              <span class="dot">·</span>
              <span>{{ it.nights }} đêm</span>
              <span v-if="isTax" class="dot">·</span>
              <span v-if="isTax">{{ formatDate(it.checkin) }} → {{ formatDate(it.checkout) }}</span>
            </div>
          </td>
          <td class="c-unit">Đêm</td>
          <td class="c-qty">{{ it.quantity }}</td>
          <td v-if="isTax" class="c-unit-price num">{{ formatVND(unitPrice(it)) }}</td>
          <td v-if="isTax" class="c-vat">{{ totalsRaw.vatRate }}%</td>
          <td class="c-total num">{{ formatVND(it.line_total) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- ============== TOTALS ============== -->
    <section class="totals-section">
      <table class="totals">
        <tr v-if="isTax">
          <td class="t-l">Cộng tiền hàng:</td>
          <td class="t-v">{{ formatVND(totalsRaw.preTax) }}</td>
        </tr>
        <tr v-else>
          <td class="t-l">Tạm tính:</td>
          <td class="t-v">{{ formatVND(totalsRaw.subtotal) }}</td>
        </tr>
        <tr v-if="totalsRaw.discount > 0">
          <td class="t-l">Giảm giá:</td>
          <td class="t-v">−{{ formatVND(totalsRaw.discount) }}</td>
        </tr>
        <tr v-if="isTax">
          <td class="t-l">Thuế GTGT ({{ totalsRaw.vatRate }}%):</td>
          <td class="t-v">{{ formatVND(totalsRaw.tax) }}</td>
        </tr>
        <tr class="t-grand">
          <td class="t-l">TỔNG CỘNG{{ isTax ? ' THANH TOÁN' : '' }}:</td>
          <td class="t-v">{{ formatVND(totalsRaw.total) }}</td>
        </tr>
        <tr v-if="!isTax">
          <td class="t-l">Đã thanh toán:</td>
          <td class="t-v t-paid">{{ formatVND(totalsRaw.paid) }}</td>
        </tr>
        <tr v-if="!isTax && totalsRaw.remaining > 0">
          <td class="t-l">Còn lại:</td>
          <td class="t-v t-remain">{{ formatVND(totalsRaw.remaining) }}</td>
        </tr>
      </table>
    </section>

    <!-- ============== WORDS + BANK (tax only) ============== -->
    <div v-if="isTax" class="words">
      <span class="words-lbl">Bằng chữ:</span> {{ data.amount_in_words }}
    </div>

    <section v-if="isTax && (cfg.bank_name || cfg.bank_account)" class="bank">
      <div class="bank-title">Thông tin chuyển khoản</div>
      <div class="bank-grid">
        <div v-if="cfg.bank_name"><span class="lbl">Ngân hàng:</span> {{ cfg.bank_name }}</div>
        <div v-if="cfg.bank_account"><span class="lbl">Số tài khoản:</span> {{ cfg.bank_account }}</div>
        <div v-if="cfg.bank_holder"><span class="lbl">Chủ tài khoản:</span> {{ cfg.bank_holder }}</div>
        <div><span class="lbl">Nội dung chuyển khoản:</span> <strong>{{ order.code }}</strong></div>
      </div>
    </section>

    <!-- ============== FOOTER NOTE ============== -->
    <p v-if="cfg.footer_note" class="footer-note">{{ cfg.footer_note }}</p>

    <!-- ============== SIGNATURES ============== -->
    <section class="signs" :class="{ 'signs-3': isTax, 'signs-2': !isTax }">
      <div class="sign-box">
        <div class="sign-role">{{ isTax ? 'Người mua hàng' : 'Người nộp tiền' }}</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      </div>
      <div class="sign-box">
        <div class="sign-role">{{ isTax ? 'Người bán hàng' : 'Người lập phiếu' }}</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      </div>
      <div v-if="isTax" class="sign-box">
        <div class="sign-role">Thủ trưởng đơn vị</div>
        <div class="sign-hint">(Ký, đóng dấu)</div>
      </div>
    </section>
  </article>
</template>

<style scoped>
/* ============================================================
   Professional monochrome invoice — no flashy colors.
   Designed to fit 1 page (A5 receipt, A4 tax) on screen + print.
   ============================================================ */
.invoice {
  background: #fff;
  color: #111827;
  font-family: 'Times New Roman', 'Liberation Serif', Times, serif;
  font-size: 11pt;
  line-height: 1.4;
  padding: 16mm 14mm;
  max-width: 210mm;
  margin: 0 auto;
  position: relative;
  box-sizing: border-box;
}
.tpl-receipt { max-width: 148mm; padding: 14mm 12mm; font-size: 10.5pt; }

/* Watermark */
.watermark {
  position: absolute; top: 38%; left: 0; right: 0;
  text-align: center; font-size: 72pt; font-weight: 700;
  color: rgba(0, 0, 0, 0.06); letter-spacing: 10px;
  transform: rotate(-22deg); pointer-events: none; z-index: 0;
}
.tpl-receipt .watermark { font-size: 56pt; }

/* Header */
.head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding-bottom: 6pt; border-bottom: 1.5pt solid #111827;
  margin-bottom: 10pt;
}
.head-l { flex: 1; }
.company {
  font-size: 13pt; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5pt; margin-bottom: 3pt;
}
.meta, .meta-r { font-size: 9.5pt; color: #374151; margin: 1pt 0; }
.meta .lbl, .bank .lbl { font-weight: 600; color: #111827; }
.meta .sep { margin: 0 6pt; color: #9ca3af; }
.head-r { text-align: right; min-width: 140pt; padding-left: 12pt; }
.meta-r .inv-num { font-weight: 700; color: #000; font-size: 11pt; }

/* Title */
.title-block { text-align: center; margin: 6pt 0 14pt; }
.doc-title {
  font-size: 18pt; font-weight: 700; margin: 0 0 4pt;
  letter-spacing: 2pt;
}
.tpl-receipt .doc-title { font-size: 16pt; letter-spacing: 1.5pt; }
.doc-sub { font-size: 10pt; color: #4b5563; margin: 0; }

/* Customer block */
.customer-block { margin-bottom: 10pt; }
.kv-row {
  display: flex; flex-wrap: wrap; gap: 4pt 14pt;
  padding: 2pt 0;
}
.kv { display: flex; gap: 4pt; font-size: 10pt; }
.kv-wide { width: 100%; }
.kv-l { color: #4b5563; min-width: 60pt; }
.kv-v { color: #111827; font-weight: 500; }

/* Items table */
.items {
  width: 100%; border-collapse: collapse; margin: 8pt 0;
  font-size: 10pt;
}
.items th, .items td {
  border: 0.5pt solid #111827;
  padding: 5pt 7pt;
  vertical-align: top;
}
.items th {
  background: transparent; font-weight: 700;
  text-align: center; border-bottom: 1pt solid #111827;
  border-top: 1pt solid #111827;
}
.items .c-idx { width: 50pt; text-align: center; }
.items .c-unit { width: 58pt; text-align: center; }
.items .c-qty { width: 50pt; text-align: center; }
.items .c-unit-price { width: 72pt; text-align: right; }
.items .c-vat { width: 56pt; text-align: center; }
.items .c-total { width: 84pt; text-align: right; }
.items th { white-space: nowrap; font-size: 9.5pt; }
.items td.num { text-align: right; font-variant-numeric: tabular-nums; }
.row-hotel { font-weight: 600; }
.row-sub { color: #6b7280; font-size: 9pt; margin-top: 1pt; }
.row-sub .dot { margin: 0 4pt; color: #9ca3af; }

/* Totals (right-aligned panel) */
.totals-section { display: flex; justify-content: flex-end; margin-top: 4pt; }
.totals { border-collapse: collapse; min-width: 50%; }
.totals td { padding: 3pt 8pt; font-size: 10.5pt; font-variant-numeric: tabular-nums; }
.totals .t-l { text-align: right; color: #374151; }
.totals .t-v { text-align: right; font-weight: 600; min-width: 100pt; }
.totals .t-grand td { border-top: 1pt solid #111827; padding-top: 5pt; font-size: 12pt; font-weight: 700; }
.totals .t-paid { color: #166534; }
.totals .t-remain { color: #991b1b; }

/* Words (tax invoice) */
.words {
  margin-top: 10pt; padding: 6pt 8pt;
  border-left: 2pt solid #111827; background: #f9fafb;
  font-size: 10pt; font-style: italic;
}
.words-lbl { font-style: normal; font-weight: 600; }

/* Bank info */
.bank {
  margin-top: 8pt; padding: 6pt 8pt;
  border: 0.5pt solid #d1d5db; border-radius: 2pt;
}
.bank-title { font-weight: 700; font-size: 10pt; margin-bottom: 3pt; }
.bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2pt 14pt; font-size: 9.5pt; }

/* Footer note */
.footer-note {
  text-align: center; font-style: italic; color: #6b7280;
  font-size: 9.5pt; margin: 12pt 0 0;
}

/* Signatures */
.signs {
  display: grid; gap: 12pt; margin-top: 18pt;
  page-break-inside: avoid;
}
.signs-2 { grid-template-columns: 1fr 1fr; }
.signs-3 { grid-template-columns: 1fr 1fr 1fr; }
.sign-box { text-align: center; }
.sign-role { font-weight: 700; text-transform: uppercase; font-size: 10pt; }
.sign-hint { color: #6b7280; font-style: italic; font-size: 9pt; margin-top: 1pt; }

/* ============================================================
   PRINT MEDIA
   Show ONLY the invoice; hide app shell, dialog chrome, sidebars.
   ============================================================ */
@media print {
  @page { size: A4 portrait; margin: 0; }
  @page :first { margin: 0; }
}
</style>

<style>
/* ============================================================
   Global print rules — UNSCOPED to reach body / dialog chrome.
   Strategy: hide siblings of the dialog mask + hide chrome inside,
   keep the path mask → dialog → content → invoice visible, then
   reset all containers' positioning/sizing so .invoice prints at
   top-left of the page with no overlap.
   ============================================================ */
@media print {
  @page { size: A4 portrait; margin: 0; }

  /* App shell: hide everything except the dialog overlay's root. */
  html, body {
    margin: 0 !important; padding: 0 !important; background: #fff !important;
    overflow: visible !important; height: auto !important;
  }
  body > *:not(.p-dialog-mask):not([data-pc-name="dialog"]) {
    display: none !important;
  }

  /* PrimeVue overlay (PrimeVue 4 uses .p-dialog-mask + .p-dialog).
     Reset positioning so nested invoice prints in normal flow. */
  .p-dialog-mask {
    position: static !important; inset: auto !important;
    background: transparent !important; overflow: visible !important;
    display: block !important; height: auto !important; width: auto !important;
    transform: none !important;
  }
  .p-dialog {
    position: static !important; width: auto !important; max-width: none !important;
    box-shadow: none !important; border: none !important; transform: none !important;
    margin: 0 !important;
  }
  .p-dialog-header, .p-dialog-footer { display: none !important; }
  .p-dialog-content { padding: 0 !important; overflow: visible !important; }

  /* Dialog grid → collapse, hide controls/banner. */
  .dlg-grid { display: block !important; }
  .dlg-controls, .cancelled-banner { display: none !important; }
  .dlg-preview {
    background: transparent !important;
    padding: 0 !important; max-height: none !important; overflow: visible !important;
    border-radius: 0 !important;
  }
  .preview-frame { box-shadow: none !important; }

  /* Invoice container: fill page comfortably. */
  .invoice {
    box-shadow: none !important;
    margin: 0 auto !important;
    padding: 14mm 12mm !important;
    max-width: 210mm !important;
  }
  .tpl-receipt {
    width: 148mm !important; max-width: 148mm !important;
    padding: 10mm 9mm !important;
  }
}
</style>
