<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';
import Chips from 'primevue/chips';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import ProgressSpinner from 'primevue/progressspinner';
import EmailTemplateEditor from './EmailTemplateEditor.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import {
  settingsApi,
  type EmailConfig,
  type SepayConfig,
  type GeneralConfig,
  type InvoiceConfig,
} from '@/api/settings.api';

const ui = useUIStore();
const notify = useNotify();

const loading = ref(true);
const savingEmail = ref(false);
const savingSepay = ref(false);
const syncingHotels = ref(false);

const general = ref<GeneralConfig | null>(null);

const emailConfig = reactive<EmailConfig>({
  from_name: '',
  from_email: '',
  reply_to: '',
  logo_url: '',
  admin_recipients: [],
  templates: {},
});
const templateKeys = ref<string[]>([]);

const sepayConfig = reactive<SepayConfig & { secret_key: string }>({
  enabled: false,
  merchant_id: '',
  secret_key: '',
  secret_key_set: false,
  environment: 'sandbox',
  auto_confirm_on_paid: true,
});

const savingInvoice = ref(false);
const invoiceConfig = reactive<InvoiceConfig>({
  company_name: '',
  company_tax_id: '',
  company_address: '',
  company_phone: '',
  company_email: '',
  bank_name: '',
  bank_account: '',
  bank_holder: '',
  logo_url: '',
  invoice_prefix: 'HD',
  invoice_format: '{prefix}-{year}-{seq:5}',
  next_seq: 1,
  reset_yearly: true,
  last_seq_year: null,
  vat_rate: 8,
  footer_note: '',
});

const TEMPLATE_LABELS: Record<string, string> = {
  pending_payment:    'Khách: Chờ thanh toán',
  paid:               'Khách: Đã thanh toán đủ',
  partial:            'Khách: Đã cọc 1 phần',
  confirmed:          'Khách: Đã xác nhận',
  completed:          'Khách: Hoàn tất chuyến đi',
  cancelled:          'Khách: Đã hủy',
  admin_notification: 'Admin: Đơn mới',
  admin_paid:         'Admin: Đã thu tiền',
  admin_cancelled:    'Admin: Đơn bị hủy',
};

const COMMON_PLACEHOLDERS = [
  'site_name', 'order_code', 'customer_name', 'customer_phone',
  'customer_email', 'total', 'paid_amount', 'remaining_amount',
  'checkin', 'checkout', 'nights', 'admin_url', 'lookup_url',
];

const environmentOptions = [
  { label: 'Sandbox (Test)', value: 'sandbox' },
  { label: 'Production',     value: 'production' },
];

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Cài đặt' }]);
  await loadAll();
});

async function loadAll() {
  loading.value = true;
  try {
    const [g, e, s, inv] = await Promise.all([
      settingsApi.getGeneral(),
      settingsApi.getEmail(),
      settingsApi.getSepay(),
      settingsApi.getInvoice(),
    ]);
    general.value = g.data;

    Object.assign(emailConfig, e.data.config);
    templateKeys.value = e.data.template_keys;

    sepayConfig.enabled              = s.data.enabled;
    sepayConfig.merchant_id          = s.data.merchant_id;
    sepayConfig.secret_key_set       = s.data.secret_key_set;
    sepayConfig.secret_key           = '';
    sepayConfig.environment          = s.data.environment;
    sepayConfig.auto_confirm_on_paid = s.data.auto_confirm_on_paid;

    Object.assign(invoiceConfig, inv.data.config);
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

async function saveEmail() {
  savingEmail.value = true;
  try {
    const resp = await settingsApi.updateEmail({
      from_name: emailConfig.from_name,
      from_email: emailConfig.from_email,
      reply_to: emailConfig.reply_to,
      logo_url: emailConfig.logo_url,
      admin_recipients: emailConfig.admin_recipients,
      templates: emailConfig.templates,
    });
    Object.assign(emailConfig, resp.data.config);
    notify.success('Đã lưu cài đặt email');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingEmail.value = false;
  }
}

async function saveSepay() {
  savingSepay.value = true;
  try {
    const body: any = {
      enabled: sepayConfig.enabled,
      merchant_id: sepayConfig.merchant_id,
      environment: sepayConfig.environment,
      auto_confirm_on_paid: sepayConfig.auto_confirm_on_paid,
    };
    if (sepayConfig.secret_key && sepayConfig.secret_key.trim() !== '') {
      body.secret_key = sepayConfig.secret_key;
    }
    const resp = await settingsApi.updateSepay(body);
    sepayConfig.enabled              = resp.data.enabled;
    sepayConfig.merchant_id          = resp.data.merchant_id;
    sepayConfig.secret_key_set       = resp.data.secret_key_set;
    sepayConfig.secret_key           = '';
    sepayConfig.environment          = resp.data.environment;
    sepayConfig.auto_confirm_on_paid = resp.data.auto_confirm_on_paid;
    notify.success('Đã lưu cài đặt SePay');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingSepay.value = false;
  }
}

async function saveInvoice() {
  savingInvoice.value = true;
  try {
    const resp = await settingsApi.updateInvoice({
      company_name: invoiceConfig.company_name,
      company_tax_id: invoiceConfig.company_tax_id,
      company_address: invoiceConfig.company_address,
      company_phone: invoiceConfig.company_phone,
      company_email: invoiceConfig.company_email,
      bank_name: invoiceConfig.bank_name,
      bank_account: invoiceConfig.bank_account,
      bank_holder: invoiceConfig.bank_holder,
      logo_url: invoiceConfig.logo_url,
      invoice_prefix: invoiceConfig.invoice_prefix,
      invoice_format: invoiceConfig.invoice_format,
      next_seq: invoiceConfig.next_seq,
      reset_yearly: invoiceConfig.reset_yearly,
      vat_rate: invoiceConfig.vat_rate,
      footer_note: invoiceConfig.footer_note,
    });
    Object.assign(invoiceConfig, resp.data.config);
    notify.success('Đã lưu cài đặt hoá đơn');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingInvoice.value = false;
  }
}

async function runHotelSync() {
  syncingHotels.value = true;
  try {
    const resp = await settingsApi.runHotelSync();
    notify.success(resp.data.message);
  } catch (e) {
    notify.apiError(e);
  } finally {
    syncingHotels.value = false;
  }
}
</script>

<template>
  <div>
    <h1 class="page-title">Cài đặt</h1>

    <div v-if="loading" class="loading">
      <ProgressSpinner style="width: 40px; height: 40px" />
    </div>

    <Tabs v-else value="general">
      <TabList>
        <Tab value="general">Chung</Tab>
        <Tab value="email">Email</Tab>
        <Tab value="invoice">Hoá đơn</Tab>
        <Tab value="sepay">SePay</Tab>
      </TabList>
      <TabPanels>
      <TabPanel value="general">
        <div class="general-grid" v-if="general">
          <div class="field"><label>Tên website</label><InputText :model-value="general.site_name" disabled /></div>
          <div class="field"><label>URL</label><InputText :model-value="general.site_url" disabled /></div>
          <div class="field"><label>Email admin</label><InputText :model-value="general.admin_email" disabled /></div>
          <div class="field"><label>Múi giờ</label><InputText :model-value="general.timezone" disabled /></div>
        </div>
        <p class="muted">Các thông tin trên đồng bộ từ WordPress (Settings → General). Đổi tại đó nếu cần.</p>

        <div class="section">
          <h3>Đồng bộ dữ liệu</h3>
          <p class="muted">
            Quét tất cả bài viết <code>post_type=hotel</code> và đồng bộ vào bảng <code>vie_hotel</code>.
            Tự động chạy lần đầu khi admin vào wp-admin; bấm để chạy lại bất kỳ lúc nào.
          </p>
          <Button label="Đồng bộ Hotel với WP Posts" icon="pi pi-sync" :loading="syncingHotels" @click="runHotelSync" />
        </div>
      </TabPanel>

      <TabPanel value="email">
        <div class="section">
          <h3>Người gửi</h3>
          <div class="grid-2">
            <div class="field"><label>Tên hiển thị</label><InputText v-model="emailConfig.from_name" /></div>
            <div class="field"><label>Email gửi</label><InputText v-model="emailConfig.from_email" /></div>
            <div class="field"><label>Reply-To (tùy chọn)</label><InputText v-model="emailConfig.reply_to" /></div>
            <div class="field"><label>Logo URL (tùy chọn)</label><InputText v-model="emailConfig.logo_url" /></div>
          </div>
          <div class="field" style="margin-top: 0.75rem">
            <label>Email admin nhận thông báo (Enter để thêm)</label>
            <Chips v-model="emailConfig.admin_recipients" separator="," />
          </div>
        </div>

        <div class="section">
          <h3>Mẫu email</h3>
          <p class="muted">Để trống Subject/Body để dùng mặc định từ template file. Toggle để tắt loại email tương ứng.</p>
          <EmailTemplateEditor
            v-for="key in templateKeys"
            :key="key"
            :template-key="key"
            :label="TEMPLATE_LABELS[key] || key"
            :placeholders="COMMON_PLACEHOLDERS"
            v-model="emailConfig.templates[key]"
          />
        </div>

        <div class="actions">
          <Button label="Lưu cài đặt email" icon="pi pi-save" :loading="savingEmail" @click="saveEmail" />
        </div>
      </TabPanel>

      <TabPanel value="invoice">
        <div class="section">
          <h3>Thông tin công ty</h3>
          <p class="muted">Hiển thị trên đầu mỗi hoá đơn xuất ra. Bắt buộc có tên công ty trước khi in lần đầu.</p>
          <div class="grid-2">
            <div class="field"><label>Tên công ty *</label><InputText v-model="invoiceConfig.company_name" /></div>
            <div class="field"><label>Mã số thuế</label><InputText v-model="invoiceConfig.company_tax_id" /></div>
            <div class="field grid-full"><label>Địa chỉ</label><InputText v-model="invoiceConfig.company_address" /></div>
            <div class="field"><label>Điện thoại</label><InputText v-model="invoiceConfig.company_phone" /></div>
            <div class="field"><label>Email</label><InputText v-model="invoiceConfig.company_email" /></div>
            <div class="field grid-full"><label>Logo URL (tuỳ chọn)</label><InputText v-model="invoiceConfig.logo_url" /></div>
          </div>
        </div>

        <div class="section">
          <h3>Tài khoản ngân hàng</h3>
          <p class="muted">Dùng cho mẫu Hoá đơn bán hàng (VAT) — phần "Thông tin chuyển khoản".</p>
          <div class="grid-2">
            <div class="field"><label>Tên ngân hàng</label><InputText v-model="invoiceConfig.bank_name" /></div>
            <div class="field"><label>Số tài khoản</label><InputText v-model="invoiceConfig.bank_account" /></div>
            <div class="field grid-full"><label>Chủ tài khoản</label><InputText v-model="invoiceConfig.bank_holder" /></div>
          </div>
        </div>

        <div class="section">
          <h3>Định dạng số hoá đơn</h3>
          <p class="muted">
            Tokens hỗ trợ: <code>{prefix}</code>, <code>{year}</code>, <code>{seq:N}</code> (N = số chữ số tối thiểu).
            VD: <code>HD-2026-00001</code>.
          </p>
          <div class="grid-2">
            <div class="field"><label>Tiền tố (prefix)</label><InputText v-model="invoiceConfig.invoice_prefix" /></div>
            <div class="field"><label>Mẫu định dạng</label><InputText v-model="invoiceConfig.invoice_format" /></div>
            <div class="field">
              <label>Số kế tiếp</label>
              <InputNumber v-model="invoiceConfig.next_seq" :min="1" show-buttons />
            </div>
            <div class="field" style="justify-content: center;">
              <label>&nbsp;</label>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <Checkbox v-model="invoiceConfig.reset_yearly" :binary="true" input-id="reset-yearly" />
                <label for="reset-yearly" style="font-size: 0.9rem;">Reset số khi sang năm mới</label>
              </div>
            </div>
          </div>
        </div>

        <div class="section">
          <h3>Thuế GTGT</h3>
          <div class="grid-2">
            <div class="field">
              <label>Thuế suất (%) cho mẫu Hoá đơn bán hàng</label>
              <InputNumber v-model="invoiceConfig.vat_rate" :min="0" :max="100" :max-fraction-digits="2" suffix=" %" />
            </div>
          </div>
        </div>

        <div class="section">
          <h3>Ghi chú chân hoá đơn</h3>
          <Textarea v-model="invoiceConfig.footer_note" rows="2" style="width: 100%;" />
        </div>

        <div class="actions">
          <Button label="Lưu cài đặt hoá đơn" icon="pi pi-save" :loading="savingInvoice" @click="saveInvoice" />
        </div>
      </TabPanel>

      <TabPanel value="sepay">
        <div class="grid-2">
          <div class="field">
            <label>Bật cổng SePay</label>
            <ToggleSwitch v-model="sepayConfig.enabled" />
          </div>
          <div class="field">
            <label>Auto-confirm khi đã thanh toán đủ</label>
            <ToggleSwitch v-model="sepayConfig.auto_confirm_on_paid" />
          </div>
          <div class="field">
            <label>Môi trường</label>
            <Select v-model="sepayConfig.environment" :options="environmentOptions" option-label="label" option-value="value" />
          </div>
          <div class="field">
            <label>Merchant ID</label>
            <InputText v-model="sepayConfig.merchant_id" />
          </div>
          <div class="field grid-full">
            <label>Secret Key {{ sepayConfig.secret_key_set ? '(đã lưu — bỏ trống nếu không đổi)' : '(chưa thiết lập)' }}</label>
            <Password v-model="sepayConfig.secret_key" toggle-mask :feedback="false" />
          </div>
        </div>

        <div class="actions">
          <Button label="Lưu cài đặt SePay" icon="pi pi-save" :loading="savingSepay" @click="saveSepay" />
        </div>
      </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 600; }
.loading { display: grid; place-items: center; min-height: 200px; }
.section { padding: 0.5rem 0 1rem; }
.section h3 { margin: 0 0 0.5rem; font-size: 1rem; font-weight: 600; }
.general-grid, .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.grid-full { grid-column: 1 / -1; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0 0 0.75rem; }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--p-surface-200); }
</style>
