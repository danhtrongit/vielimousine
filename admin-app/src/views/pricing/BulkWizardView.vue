<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Card from 'primevue/card';
import Steps from 'primevue/steps';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import Message from 'primevue/message';
import { useUIStore } from '@/stores/ui.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { roomPricesApi } from '@/api/roomPrices.api';
import { formatVND } from '@/composables/useFormat';

const router = useRouter();
const ui = useUIStore();
const lookup = useLookupStore();
const notify = useNotify();

ui.setBreadcrumb([
  { label: 'Bảng giá', to: '/pricing' },
  { label: 'Cập nhật hàng loạt' },
]);

const stepIndex = ref(0);
const steps = [
  { label: 'Phạm vi' },
  { label: 'Giá trị' },
  { label: 'Xác nhận' },
];

const today = new Date();
const defaultEnd = new Date(today.getTime() + 29 * 24 * 3600 * 1000);

const scope = ref({
  hotel_id: null as number | null,
  room_ids: [] as number[],
  date_from: today as Date,
  date_to: defaultEnd as Date,
  weekdays: [] as number[],
});

const values = ref({
  price: 1500000,
  extra_adult_price: 300000,
  stock: 10,
  is_active: true,
  source: 'manual' as 'manual' | 'weekday_rule' | 'holiday_override' | 'import',
});

const WEEKDAYS = [
  { label: 'Thứ 2', value: 1 },
  { label: 'Thứ 3', value: 2 },
  { label: 'Thứ 4', value: 3 },
  { label: 'Thứ 5', value: 4 },
  { label: 'Thứ 6', value: 5 },
  { label: 'Thứ 7', value: 6 },
  { label: 'Chủ nhật', value: 7 },
];

const SOURCES = [
  { label: 'Manual', value: 'manual' },
  { label: 'Theo ngày trong tuần', value: 'weekday_rule' },
  { label: 'Override ngày lễ', value: 'holiday_override' },
  { label: 'Import', value: 'import' },
];

const submitting = ref(false);

const availableRooms = computed(() =>
  scope.value.hotel_id === null ? [] : lookup.roomsByHotel(scope.value.hotel_id)
);

watch(() => scope.value.hotel_id, () => {
  scope.value.room_ids = [];
});

const dateCount = computed(() => {
  if (!scope.value.date_from || !scope.value.date_to) return 0;
  const filter = scope.value.weekdays.length > 0 ? scope.value.weekdays : null;
  let count = 0;
  const cursor = new Date(scope.value.date_from);
  const end = new Date(scope.value.date_to);
  while (cursor <= end) {
    const dow = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (filter === null || filter.includes(dow)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }
  return count;
});

const cellCount = computed(() => scope.value.room_ids.length * dateCount.value);

function nextStep() {
  if (stepIndex.value === 0) {
    if (!scope.value.hotel_id) { notify.warn('Chọn khách sạn'); return; }
    if (scope.value.room_ids.length === 0) { notify.warn('Chọn ít nhất 1 phòng'); return; }
    if (!scope.value.date_from || !scope.value.date_to) { notify.warn('Chọn ngày'); return; }
    if (cellCount.value === 0) { notify.warn('Không có ngày nào trong phạm vi'); return; }
  }
  stepIndex.value++;
}
function prevStep() { if (stepIndex.value > 0) stepIndex.value--; }

function fmtDate(d: Date): string {
  return d.toISOString().slice(0, 10);
}

onMounted(async () => {
  await lookup.ensureLoaded();
});

async function submit() {
  submitting.value = true;
  try {
    const resp = await roomPricesApi.bulk(
      {
        room_ids: scope.value.room_ids,
        date_from: fmtDate(scope.value.date_from),
        date_to: fmtDate(scope.value.date_to),
        weekdays: scope.value.weekdays.length > 0 ? scope.value.weekdays : null,
      },
      values.value
    );
    notify.success(
      'Đã cập nhật',
      `${resp.data.cells_count} ô (${resp.data.rooms_count} phòng × ${resp.data.dates_count} ngày)`
    );
    router.push('/pricing');
  } catch (e) {
    notify.apiError(e);
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <div>
    <h1 class="page-title">Cập nhật hàng loạt</h1>

    <Steps :model="steps" :active-step="stepIndex" :readonly="true" class="wizard-steps" />

    <Card>
      <template #content>
        <!-- Step 0: Scope -->
        <div v-if="stepIndex === 0" class="step-content">
          <h3>Phạm vi cập nhật</h3>
          <div class="grid-2">
            <div class="field">
              <label>Khách sạn</label>
              <Dropdown
                v-model="scope.hotel_id"
                :options="lookup.hotels"
                option-label="name"
                option-value="id"
                placeholder="Chọn khách sạn"
              />
            </div>
            <div class="field">
              <label>Phòng (chọn nhiều)</label>
              <MultiSelect
                v-model="scope.room_ids"
                :options="availableRooms"
                option-label="name"
                option-value="id"
                placeholder="Chọn phòng"
                display="chip"
                :max-selected-labels="3"
              />
            </div>
            <div class="field">
              <label>Từ ngày</label>
              <Calendar v-model="scope.date_from" date-format="yy-mm-dd" show-icon />
            </div>
            <div class="field">
              <label>Đến ngày</label>
              <Calendar v-model="scope.date_to" date-format="yy-mm-dd" show-icon />
            </div>
            <div class="field grid-span-2">
              <label>Lọc theo thứ (bỏ trống = mọi ngày)</label>
              <MultiSelect
                v-model="scope.weekdays"
                :options="WEEKDAYS"
                option-label="label"
                option-value="value"
                placeholder="Tất cả các ngày"
                display="chip"
              />
            </div>
          </div>
          <Message v-if="cellCount > 0" severity="info" :closable="false" class="preview-msg">
            Sẽ áp dụng cho <strong>{{ cellCount }}</strong> ô
            ({{ scope.room_ids.length }} phòng × {{ dateCount }} ngày)
          </Message>
        </div>

        <!-- Step 1: Values -->
        <div v-else-if="stepIndex === 1" class="step-content">
          <h3>Giá trị áp dụng</h3>
          <div class="grid-2">
            <div class="field">
              <label>Giá phòng (VND)</label>
              <InputNumber v-model="values.price" :min="0" />
            </div>
            <div class="field">
              <label>Phụ thu người lớn (VND)</label>
              <InputNumber v-model="values.extra_adult_price" :min="0" />
            </div>
            <div class="field">
              <label>Tồn phòng</label>
              <InputNumber v-model="values.stock" :min="0" show-buttons />
            </div>
            <div class="field">
              <label>Nguồn</label>
              <Dropdown
                v-model="values.source"
                :options="SOURCES"
                option-label="label"
                option-value="value"
              />
            </div>
            <div class="field">
              <label>Đang mở bán</label>
              <ToggleSwitch v-model="values.is_active" />
            </div>
          </div>
        </div>

        <!-- Step 2: Confirm -->
        <div v-else class="step-content">
          <h3>Xác nhận</h3>
          <Card>
            <template #content>
              <div class="confirm-kv">
                <span>Khách sạn:</span>
                <strong>{{ lookup.hotelById(scope.hotel_id!)?.name }}</strong>
              </div>
              <div class="confirm-kv">
                <span>Số phòng:</span>
                <strong>{{ scope.room_ids.length }}</strong>
              </div>
              <div class="confirm-kv">
                <span>Số ngày trong phạm vi:</span>
                <strong>{{ dateCount }}</strong>
              </div>
              <div class="confirm-kv confirm-total">
                <span>Tổng số ô sẽ cập nhật:</span>
                <strong>{{ cellCount }}</strong>
              </div>
              <hr />
              <div class="confirm-kv"><span>Giá phòng:</span> <strong>{{ formatVND(values.price) }}</strong></div>
              <div class="confirm-kv"><span>Phụ thu người lớn:</span> <strong>{{ formatVND(values.extra_adult_price) }}</strong></div>
              <div class="confirm-kv"><span>Tồn phòng:</span> <strong>{{ values.stock }}</strong></div>
              <div class="confirm-kv"><span>Nguồn:</span> {{ values.source }}</div>
              <div class="confirm-kv"><span>Trạng thái:</span> {{ values.is_active ? 'Mở bán' : 'Tạm ngưng' }}</div>
            </template>
          </Card>
        </div>
      </template>

      <template #footer>
        <div class="wizard-footer">
          <Button label="Quay lại" severity="secondary" outlined :disabled="stepIndex === 0 || submitting" @click="prevStep" />
          <Button
            v-if="stepIndex < steps.length - 1"
            label="Tiếp"
            icon="pi pi-arrow-right"
            icon-pos="right"
            @click="nextStep"
          />
          <Button
            v-else
            label="Áp dụng"
            icon="pi pi-check"
            severity="success"
            :loading="submitting"
            @click="submit"
          />
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 600; }
.wizard-steps { margin-bottom: 1.5rem; }
.step-content { padding: 1rem; min-height: 280px; }
.step-content h3 { margin: 0 0 1rem; font-size: 1.1rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.preview-msg { margin-top: 1.25rem; }
.confirm-kv { padding: 0.4rem 0; display: flex; justify-content: space-between; border-bottom: 1px dashed var(--p-surface-200); }
.confirm-kv:last-child { border-bottom: 0; }
.confirm-total { background: var(--p-primary-50); padding: 0.6rem 0.75rem; margin: 0.5rem 0; border-radius: 0.375rem; }
.confirm-total strong { color: var(--p-primary-700); font-size: 1.2rem; }
.wizard-footer { display: flex; justify-content: space-between; padding: 1rem; background: var(--p-surface-50); border-top: 1px solid var(--p-surface-200); }
</style>
