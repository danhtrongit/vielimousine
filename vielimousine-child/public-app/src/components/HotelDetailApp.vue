<script setup lang="ts">
import { onMounted, onBeforeUnmount, computed } from 'vue';
import SearchBar from './SearchBar.vue';
import RoomCard from './RoomCard.vue';
import InlineCheckout from './InlineCheckout.vue';
import MobileCta from './MobileCta.vue';
import { prefillFromQuery, setSelection, getQuote, priceChecked, handleBackToRooms } from '@/composables/useBookingState';
import { registerRooms } from '@/composables/useQuotes';

interface RoomData {
  id: number;
  name: string;
  description?: string | null;
  thumbnail_url?: string | null;
  images?: string[] | null;
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

// Sort priority (only after prices are checked):
//  1. has a price (room-type quote not requires-quote / not unavailable) before quote-required
//  2. fits in requested rooms (not auto-expanded) before expanded
//  3. tighter capacity fit (less spare adult capacity) first
//  4. original admin sort_order (props order) as tiebreak
const sortedRooms = computed(() => {
  if (!priceChecked.value) return props.rooms;
  const decorated = props.rooms.map((room, index) => {
    const q = getQuote(room.id, 'room');
    const priced = !!q && !q.requires_quote && !q.unavailable_date;
    const expanded = !!q && q.rooms_expanded;
    const waste = q ? q.num_rooms * room.max_adults - q.effective_adults : 9999;
    return { room, index, priced, expanded, waste };
  });
  decorated.sort((a, b) => {
    if (a.priced !== b.priced) return a.priced ? -1 : 1;
    if (a.expanded !== b.expanded) return a.expanded ? 1 : -1;
    if (a.waste !== b.waste) return a.waste - b.waste;
    return a.index - b.index;
  });
  return decorated.map((d) => d.room);
});

function onPopState() {
  if (handleBackToRooms()) {
    setTimeout(() => {
      document.querySelector('.vh-rooms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
  }
}

onMounted(() => {
  prefillFromQuery();
  registerRooms(props.rooms.map((r) => r.id));
  window.addEventListener('popstate', onPopState);

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

onBeforeUnmount(() => {
  window.removeEventListener('popstate', onPopState);
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
        <RoomCard v-for="room in sortedRooms" :key="room.id" :room="room" />
      </div>
    </section>

    <InlineCheckout :rooms="rooms" />

    <MobileCta :rooms="rooms" />
  </div>
</template>
