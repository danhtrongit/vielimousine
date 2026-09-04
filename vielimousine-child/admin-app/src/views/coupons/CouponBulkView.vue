<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Card from 'primevue/card';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import Message from 'primevue/message';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '@/components/PageHeader.vue';
import { couponsApi, type Coupon, type CouponTemplate } from '@/api/coupons.api';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND, formatDateTime } from '@/composables/useFormat';
import {
  CODE_MAX_LENGTH,
  MAX_QUANTITY,
  MAX_RANDOM_LENGTH,
  MIN_RANDOM_LENGTH,
  normalizeAffix,
  validateBulkForm,
} from './couponBulkForm';

const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();
const csv = useCsvExport();

const BOOKING_TYPES = [
  { label: 'Phòng', value: 'room' },
  { label: 'Combo', value: 'combo' },
];
const TYPES = [
  { label: 'Phần trăm (%)', value: 'percentage' },
  { label: 'Số tiền cố định', value: 'fixed' },
];

const generating = ref(false);
const created = ref<Coupon[]>([]);

const quantity = ref(20);
const prefix = ref('VIE');
const suffix = ref('');
const randomLength = ref(8);

const template = ref<CouponTemplate>({
  description: '',
  type: 'percentage',
  value: 10,
  min_order: 0,
  max_discount: null,
  usage_limit: 1,
  usage_limit_per_user: 1,
  valid_from: null,
  valid_to: null,
  hotel_ids: [],
  room_ids: [],
  booking_types: [],
  is_active: true,
  sales_only: false,
});

function toMysql(v: Date | null): string | null {
  return v ? v.toISOString().slice(0, 19).replace('T', ' ') : null;
}

const validFromDate = computed({
  get: () => (template.value.valid_from ? new Date(template.value.valid_from) : null),
  set: (v: Date | null) => {
    template.value.valid_from = toMysql(v);
  },
});
const validToDate = computed({
  get: () => (template.value.valid_to ? new Date(template.value.valid_to) : null),
  set: (v: Date | null) => {
    template.value.valid_to = toMysql(v);
  },
});

const availableRooms = computed(() => {
  const ids = template.value.hotel_ids ?? [];
  if (ids.length === 0) return lookup.rooms;
  return lookup.rooms.filter((r) => ids.includes(r.hotel_id));
});

const normalizedPrefix = computed(() => normalizeAffix(prefix.value));
const normalizedSuffix = computed(() => normalizeAffix(suffix.value));

const codeLength = computed(
  () => normalizedPrefix.value.length + randomLength.value + normalizedSuffix.value.length,
);

/** Mẫu mã hiển thị cho user trước khi sinh — phần ngẫu nhiên là placeholder. */
const codePreview = computed(
  () => normalizedPrefix.value + 'X'.repeat(Math.max(0, randomLength.value)) + normalizedSuffix.value,
);

const formError = computed(() =>
  validateBulkForm({
    prefix: prefix.value,
    suffix: suffix.value,
    randomLength: randomLength.value,
    template: template.value,
  }),
);

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Mã giảm giá', to: '/coupons' }, { label: 'Tạo hàng loạt' }]);
  try {
    await lookup.ensureLoaded();
  } catch (e) {
    notify.apiError(e);
  }
});

async function generate() {
  if (formError.value) {
    notify.warn('Cấu hình chưa hợp lệ', formError.value);
    return;
  }
  generating.value = true;
  try {
    const resp = await couponsApi.bulkGenerate({
      quantity: quantity.value,
      prefix: normalizedPrefix.value,
      suffix: normalizedSuffix.value,
      random_length: randomLength.value,
      template: template.value,
    });
    created.value = resp.data.coupons;
    notify.success('Đã tạo mã hàng loạt', `${resp.data.created_count} mã`);
  } catch (e) {
    notify.apiError(e, 'Không tạo được mã hàng loạt');
  } finally {
    generating.value = false;
  }
}

function exportCsv() {
  try {
    csv.downloadCsv(
      `vie-coupons-${new Date().toISOString().slice(0, 10)}.csv`,
      ['Code', 'Loại', 'Giá trị', 'Giảm tối đa', 'Đơn tối thiểu', 'Lượt dùng', 'Hiệu lực từ', 'Hiệu lực đến'],
      created.value.map((c) => [
        c.code,
        c.type === 'percentage' ? 'Phần trăm' : 'Số tiền',
        c.type === 'percentage' ? `${c.value}%` : c.value,
        c.max_discount ?? '',
        c.min_order,
        c.usage_limit ?? '',
        c.valid_from ?? '',
        c.valid_to ?? '',
      ]),
    );
    notify.success('Đã xuất CSV', `${created.value.length} dòng`);
  } catch (e) {
    notify.apiError(e, 'Không xuất được CSV');
  }
}

function copyCodes() {
  const text = created.value.map((c) => c.code).join('\n');
  navigator.clipboard
    .writeText(text)
    .then(() => notify.success('Đã copy', `${created.value.length} mã`))
    .catch(() => notify.error('Không copy được', 'Trình duyệt từ chối truy cập clipboard'));
}
</script>

<template>
  <div>
    <PageHeader
      title="Tạo mã giảm giá hàng loạt"
      subtitle="Sinh nhiều mã dùng chung một cấu hình"
      icon="pi pi-ticket"
    >
      <Button label="Về danh sách" icon="pi pi-list" outlined @click="router.push('/coupons')" />
    </PageHeader>

    <Card>
      <template #title>Bộ sinh mã</template>
      <template #content>
        <div class="grid-2">
          <div class="field">
            <label>Số lượng mã <span class="required-mark">*</span></label>
            <InputNumber v-model="quantity" :min="1" :max="MAX_QUANTITY" show-buttons />
            <small class="hint">Tối đa {{ MAX_QUANTITY }} mã mỗi lần.</small>
          </div>
          <div class="field">
            <label>Độ dài phần ngẫu nhiên</label>
            <InputNumber
              v-model="randomLength"
              :min="MIN_RANDOM_LENGTH"
              :max="MAX_RANDOM_LENGTH"
              show-buttons
            />
            <small class="hint">Bảng chữ 32 ký tự, đã bỏ O/0 và I/1 cho dễ đọc.</small>
          </div>
          <div class="field">
            <label>Tiền tố</label>
            <InputText v-model="prefix" placeholder="VIE" />
          </div>
          <div class="field">
            <label>Hậu tố</label>
            <InputText v-model="suffix" placeholder="2026" />
          </div>
          <div class="field grid-span-2">
            <label>Mẫu mã</label>
            <code class="code-preview">{{ codePreview }}</code>
            <small class="hint">{{ codeLength }}/{{ CODE_MAX_LENGTH }} ký tự.</small>
          </div>
        </div>
      </template>
    </Card>

    <Card class="section">
      <template #title>Cấu hình áp cho mọi mã</template>
      <template #content>
        <div class="grid-2">
          <div class="field">
            <label>Loại giảm giá</label>
            <Select v-model="template.type" :options="TYPES" option-label="label" option-value="value" />
          </div>
          <div class="field">
            <label>Giá trị {{ template.type === 'percentage' ? '(%)' : '(VND)' }}</label>
            <InputNumber v-model="template.value" :min="0" />
          </div>
          <div class="field">
            <label>Giảm tối đa (VND) — chỉ áp dụng cho %</label>
            <InputNumber v-model="template.max_discount" :min="0" />
          </div>
          <div class="field">
            <label>Đơn tối thiểu (VND)</label>
            <InputNumber v-model="template.min_order" :min="0" />
          </div>
          <div class="field">
            <label>Số lượt dùng tối đa (mỗi mã)</label>
            <InputNumber v-model="template.usage_limit" :min="0" placeholder="Bỏ trống = không giới hạn" />
          </div>
          <div class="field">
            <label>Số lượt dùng / user</label>
            <InputNumber
              v-model="template.usage_limit_per_user"
              :min="0"
              placeholder="Bỏ trống = không giới hạn"
            />
          </div>
          <div class="field">
            <label>Hiệu lực từ</label>
            <DatePicker v-model="validFromDate" date-format="yy-mm-dd" show-icon show-time hour-format="24" />
          </div>
          <div class="field">
            <label>Hiệu lực đến</label>
            <DatePicker v-model="validToDate" date-format="yy-mm-dd" show-icon show-time hour-format="24" />
          </div>
          <div class="field grid-span-2">
            <label>Mô tả</label>
            <Textarea v-model="template.description" rows="2" placeholder="Khuyến mãi hè 2026" />
          </div>
          <div class="field">
            <label>Hoạt động</label>
            <ToggleSwitch v-model="template.is_active" />
          </div>
          <div class="field">
            <label>Chỉ sales dùng được</label>
            <ToggleSwitch v-model="template.sales_only" />
          </div>
          <div class="field grid-span-2">
            <label>Khách sạn áp dụng (bỏ trống = mọi khách sạn)</label>
            <MultiSelect
              v-model="template.hotel_ids"
              :options="lookup.hotels"
              option-label="name"
              option-value="id"
              display="chip"
              filter
            />
          </div>
          <div class="field grid-span-2">
            <label>Phòng áp dụng (bỏ trống = mọi phòng trong khách sạn được chọn)</label>
            <MultiSelect
              v-model="template.room_ids"
              :options="availableRooms"
              option-label="name"
              option-value="id"
              display="chip"
              filter
            />
          </div>
          <div class="field grid-span-2">
            <label>Loại đặt</label>
            <MultiSelect
              v-model="template.booking_types"
              :options="BOOKING_TYPES"
              option-label="label"
              option-value="value"
              display="chip"
              placeholder="Mọi loại"
            />
          </div>
        </div>

        <Message v-if="formError" severity="error" :closable="false" class="section">
          {{ formError }}
        </Message>
      </template>
      <template #footer>
        <div class="actions">
          <Button
            :label="`Sinh ${quantity} mã`"
            icon="pi pi-bolt"
            :loading="generating"
            :disabled="formError !== null"
            @click="generate"
          />
        </div>
      </template>
    </Card>

    <Card v-if="created.length > 0" class="section">
      <template #title>Đã tạo {{ created.length }} mã</template>
      <template #content>
        <div class="actions actions--start">
          <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
          <Button label="Copy danh sách mã" icon="pi pi-copy" outlined @click="copyCodes" />
        </div>
        <DataTable
          :value="created"
          data-key="id"
          paginator
          :rows="20"
          :rows-per-page-options="[20, 50, 100]"
          class="section"
        >
          <Column field="code" header="Code">
            <template #body="{ data }">
              <RouterLink :to="`/coupons/${data.id}`" class="link">{{ data.code }}</RouterLink>
            </template>
          </Column>
          <Column header="Giảm">
            <template #body="{ data }">
              {{ data.type === 'percentage' ? `${data.value}%` : formatVND(data.value) }}
            </template>
          </Column>
          <Column header="Đơn tối thiểu">
            <template #body="{ data }">
              {{ data.min_order > 0 ? formatVND(data.min_order) : '—' }}
            </template>
          </Column>
          <Column header="Lượt dùng">
            <template #body="{ data }">{{ data.used_count }}/{{ data.usage_limit ?? '∞' }}</template>
          </Column>
          <Column header="Hiệu lực">
            <template #body="{ data }">
              {{ data.valid_from ? formatDateTime(data.valid_from) : '—' }} →
              {{ data.valid_to ? formatDateTime(data.valid_to) : '—' }}
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.section { margin-top: 1rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.hint { color: var(--p-text-muted-color); font-size: 0.78rem; }
.code-preview {
  display: inline-block;
  padding: 0.45rem 0.7rem;
  border-radius: 0.375rem;
  background: var(--p-surface-100);
  font-family: ui-monospace, SFMono-Regular, monospace;
  letter-spacing: 0.08em;
}
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; }
.actions--start { justify-content: flex-start; }
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
@media (max-width: 720px) {
  .grid-2 { grid-template-columns: 1fr; }
  .grid-span-2 { grid-column: span 1; }
}
</style>
