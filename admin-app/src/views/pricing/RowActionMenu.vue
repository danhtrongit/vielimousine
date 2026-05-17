<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import { ymdLocal } from '@/composables/useFormat';

const props = defineProps<{
  // Current effective value used as default in "apply to all" dialog
  currentValue: number;
  // Date range bounds shown in date pickers
  dateFrom: string;
  dateTo: string;
  // Label to show in menu header
  rowLabel: string;
}>();

const emit = defineEmits<{
  (e: 'apply-range', payload: { value: number; from: string; to: string; weekdays: number[] | null }): void;
  (e: 'reset'): void;
}>();

const popover = ref();
const mode = ref<'menu' | 'apply'>('menu');
const applyValue = ref<number>(0);
const applyFrom = ref<Date>(new Date());
const applyTo = ref<Date>(new Date());
const weekendOnly = ref(false);
const weekdayOnly = ref(false);

function open(e: Event) {
  mode.value = 'menu';
  applyValue.value = props.currentValue;
  applyFrom.value = new Date(props.dateFrom);
  applyTo.value = new Date(props.dateTo);
  weekendOnly.value = false;
  weekdayOnly.value = false;
  popover.value?.toggle(e);
}

function goApply() {
  mode.value = 'apply';
}

function confirmApply() {
  let weekdays: number[] | null = null;
  if (weekendOnly.value) weekdays = [6, 7];
  else if (weekdayOnly.value) weekdays = [1, 2, 3, 4, 5];
  emit('apply-range', {
    value: applyValue.value,
    from: ymdLocal(applyFrom.value),
    to: ymdLocal(applyTo.value),
    weekdays,
  });
  popover.value?.hide();
}

function confirmReset() {
  emit('reset');
  popover.value?.hide();
}
</script>

<template>
  <Button
    icon="pi pi-ellipsis-v"
    text
    rounded
    size="small"
    class="row-menu-btn"
    aria-label="Hành động"
    @click="open"
  />
  <Popover ref="popover">
    <div class="menu-pop">
      <div class="menu-header">{{ rowLabel }}</div>

      <template v-if="mode === 'menu'">
        <button class="menu-item" @click="goApply">
          <i class="pi pi-arrow-right-arrow-left" />
          <div>
            <div>Áp giá cho cả dải</div>
            <small>Set 1 giá → tất cả các ngày trong khoảng</small>
          </div>
        </button>
        <button class="menu-item danger" @click="confirmReset">
          <i class="pi pi-undo" />
          <div>
            <div>Đặt lại về mặc định</div>
            <small>Tắt tất cả override trong khoảng đang xem</small>
          </div>
        </button>
      </template>

      <template v-else>
        <div class="form">
          <div class="field">
            <label>Giá áp dụng (VND)</label>
            <InputNumber v-model="applyValue" :min="0" fluid />
          </div>
          <div class="row">
            <div class="field flex-1">
              <label>Từ ngày</label>
              <DatePicker v-model="applyFrom" date-format="yy-mm-dd" show-icon />
            </div>
            <div class="field flex-1">
              <label>Đến ngày</label>
              <DatePicker v-model="applyTo" date-format="yy-mm-dd" show-icon />
            </div>
          </div>
          <div class="row">
            <label class="check">
              <input type="checkbox" v-model="weekendOnly" :disabled="weekdayOnly" />
              Chỉ cuối tuần (T7, CN)
            </label>
            <label class="check">
              <input type="checkbox" v-model="weekdayOnly" :disabled="weekendOnly" />
              Chỉ ngày thường (T2-T6)
            </label>
          </div>
          <div class="footer">
            <Button label="Hủy" severity="secondary" text size="small" @click="mode = 'menu'" />
            <Button label="Áp dụng" icon="pi pi-check" size="small" @click="confirmApply" />
          </div>
        </div>
      </template>
    </div>
  </Popover>
</template>

<style scoped>
.row-menu-btn { width: 24px; height: 24px; opacity: 0; transition: opacity 0.1s; }
:deep(.row:hover) .row-menu-btn,
.row-menu-btn:focus { opacity: 1; }
.menu-pop { min-width: 280px; }
.menu-header { font-size: 0.8rem; font-weight: 500; color: var(--p-text-muted-color); padding: 0.25rem 0.5rem 0.5rem; border-bottom: 1px solid var(--p-surface-200); margin-bottom: 0.5rem; }
.menu-item {
  display: flex; align-items: flex-start; gap: 0.6rem;
  width: 100%; padding: 0.5rem 0.6rem; border: 0; background: transparent; cursor: pointer;
  text-align: left; border-radius: 4px; color: inherit; font: inherit;
}
.menu-item:hover { background: var(--p-surface-100); }
.menu-item.danger:hover { background: var(--p-red-50); }
.menu-item.danger { color: var(--p-red-700); }
.menu-item small { display: block; color: var(--p-text-muted-color); font-size: 0.72rem; margin-top: 0.1rem; }
.menu-item i { margin-top: 0.15rem; }
.form { padding: 0.25rem 0.5rem; display: flex; flex-direction: column; gap: 0.6rem; }
.field { display: flex; flex-direction: column; gap: 0.2rem; }
.field label { font-size: 0.75rem; color: var(--p-text-muted-color); }
.row { display: flex; gap: 0.5rem; }
.flex-1 { flex: 1; min-width: 0; }
.check { display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; cursor: pointer; }
.footer { display: flex; justify-content: flex-end; gap: 0.4rem; padding-top: 0.4rem; border-top: 1px solid var(--p-surface-200); }
</style>
