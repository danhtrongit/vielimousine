<script setup lang="ts">
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import {
  getQuote, getError, isLoading, setSelection, selection, priceChecked,
  type BookingType,
} from '@/composables/useBookingState';
import { formatVND } from '@/composables/useFormat';
import PriceCalendar from './PriceCalendar.vue';

interface Props {
  room: {
    id: number;
    name: string;
    description?: string | null;
    thumbnail_url?: string | null;
    included_adults: number;
    max_adults: number;
    max_children: number;
    base_price: number;
    extra_adult_price: number;
    area?: number | null;
    bed_type?: string | null;
    bed_count?: number | null;
    view?: string | null;
    floor?: string | null;
    amenities: string[];
  };
}

const props = defineProps<Props>();
const showCalendar = ref(false);

interface OptionState {
  loading: boolean;
  error?: string;
  state: 'loading' | 'unavailable' | 'requires-quote' | 'quote' | 'idle' | 'error';
  total: string;
  hint?: string;
  unavailable: boolean;
}

function buildOption(type: BookingType): OptionState {
  const q = getQuote(props.room.id, type);
  const e = getError(props.room.id, type);
  const loading = isLoading(props.room.id, type);
  if (loading && !q) return { loading: true, state: 'loading', total: '', unavailable: false };
  if (e) return { loading: false, error: e, state: 'error', total: '', unavailable: false, hint: e };
  if (!q) return { loading: false, state: 'idle', total: formatVND(props.room.base_price) + '/đêm', unavailable: false };
  if (q.unavailable_date) return { loading: false, state: 'unavailable', total: 'Hết phòng', unavailable: true, hint: 'Hết phòng đêm ' + q.unavailable_date };
  if (q.requires_quote) return { loading: false, state: 'requires-quote', total: 'Liên hệ báo giá', unavailable: false };
  return {
    loading: false, state: 'quote', total: formatVND(q.total), unavailable: false,
    hint: q.num_rooms > 1
      ? `${q.num_rooms} phòng × ${q.nights} đêm`
      : `${q.nights} đêm`,
  };
}

const roomOpt = computed(() => buildOption('room'));
const comboOpt = computed(() => buildOption('combo'));

const isPicked = computed(() => selection.roomId === props.room.id);
const pickedType = computed(() => isPicked.value ? selection.bookingType : null);

const bedText = computed(() => {
  const parts = [];
  if (props.room.bed_type) parts.push(props.room.bed_type);
  if (props.room.bed_count) parts.push(`× ${props.room.bed_count}`);
  return parts.join(' ');
});

function pick(type: BookingType) {
  setSelection(props.room.id, type);
  setTimeout(() => {
    document.querySelector('[data-vh-checkout]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 50);
}
</script>

<template>
  <article
    class="vh-room"
    :class="{ 'vh-room-picked': isPicked }"
    :aria-labelledby="`room-${room.id}-name`"
  >
    <div class="vh-room-media">
      <div
        v-if="room.thumbnail_url"
        class="vh-room-thumb"
        :style="{ backgroundImage: `url('${room.thumbnail_url}')` }"
      ></div>
      <div v-else class="vh-room-thumb vh-room-thumb-empty"><i class="pi pi-home" /></div>
      <span v-if="room.view" class="vh-room-badge"><i class="pi pi-eye" /> {{ room.view }}</span>
    </div>

    <div class="vh-room-body">
      <header class="vh-room-head">
        <h3 :id="`room-${room.id}-name`" class="vh-room-name">{{ room.name }}</h3>
        <p v-if="room.description" class="vh-room-desc">{{ room.description }}</p>
      </header>

      <ul class="vh-room-specs">
        <li>
          <i class="pi pi-users" />
          <span>{{ room.included_adults === room.max_adults ? `${room.max_adults} người lớn` : `${room.included_adults}–${room.max_adults} người lớn` }}</span>
        </li>
        <li v-if="room.max_children > 0">
          <i class="pi pi-user" />
          <span>tối đa {{ room.max_children }} trẻ em</span>
        </li>
        <li v-if="room.area">
          <i class="pi pi-arrows-alt" />
          <span>{{ room.area }} m²</span>
        </li>
        <li v-if="bedText">
          <i class="pi pi-th-large" />
          <span>{{ bedText }}</span>
        </li>
        <li v-if="room.floor">
          <i class="pi pi-building" />
          <span>{{ room.floor }}</span>
        </li>
      </ul>

      <ul v-if="room.amenities.length" class="vh-room-amenities">
        <li v-for="a in room.amenities.slice(0, 5)" :key="a">{{ a }}</li>
        <li v-if="room.amenities.length > 5" class="vh-room-amenities-more">+{{ room.amenities.length - 5 }}</li>
      </ul>

      <button type="button" class="vh-link vh-room-cal-toggle" @click="showCalendar = true">
        <i class="pi pi-calendar" />
        Xem lịch giá theo ngày
      </button>
    </div>

    <Dialog
      v-model:visible="showCalendar"
      :header="`Lịch giá — ${room.name}`"
      modal
      dismissable-mask
      :style="{ width: 'min(560px, 96vw)' }"
      :breakpoints="{ '640px': '96vw' }"
      class="vh-cal-dialog"
    >
      <PriceCalendar :room-id="room.id" :base-price="room.base_price" />
      <template #footer>
        <Button label="Xác nhận" icon="pi pi-check" size="small" @click="showCalendar = false" />
      </template>
    </Dialog>

    <aside class="vh-room-cta" aria-label="Tuỳ chọn đặt phòng">
      <div
        class="vh-opt"
        :class="{ 'vh-opt-picked': pickedType === 'room' }"
        :aria-pressed="pickedType === 'room'"
      >
        <div class="vh-opt-head">
          <i class="pi pi-key" aria-hidden="true" />
          <span>Chỉ phòng</span>
        </div>
        <div class="vh-opt-price">
          <span
            v-if="roomOpt.state === 'loading'"
            class="vh-skeleton"
            aria-label="Đang tính giá phòng"
          ></span>
          <template v-else>
            <span
              :class="[
                'vh-price-amount',
                roomOpt.state === 'unavailable' && 'vh-price-na',
                roomOpt.state === 'requires-quote' && 'vh-price-contact',
              ]"
            >
              {{ roomOpt.total }}
            </span>
            <small v-if="roomOpt.hint && roomOpt.state === 'quote'" class="vh-price-hint">{{ roomOpt.hint }}</small>
            <small v-else-if="roomOpt.state === 'idle'" class="vh-price-hint">1 đêm</small>
          </template>
        </div>
        <Button
          :label="pickedType === 'room' ? 'Đã chọn' : (roomOpt.unavailable ? 'Liên hệ đặt phòng' : (priceChecked ? 'Chọn phòng' : 'Kiểm tra giá trước'))"
          :icon="pickedType === 'room' ? 'pi pi-check' : (roomOpt.unavailable ? 'pi pi-phone' : (priceChecked ? 'pi pi-arrow-right' : 'pi pi-lock'))"
          :icon-pos="pickedType === 'room' ? 'left' : 'right'"
          :severity="pickedType === 'room' ? 'success' : (roomOpt.unavailable ? 'secondary' : undefined)"
          :disabled="!priceChecked || roomOpt.loading"
          :aria-label="pickedType === 'room' ? `Đã chọn phòng ${room.name}` : (roomOpt.unavailable ? `Liên hệ đặt phòng ${room.name}` : `Chọn phòng ${room.name}`)"
          size="small"
          fluid
          @click="pick('room')"
        />
      </div>

      <div
        class="vh-opt vh-opt-combo"
        :class="{ 'vh-opt-picked': pickedType === 'combo' }"
        :aria-pressed="pickedType === 'combo'"
      >
        <div class="vh-opt-head">
          <i class="pi pi-car" aria-hidden="true" />
          <span>Combo <small>(Phòng + vé xe)</small></span>
        </div>
        <div class="vh-opt-price">
          <span
            v-if="comboOpt.state === 'loading'"
            class="vh-skeleton"
            aria-label="Đang tính giá combo"
          ></span>
          <template v-else>
            <span
              :class="[
                'vh-price-amount',
                comboOpt.state === 'unavailable' && 'vh-price-na',
                comboOpt.state === 'requires-quote' && 'vh-price-contact',
              ]"
            >
              {{ comboOpt.total }}
            </span>
            <small v-if="comboOpt.hint && comboOpt.state === 'quote'" class="vh-price-hint">{{ comboOpt.hint }}</small>
            <small v-else-if="comboOpt.state === 'idle'" class="vh-price-hint">1 đêm</small>
          </template>
        </div>
        <Button
          :label="pickedType === 'combo' ? 'Đã chọn' : (comboOpt.unavailable ? 'Liên hệ đặt combo' : (priceChecked ? 'Chọn combo' : 'Kiểm tra giá trước'))"
          :icon="pickedType === 'combo' ? 'pi pi-check' : (comboOpt.unavailable ? 'pi pi-phone' : (priceChecked ? 'pi pi-arrow-right' : 'pi pi-lock'))"
          :icon-pos="pickedType === 'combo' ? 'left' : 'right'"
          :severity="pickedType === 'combo' ? 'success' : (comboOpt.unavailable ? 'secondary' : 'warn')"
          :disabled="!priceChecked || comboOpt.loading"
          :aria-label="pickedType === 'combo' ? `Đã chọn combo cho ${room.name}` : (comboOpt.unavailable ? `Liên hệ đặt combo cho ${room.name}` : `Chọn combo cho ${room.name}`)"
          size="small"
          fluid
          @click="pick('combo')"
        />
      </div>
    </aside>
  </article>
</template>
