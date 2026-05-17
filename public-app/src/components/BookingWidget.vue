<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import { search, selection, getQuote } from '@/composables/useBookingState';
import { formatVND, formatDateVN } from '@/composables/useFormat';

const props = defineProps<{ rooms: Array<{ id: number; name: string }> }>();

const room = computed(() => props.rooms.find((r) => r.id === selection.roomId));
const quote = computed(() => (selection.roomId ? getQuote(selection.roomId, selection.bookingType) : null));
const totalText = computed(() => {
  if (!quote.value) return '—';
  if (quote.value.requires_quote) return 'Liên hệ';
  return formatVND(quote.value.total);
});

function scrollToCheckout() {
  document.querySelector('[data-vh-checkout]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
  <div class="vh-widget-wrap">
    <div class="vh-widget">
      <h3 class="vh-widget-title">
        <i class="pi pi-shopping-cart" /> Tóm tắt
      </h3>
      <div v-if="!room || !quote" class="vh-widget-empty">
        <p><i class="pi pi-arrow-left" /> Chọn phòng để xem giá chi tiết.</p>
      </div>
      <div v-else class="vh-widget-body">
        <div class="vh-widget-room">{{ room.name }}</div>
        <div class="vh-widget-type">
          <i :class="['pi', selection.bookingType === 'combo' ? 'pi-car' : 'pi-key']" />
          {{ selection.bookingType === 'combo' ? 'Combo (phòng + vé)' : 'Chỉ phòng' }}
        </div>
        <div class="vh-widget-meta">
          <div><i class="pi pi-calendar" /> {{ formatDateVN(search.checkin) }} → {{ formatDateVN(search.checkout) }}</div>
          <div><i class="pi pi-moon" /> {{ quote.nights }} đêm · {{ quote.num_rooms }} phòng</div>
          <div><i class="pi pi-users" /> {{ search.adults }} người lớn{{ search.childAges.length > 0 ? `, ${search.childAges.length} trẻ em` : '' }}</div>
        </div>

        <div v-if="quote.requires_quote" class="vh-widget-lines vh-muted">
          Vượt sức chứa thông thường — gửi yêu cầu để được báo giá.
        </div>
        <div v-else class="vh-widget-lines">
          <div class="vh-line">
            <span>{{ quote.num_rooms }} phòng × {{ quote.nights }} đêm</span>
            <span>{{ formatVND(quote.room_subtotal) }}</span>
          </div>
          <div v-if="quote.extra_adult_subtotal" class="vh-line">
            <span>Phụ thu người lớn</span><span>{{ formatVND(quote.extra_adult_subtotal) }}</span>
          </div>
          <div v-if="quote.child_surcharge_total" class="vh-line">
            <span>Phụ thu trẻ em</span><span>{{ formatVND(quote.child_surcharge_total) }}</span>
          </div>
          <div v-if="quote.ticket_subtotal" class="vh-line">
            <span>Vé xe</span><span>{{ formatVND(quote.ticket_subtotal) }}</span>
          </div>
          <div v-if="quote.discount" class="vh-line vh-line-discount">
            <span>Giảm giá</span><span>−{{ formatVND(quote.discount) }}</span>
          </div>
        </div>

        <div class="vh-widget-total">
          <span>Tổng cộng</span>
          <strong>{{ totalText }}</strong>
        </div>

        <ul v-if="quote.messages.length" class="vh-msg-list">
          <li v-for="m in quote.messages" :key="m">{{ m }}</li>
        </ul>

        <Button label="Đặt ngay" icon="pi pi-arrow-right" icon-pos="right" fluid size="large" @click="scrollToCheckout" />
      </div>
    </div>
  </div>
</template>
