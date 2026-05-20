<script setup lang="ts">
import { computed } from 'vue';
import Button from 'primevue/button';
import { selection, getQuote } from '@/composables/useBookingState';
import { formatVND } from '@/composables/useFormat';

const props = defineProps<{ rooms: Array<{ id: number; name: string }> }>();
const room = computed(() => props.rooms.find((r) => r.id === selection.roomId));
const quote = computed(() => (selection.roomId ? getQuote(selection.roomId, selection.bookingType) : null));

const totalText = computed(() => {
  if (!quote.value) return '—';
  if (quote.value.requires_quote) return 'Liên hệ';
  return formatVND(quote.value.total);
});

function jump() {
  document.querySelector('[data-vh-checkout]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
  <div v-if="room" class="vh-mobile-cta">
    <div>
      <span class="vh-mobile-room">
        <i :class="['pi', selection.bookingType === 'combo' ? 'pi-car' : 'pi-key']" />
        {{ room.name }}
      </span>
      <strong class="vh-mobile-total">{{ totalText }}</strong>
    </div>
    <Button label="Đặt ngay" icon="pi pi-arrow-right" icon-pos="right" @click="jump" />
  </div>
</template>
