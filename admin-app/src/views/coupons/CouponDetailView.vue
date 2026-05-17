<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Card from 'primevue/card';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import { couponsApi, couponUsageApi, type Coupon, type CouponUsage } from '@/api/coupons.api';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND, formatDateTime } from '@/composables/useFormat';

const route = useRoute();
const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();

const isNew = computed(() => route.params.id === 'new');
const id = computed(() => (isNew.value ? 0 : Number(route.params.id)));

const loading = ref(true);
const saving = ref(false);
const coupon = ref<Partial<Coupon>>({
  code: '',
  description: '',
  type: 'percentage',
  value: 10,
  min_order: 0,
  max_discount: null,
  usage_limit: null,
  usage_limit_per_user: null,
  valid_from: null,
  valid_to: null,
  hotel_ids: [],
  room_ids: [],
  booking_types: [],
  is_active: true,
  sales_only: false,
});
const usage = ref<CouponUsage[]>([]);

const validFromDate = computed({
  get: () => (coupon.value.valid_from ? new Date(coupon.value.valid_from) : null),
  set: (v: Date | null) => {
    coupon.value.valid_from = v ? v.toISOString().slice(0, 19).replace('T', ' ') : null;
  },
});
const validToDate = computed({
  get: () => (coupon.value.valid_to ? new Date(coupon.value.valid_to) : null),
  set: (v: Date | null) => {
    coupon.value.valid_to = v ? v.toISOString().slice(0, 19).replace('T', ' ') : null;
  },
});

const availableRooms = computed(() => {
  const ids = coupon.value.hotel_ids ?? [];
  if (ids.length === 0) return lookup.rooms;
  return lookup.rooms.filter((r) => ids.includes(r.hotel_id));
});

async function load() {
  loading.value = true;
  try {
    await lookup.ensureLoaded();
    if (!isNew.value) {
      const resp = await couponsApi.get(id.value);
      coupon.value = resp.data;
      ui.setBreadcrumb([
        { label: 'Mã giảm giá', to: '/coupons' },
        { label: resp.data.code },
      ]);

      const usageResp = await couponUsageApi.list({ coupon_id: id.value, per_page: 100 });
      usage.value = usageResp.data;
    } else {
      ui.setBreadcrumb([
        { label: 'Mã giảm giá', to: '/coupons' },
        { label: 'Tạo mới' },
      ]);
    }
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function save() {
  if (!coupon.value.code || !coupon.value.type || coupon.value.value === undefined) {
    notify.warn('Vui lòng nhập đủ Code, Loại, Giá trị');
    return;
  }
  saving.value = true;
  try {
    if (isNew.value) {
      const resp = await couponsApi.create(coupon.value);
      notify.success('Đã tạo mã');
      router.replace(`/coupons/${resp.data.id}`);
    } else {
      await couponsApi.update(id.value, coupon.value);
      notify.success('Đã lưu');
      await load();
    }
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

const BOOKING_TYPES = [
  { label: 'Phòng', value: 'room' },
  { label: 'Combo', value: 'combo' },
];
const TYPES = [
  { label: 'Phần trăm (%)', value: 'percentage' },
  { label: 'Số tiền cố định', value: 'fixed' },
];
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else>
    <h1 class="page-title">{{ isNew ? 'Tạo mã giảm giá' : coupon.code }}</h1>

    <Tabs value="info">
      <TabList>
        <Tab value="info">Thông tin</Tab>
        <Tab value="scope" :disabled="isNew">Phạm vi</Tab>
        <Tab value="usage" :disabled="isNew">Lịch sử dùng</Tab>
      </TabList>
      <TabPanels>
      <TabPanel value="info">
        <Card>
          <template #content>
            <div class="grid-2">
              <div class="field">
                <label>Code <span style="color: red">*</span></label>
                <InputText v-model="coupon.code" :disabled="!isNew" placeholder="SUMMER10" />
              </div>
              <div class="field">
                <label>Loại giảm giá</label>
                <Select v-model="coupon.type" :options="TYPES" option-label="label" option-value="value" />
              </div>
              <div class="field">
                <label>Giá trị {{ coupon.type === 'percentage' ? '(%)' : '(VND)' }}</label>
                <InputNumber v-model="coupon.value" :min="0" />
              </div>
              <div class="field">
                <label>Giảm tối đa (VND) — chỉ áp dụng cho %</label>
                <InputNumber v-model="coupon.max_discount" :min="0" />
              </div>
              <div class="field">
                <label>Đơn tối thiểu (VND)</label>
                <InputNumber v-model="coupon.min_order" :min="0" />
              </div>
              <div class="field">
                <label>Số lượt dùng tối đa (toàn cục)</label>
                <InputNumber v-model="coupon.usage_limit" :min="0" placeholder="Bỏ trống = không giới hạn" />
              </div>
              <div class="field">
                <label>Số lượt dùng / user</label>
                <InputNumber v-model="coupon.usage_limit_per_user" :min="0" placeholder="Bỏ trống = không giới hạn" />
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
                <Textarea v-model="coupon.description" rows="2" />
              </div>
              <div class="field">
                <label>Hoạt động</label>
                <ToggleSwitch v-model="coupon.is_active" />
              </div>
              <div class="field">
                <label>Chỉ sales dùng được</label>
                <ToggleSwitch v-model="coupon.sales_only" />
              </div>
            </div>
          </template>
        </Card>
        <div class="actions">
          <Button :label="isNew ? 'Tạo' : 'Lưu'" icon="pi pi-save" :loading="saving" @click="save" />
        </div>
      </TabPanel>

      <TabPanel value="scope">
        <Card>
          <template #content>
            <div class="field">
              <label>Khách sạn áp dụng (bỏ trống = mọi khách sạn)</label>
              <MultiSelect
                v-model="coupon.hotel_ids"
                :options="lookup.hotels"
                option-label="name"
                option-value="id"
                display="chip"
              />
            </div>
            <div class="field">
              <label>Phòng áp dụng (bỏ trống = mọi phòng trong khách sạn được chọn)</label>
              <MultiSelect
                v-model="coupon.room_ids"
                :options="availableRooms"
                option-label="name"
                option-value="id"
                display="chip"
              />
            </div>
            <div class="field">
              <label>Loại đặt</label>
              <MultiSelect
                v-model="coupon.booking_types"
                :options="BOOKING_TYPES"
                option-label="label"
                option-value="value"
                display="chip"
                placeholder="Mọi loại"
              />
            </div>
          </template>
        </Card>
        <div class="actions">
          <Button label="Lưu phạm vi" icon="pi pi-save" :loading="saving" @click="save" />
        </div>
      </TabPanel>

      <TabPanel value="usage">
        <DataTable :value="usage" :empty-message="'Chưa có lượt dùng'" data-key="id">
          <Column field="used_at" header="Thời gian">
            <template #body="{ data }">{{ formatDateTime(data.used_at) }}</template>
          </Column>
          <Column field="order_id" header="Đơn hàng">
            <template #body="{ data }">
              <RouterLink :to="`/orders/${data.order_id}`">#{{ data.order_id }}</RouterLink>
            </template>
          </Column>
          <Column field="user_email" header="Email">
            <template #body="{ data }">{{ data.user_email ?? '—' }}</template>
          </Column>
          <Column field="discount" header="Giảm">
            <template #body="{ data }">{{ formatVND(data.discount) }}</template>
          </Column>
        </DataTable>
      </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.page-title { margin: 0 0 1.25rem; font-size: 1.5rem; font-weight: 600; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.actions { display: flex; justify-content: flex-end; margin-top: 1rem; }
</style>
