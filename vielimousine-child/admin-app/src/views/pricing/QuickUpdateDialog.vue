<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';
import SelectButton from 'primevue/selectbutton';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import { useLookupStore } from '@/stores/lookup.store';
import { useNotify } from '@/composables/useNotify';
import { roomPricesApi } from '@/api/roomPrices.api';
import { ymdLocal, formatVND, formatCompact } from '@/composables/useFormat';

type Kind = 'room_price' | 'extra_adult_price' | 'room_stock';

const props = defineProps<{
  visible: boolean;
  defaultHotelIds: number[];
  defaultDateFrom: string; // YYYY-MM-DD
  defaultDateTo: string;   // YYYY-MM-DD
  pendingCount: number;
}>();

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void;
  (e: 'applied'): void;
}>();

const lookup = useLookupStore();
const notify = useNotify();

const KIND_OPTIONS = [
  { value: 'room_price' as Kind,        label: 'Giá phòng',         icon: 'pi pi-home',      helper: 'Giá phòng theo phòng / ngày' },
  { value: 'extra_adult_price' as Kind, label: 'PT người lớn',      icon: 'pi pi-user-plus', helper: 'Phụ thu mỗi người lớn vượt định mức / ngày' },
  { value: 'room_stock' as Kind,        label: 'Tồn kho',           icon: 'pi pi-box',       helper: 'Số phòng còn bán theo ngày' },
];

const WEEKDAYS = [
  { label: 'T2', value: 1 },
  { label: 'T3', value: 2 },
  { label: 'T4', value: 3 },
  { label: 'T5', value: 4 },
  { label: 'T6', value: 5 },
  { label: 'T7', value: 6 },
  { label: 'CN', value: 7 },
];

const WEEKDAYS_ONLY = [1, 2, 3, 4, 5];
const WEEKENDS_ONLY = [6, 7];

const CONFIRM_THRESHOLD = 5000;
// Backend validation caps each bulk request — see inc/src/Validation/Schemas/RoomPriceBulkValidation.php
const ROOM_IDS_CHUNK = 100;

function chunk<T>(arr: T[], size: number): T[][] {
  if (size <= 0 || arr.length <= size) return [arr];
  const out: T[][] = [];
  for (let i = 0; i < arr.length; i += size) out.push(arr.slice(i, i + size));
  return out;
}

const kind = ref<Kind>('room_price');
const hotelIds = ref<number[]>([]);
const roomIds = ref<number[]>([]);
const dateFrom = ref<Date>(new Date());
const dateTo = ref<Date>(new Date());
const weekdays = ref<number[]>([]);
const value = ref<number>(0);
const submitting = ref(false);
const showConfirm = ref(false);
const progress = ref<{ done: number; total: number } | null>(null);

// Re-init form whenever the dialog is opened
watch(() => props.visible, (v) => {
  if (!v) return;
  kind.value = 'room_price';
  hotelIds.value = [...props.defaultHotelIds];
  roomIds.value = [];
  dateFrom.value = parseDate(props.defaultDateFrom);
  dateTo.value = parseDate(props.defaultDateTo);
  weekdays.value = [];
  value.value = 0;
  submitting.value = false;
  showConfirm.value = false;
  progress.value = null;
});

// When hotel selection changes, prune incompatible room selections
watch(hotelIds, () => {
  const allowedRoomIds = new Set(availableRooms.value.map((r) => r.id));
  roomIds.value = roomIds.value.filter((id) => allowedRoomIds.has(id));
});

function parseDate(s: string): Date {
  const [y, m, d] = s.split('-').map(Number);
  return new Date(y, (m || 1) - 1, d || 1);
}

// Hotels are always the top filter; rooms inherit from selected hotels (or all hotels if none)
const effectiveHotelIds = computed<number[]>(() =>
  hotelIds.value.length > 0 ? hotelIds.value : lookup.hotels.map((h) => h.id),
);

const availableRooms = computed(() => {
  const hset = new Set(effectiveHotelIds.value);
  return lookup.rooms
    .filter((r) => hset.has(r.hotel_id) && r.is_active)
    .sort((a, b) => a.name.localeCompare(b.name));
});

const effectiveRoomIds = computed<number[]>(() =>
  roomIds.value.length > 0 ? roomIds.value : availableRooms.value.map((r) => r.id),
);

// Date / weekday counting
const dateCount = computed(() => {
  if (!dateFrom.value || !dateTo.value) return 0;
  const filter = weekdays.value.length > 0 ? new Set(weekdays.value) : null;
  let count = 0;
  const cursor = new Date(dateFrom.value);
  const end = new Date(dateTo.value);
  if (cursor > end) return 0;
  while (cursor <= end) {
    const dow = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (filter === null || filter.has(dow)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }
  return count;
});

const targetCount = computed(() => effectiveRoomIds.value.length);

const cellCount = computed(() => targetCount.value * dateCount.value);

const targetLabel = computed(() => 'phòng');

const valueLabel = computed(() => {
  if (kind.value === 'room_stock') return 'Số phòng tồn';
  if (kind.value === 'extra_adult_price') return 'Phụ thu người lớn (VND)';
  return 'Giá phòng (VND)';
});

const valueIsCurrency = computed(() => kind.value !== 'room_stock');

const valueDisplay = computed(() =>
  valueIsCurrency.value ? formatVND(value.value) : `${value.value} phòng`,
);

const canSubmit = computed(() => {
  if (submitting.value) return false;
  if (!dateFrom.value || !dateTo.value) return false;
  if (dateFrom.value > dateTo.value) return false;
  if (dateCount.value === 0) return false;
  if (targetCount.value === 0) return false;
  if (value.value < 0) return false;
  return true;
});

const helperMsg = computed(() => KIND_OPTIONS.find((o) => o.value === kind.value)?.helper ?? '');

// Weekday quick-pick state
function setWeekendOnly()  { weekdays.value = [...WEEKENDS_ONLY]; }
function setWeekdayOnly()  { weekdays.value = [...WEEKDAYS_ONLY]; }
function setAllDays()      { weekdays.value = []; }

const weekdayMode = computed<'all' | 'weekend' | 'weekday' | 'custom'>(() => {
  if (weekdays.value.length === 0) return 'all';
  const set = new Set(weekdays.value);
  if (set.size === 2 && set.has(6) && set.has(7)) return 'weekend';
  if (set.size === 5 && WEEKDAYS_ONLY.every((d) => set.has(d))) return 'weekday';
  return 'custom';
});

function close() {
  if (submitting.value) return;
  emit('update:visible', false);
}

async function onSubmitClick() {
  if (!canSubmit.value) return;
  if (cellCount.value > CONFIRM_THRESHOLD) {
    showConfirm.value = true;
    return;
  }
  await doSubmit();
}

async function doSubmit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  const from = ymdLocal(dateFrom.value);
  const to = ymdLocal(dateTo.value);
  const wd = weekdays.value.length > 0 ? [...weekdays.value] : null;

  try {
    let totalCells = 0;
    let totalTargets = 0;

    const batches = chunk(effectiveRoomIds.value, ROOM_IDS_CHUNK);
    progress.value = { done: 0, total: batches.length };
    const values = kind.value === 'room_stock'
      ? { stock: value.value, is_active: true, source: 'manual' as const }
      : kind.value === 'extra_adult_price'
      ? { extra_adult_price: value.value, is_active: true, source: 'manual' as const }
      : { price: value.value, is_active: true, source: 'manual' as const };
    const successMsg = kind.value === 'room_stock'
      ? 'Đã cập nhật tồn kho'
      : kind.value === 'extra_adult_price'
      ? 'Đã cập nhật phụ thu người lớn'
      : 'Đã cập nhật giá phòng';
    for (const ids of batches) {
      const resp = await roomPricesApi.bulk(
        { room_ids: ids, date_from: from, date_to: to, weekdays: wd },
        values,
      );
      totalCells += resp.data.cells_count;
      totalTargets += resp.data.rooms_count;
      progress.value = { done: progress.value.done + 1, total: batches.length };
    }
    notify.success(
      successMsg,
      `${totalCells} ô · ${totalTargets} phòng × ${dateCount.value} ngày`,
    );

    showConfirm.value = false;
    emit('applied');
    emit('update:visible', false);
  } catch (e) {
    // Partial success: notify what was done before the failure
    if (progress.value && progress.value.done > 0) {
      notify.warn(
        'Cập nhật bị gián đoạn',
        `Đã chạy ${progress.value.done}/${progress.value.total} batch trước khi gặp lỗi.`,
      );
    }
    notify.apiError(e);
  } finally {
    submitting.value = false;
    progress.value = null;
  }
}

</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="(v: boolean) => emit('update:visible', v)"
    modal
    header="Cập nhật giá nhanh"
    :style="{ width: '720px', maxWidth: '95vw' }"
    :closable="!submitting"
    :close-on-escape="!submitting"
    :draggable="false"
  >
    <div class="quick-body">
      <!-- 1. Entity kind -->
      <div class="section">
        <label class="section-label">1. Đối tượng cập nhật</label>
        <SelectButton
          v-model="kind"
          :options="KIND_OPTIONS"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          aria-label="Đối tượng cập nhật"
        >
          <template #option="slotProps">
            <i :class="slotProps.option.icon" />
            <span class="kind-label">{{ slotProps.option.label }}</span>
          </template>
        </SelectButton>
        <small class="hint">{{ helperMsg }}</small>
      </div>

      <!-- 2. Scope -->
      <div class="section">
        <label class="section-label">2. Phạm vi</label>
        <div class="grid-2">
          <div class="field">
            <label>Khách sạn <small class="muted">(trống = tất cả)</small></label>
            <MultiSelect
              v-model="hotelIds"
              :options="lookup.hotels"
              option-label="name"
              option-value="id"
              :placeholder="`Tất cả (${lookup.hotels.length})`"
              display="chip"
              filter
              filter-placeholder="Tìm khách sạn…"
              :max-selected-labels="3"
              :selected-items-label="`{0} / ${lookup.hotels.length} khách sạn`"
            />
          </div>

          <div class="field">
            <label>Phòng <small class="muted">(trống = mọi phòng của KS đã chọn)</small></label>
            <MultiSelect
              v-model="roomIds"
              :options="availableRooms"
              option-label="name"
              option-value="id"
              :placeholder="`Tất cả (${availableRooms.length})`"
              display="chip"
              filter
              filter-placeholder="Tìm phòng…"
              :max-selected-labels="3"
              :selected-items-label="`{0} / ${availableRooms.length} phòng`"
            />
          </div>

          <div class="field">
            <label>Từ ngày</label>
            <DatePicker v-model="dateFrom" date-format="yy-mm-dd" show-icon />
          </div>
          <div class="field">
            <label>Đến ngày</label>
            <DatePicker v-model="dateTo" date-format="yy-mm-dd" show-icon />
          </div>

          <div class="field grid-span-2">
            <label>Lọc theo thứ trong tuần</label>
            <div class="weekday-row">
              <Button
                label="Tất cả"
                size="small"
                :severity="weekdayMode === 'all' ? 'primary' : 'secondary'"
                :outlined="weekdayMode !== 'all'"
                @click="setAllDays"
              />
              <Button
                label="Ngày thường (T2–T6)"
                size="small"
                :severity="weekdayMode === 'weekday' ? 'primary' : 'secondary'"
                :outlined="weekdayMode !== 'weekday'"
                @click="setWeekdayOnly"
              />
              <Button
                label="Cuối tuần (T7, CN)"
                size="small"
                :severity="weekdayMode === 'weekend' ? 'primary' : 'secondary'"
                :outlined="weekdayMode !== 'weekend'"
                @click="setWeekendOnly"
              />
              <MultiSelect
                v-model="weekdays"
                :options="WEEKDAYS"
                option-label="label"
                option-value="value"
                placeholder="Tùy chỉnh"
                display="chip"
                class="weekday-custom"
                :max-selected-labels="7"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Value -->
      <div class="section">
        <label class="section-label">3. Giá trị</label>
        <div class="grid-2">
          <div class="field">
            <label>{{ valueLabel }}</label>
            <InputNumber
              v-model="value"
              :min="0"
              :step="valueIsCurrency ? 50000 : 1"
              :show-buttons="!valueIsCurrency"
              :suffix="valueIsCurrency ? ' đ' : ''"
              fluid
              autofocus
            />
            <small class="hint">{{ valueDisplay }}</small>
          </div>
        </div>
      </div>

      <!-- Preview / warnings -->
      <Message
        v-if="cellCount > 0"
        :severity="cellCount > CONFIRM_THRESHOLD ? 'warn' : 'info'"
        :closable="false"
        class="preview-msg"
      >
        Sẽ ghi đè <strong>{{ formatCompact(cellCount) }}</strong> ô
        ({{ targetCount }} {{ targetLabel }} × {{ dateCount }} ngày)
        <span v-if="cellCount > CONFIRM_THRESHOLD"> · cần xác nhận thêm</span>
      </Message>
      <Message v-else-if="dateCount === 0" severity="warn" :closable="false" class="preview-msg">
        Không có ngày nào hợp lệ trong phạm vi đã chọn.
      </Message>
      <Message v-else-if="targetCount === 0" severity="warn" :closable="false" class="preview-msg">
        Không có {{ targetLabel }} nào để áp dụng. Hãy chọn khách sạn / phòng phù hợp.
      </Message>

      <Message v-if="pendingCount > 0" severity="warn" :closable="false" class="preview-msg">
        Có {{ pendingCount }} thay đổi tay đang chờ lưu. Áp dụng cập nhật nhanh sẽ ghi đè giá trị đã đổi sau khi load lại.
      </Message>

      <Message v-if="progress" severity="info" :closable="false" class="preview-msg">
        <i class="pi pi-spin pi-spinner" /> Đang xử lý batch {{ progress.done }}/{{ progress.total }}…
      </Message>
    </div>

    <template #footer>
      <div class="footer-row">
        <Button label="Hủy" severity="secondary" text :disabled="submitting" @click="close" />
        <Button
          label="Áp dụng"
          icon="pi pi-check"
          severity="success"
          :disabled="!canSubmit"
          :loading="submitting"
          @click="onSubmitClick"
        />
      </div>
    </template>
  </Dialog>

  <!-- Large-batch confirmation -->
  <Dialog
    v-model:visible="showConfirm"
    modal
    header="Xác nhận cập nhật lượng lớn"
    :style="{ width: '440px' }"
    :closable="!submitting"
  >
    <p>
      Bạn sắp ghi đè <strong>{{ formatCompact(cellCount) }}</strong> ô giá
      ({{ targetCount }} {{ targetLabel }} × {{ dateCount }} ngày).
    </p>
    <p class="muted">
      Hành động này không thể hoàn tác tự động. Hãy kiểm tra kỹ phạm vi trước khi tiếp tục.
    </p>
    <template #footer>
      <Button label="Quay lại" severity="secondary" text :disabled="submitting" @click="showConfirm = false" />
      <Button
        label="Tôi đã hiểu, áp dụng"
        icon="pi pi-check"
        severity="warn"
        :loading="submitting"
        @click="doSubmit"
      />
    </template>
  </Dialog>
</template>

<style scoped>
.quick-body { display: flex; flex-direction: column; gap: 1.25rem; padding-top: 0.25rem; }
.section { display: flex; flex-direction: column; gap: 0.5rem; }
.section-label { font-size: 0.85rem; font-weight: 600; color: var(--p-text-color); }
.hint { font-size: 0.75rem; color: var(--p-text-muted-color); }
.muted { color: var(--p-text-muted-color); font-weight: 400; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.field label { font-size: 0.8rem; font-weight: 500; }
.weekday-row { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
.weekday-custom { min-width: 200px; flex: 1; }
.kind-label { margin-left: 0.35rem; }
.preview-msg { margin-top: 0.25rem; }
.footer-row { display: flex; justify-content: flex-end; gap: 0.5rem; }

@media (max-width: 640px) {
  .grid-2 { grid-template-columns: 1fr; }
  .grid-span-2 { grid-column: auto; }
}
</style>
