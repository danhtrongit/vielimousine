<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import ToggleSwitch from 'primevue/toggleswitch';
import Dropdown from 'primevue/dropdown';
import Chips from 'primevue/chips';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import EmailTemplateEditor from './EmailTemplateEditor.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import {
  settingsApi,
  type EmailConfig,
  type SepayConfig,
  type GeneralConfig,
} from '@/api/settings.api';

const ui = useUIStore();
const notify = useNotify();

const loading = ref(true);
const savingEmail = ref(false);
const savingSepay = ref(false);

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
    const [g, e, s] = await Promise.all([
      settingsApi.getGeneral(),
      settingsApi.getEmail(),
      settingsApi.getSepay(),
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
</script>

<template>
  <div>
    <h1 class="page-title">Cài đặt</h1>

    <div v-if="loading" class="loading">
      <ProgressSpinner style="width: 40px; height: 40px" />
    </div>

    <TabView v-else>
      <TabPanel value="general" header="Chung">
        <div class="general-grid" v-if="general">
          <div class="field"><label>Tên website</label><InputText :model-value="general.site_name" disabled /></div>
          <div class="field"><label>URL</label><InputText :model-value="general.site_url" disabled /></div>
          <div class="field"><label>Email admin</label><InputText :model-value="general.admin_email" disabled /></div>
          <div class="field"><label>Múi giờ</label><InputText :model-value="general.timezone" disabled /></div>
        </div>
        <p class="muted">Các thông tin trên đồng bộ từ WordPress (Settings → General). Đổi tại đó nếu cần.</p>
      </TabPanel>

      <TabPanel value="email" header="Email">
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

      <TabPanel value="sepay" header="SePay">
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
            <Dropdown v-model="sepayConfig.environment" :options="environmentOptions" option-label="label" option-value="value" />
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
    </TabView>
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
