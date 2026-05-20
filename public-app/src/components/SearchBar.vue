<script setup lang="ts">
import { computed, watch, ref } from 'vue';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import { search, priceChecked } from '@/composables/useBookingState';
import { refreshQuotes } from '@/composables/useQuotes';
import { ymd, parseYmd } from '@/composables/useFormat';

const adultOptions = Array.from({ length: 8 }, (_, i) => ({ label: `${i + 1} người`, value: i + 1 }));
const childOptions = Array.from({ length: 7 }, (_, i) => ({ label: `${i} trẻ em`, value: i }));
const roomOptions = Array.from({ length: 5 }, (_, i) => ({ label: `${i + 1} phòng`, value: i + 1 }));

// PrimeVue DatePicker uses Date objects → mirror search state.
const checkin = ref<Date>(parseYmd(search.checkin));
const checkout = ref<Date>(parseYmd(search.checkout));

watch(checkin, (v) => { if (v) search.checkin = ymd(v); });
watch(checkout, (v) => { if (v) search.checkout = ymd(v); });
watch(() => search.checkin, (v) => { const d = parseYmd(v); if (d.getTime() !== checkin.value.getTime()) checkin.value = d; });
watch(() => search.checkout, (v) => { const d = parseYmd(v); if (d.getTime() !== checkout.value.getTime()) checkout.value = d; });

const childCount = computed({
  get: () => search.childAges.length,
  set: (n: number) => {
    const cur = search.childAges.slice(0, n);
    while (cur.length < n) cur.push(5);
    search.childAges = cur;
  },
});

// Per-index proxy: each InputNumber gets its own reactive get/set into search.childAges.
// Direct v-model on `search.childAges[i]` doesn't reliably round-trip with PrimeVue
// InputNumber's internal state, so we use a computed proxy per index.
function ageProxyFor(i: number) {
  return computed<number>({
    get: () => search.childAges[i] ?? 0,
    set: (v) => {
      const ages = search.childAges.slice();
      ages[i] = typeof v === 'number' ? v : 0;
      search.childAges = ages;
    },
  });
}
// Reactive array of proxies (recomputed when childAges length changes).
const ageProxies = computed(() => search.childAges.map((_, i) => ageProxyFor(i)));

const today = new Date();
today.setHours(0, 0, 0, 0);

const checking = ref(false);

async function checkPrice() {
  if (checking.value) return;
  checking.value = true;
  try {
    await Promise.all([
      refreshQuotes(),
      new Promise<void>((resolve) => setTimeout(resolve, 1000)),
    ]);
  } finally {
    checking.value = false;
  }
}
</script>

<template>
  <section class="vh-search">
    <h2 class="vh-section-title">
      <i class="pi pi-search" /> Tìm phòng phù hợp
    </h2>
    <div class="vh-search-row">
      <div class="vh-field">
        <label for="vh-checkin"><i class="pi pi-calendar" aria-hidden="true" /> Nhận phòng</label>
        <DatePicker
          input-id="vh-checkin"
          v-model="checkin"
          date-format="dd/mm/yy"
          :min-date="today"
          show-icon
          icon-display="input"
          fluid
        />
      </div>
      <div class="vh-field">
        <label for="vh-checkout"><i class="pi pi-calendar" aria-hidden="true" /> Trả phòng</label>
        <DatePicker
          input-id="vh-checkout"
          v-model="checkout"
          date-format="dd/mm/yy"
          :min-date="today"
          show-icon
          icon-display="input"
          fluid
        />
      </div>
      <div class="vh-field">
        <label for="vh-rooms"><i class="pi pi-th-large" aria-hidden="true" /> Số phòng</label>
        <Select
          input-id="vh-rooms"
          v-model="search.userRooms"
          :options="roomOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>
      <div class="vh-field">
        <label for="vh-adults"><i class="pi pi-users" aria-hidden="true" /> Người lớn</label>
        <Select
          input-id="vh-adults"
          v-model="search.adults"
          :options="adultOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>
      <div class="vh-field">
        <label for="vh-children"><i class="pi pi-user" aria-hidden="true" /> Số trẻ em</label>
        <Select
          input-id="vh-children"
          v-model="childCount"
          :options="childOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>
    </div>
    <div v-if="search.childAges.length > 0" class="vh-search-row vh-child-ages">
      <div v-for="(_, i) in search.childAges" :key="i" class="vh-field vh-field-sm">
        <label>Trẻ em #{{ i + 1 }} (tuổi)</label>
        <InputNumber
          v-model="ageProxies[i].value"
          :min="0"
          :max="17"
          show-buttons
          button-layout="horizontal"
          increment-button-icon="pi pi-plus"
          decrement-button-icon="pi pi-minus"
          :input-style="{ width: '3rem', textAlign: 'center' }"
          class="vh-age-input"
        />
      </div>
    </div>
    <div class="vh-search-actions">
      <p class="vh-search-hint" :class="{ 'vh-search-hint-ok': priceChecked }">
        <i :class="['pi', priceChecked ? 'pi-check-circle' : 'pi-info-circle']" aria-hidden="true" />
        <span v-if="priceChecked">Giá đã cập nhật — bạn có thể chọn phòng bên dưới.</span>
        <span v-else>Bấm "Kiểm tra giá" để xem giá thực tế và chọn phòng.</span>
      </p>
      <Button
        :label="priceChecked ? 'Cập nhật giá' : 'Kiểm tra giá'"
        icon="pi pi-search"
        size="large"
        :loading="checking"
        :disabled="checking"
        @click="checkPrice"
      />
    </div>
  </section>
</template>
