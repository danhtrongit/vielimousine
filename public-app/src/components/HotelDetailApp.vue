<script setup lang="ts">
import { onMounted } from 'vue';
import SearchBar from './SearchBar.vue';
import RoomCard from './RoomCard.vue';
import InlineCheckout from './InlineCheckout.vue';
import MobileCta from './MobileCta.vue';
import { prefillFromQuery, setSelection } from '@/composables/useBookingState';
import { startQuotePolling } from '@/composables/useQuotes';

interface RoomData {
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
}

const props = defineProps<{
  hotelId: number;
  rooms: RoomData[];
}>();

onMounted(() => {
  prefillFromQuery();
  startQuotePolling(props.rooms.map((r) => r.id));

  // Auto-pick room from query
  const sp = new URLSearchParams(window.location.search);
  const rid = sp.get('room_id');
  if (rid) {
    const id = parseInt(rid, 10);
    if (props.rooms.find((r) => r.id === id)) {
      const bt = sp.get('booking_type') === 'combo' ? 'combo' : 'room';
      setTimeout(() => setSelection(id, bt), 700);
    }
  }
});
</script>

<template>
  <div class="vh-app vh-app-embedded">
    <SearchBar />

    <section class="vh-rooms">
      <div v-if="rooms.length === 0" class="vh-empty">
        <p>Khách sạn chưa có phòng nào đang mở bán.</p>
      </div>
      <div v-else class="vh-room-grid">
        <RoomCard v-for="room in rooms" :key="room.id" :room="room" />
      </div>
    </section>

    <InlineCheckout :rooms="rooms" />

    <MobileCta :rooms="rooms" />
  </div>
</template>
