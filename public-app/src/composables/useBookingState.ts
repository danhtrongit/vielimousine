import { reactive, computed, ref } from 'vue';
import type { Quote } from '@/api/types';
import { ymd } from './useFormat';

export type BookingType = 'room' | 'combo';

interface SearchState {
  checkin: string;
  checkout: string;
  adults: number;
  childAges: number[];
  userRooms: number; // Số phòng khách muốn đặt; 1 = mặc định, 0 = để hệ thống tự chọn theo sức chứa
}

function defaultDates() {
  const today = new Date();
  const tomorrow = new Date(today.getTime() + 86400000);
  return { checkin: ymd(today), checkout: ymd(tomorrow) };
}

const dates = defaultDates();

export const search = reactive<SearchState>({
  checkin: dates.checkin,
  checkout: dates.checkout,
  adults: 2,
  childAges: [],
  userRooms: 1,
});

// Keyed by `${roomId}:${bookingType}` so we can show both prices per room.
export function quoteKey(roomId: number, type: BookingType): string {
  return `${roomId}:${type}`;
}

export const quotes = reactive(new Map<string, Quote>());
export const quoteErrors = reactive(new Map<string, string>());
export const quoteLoading = reactive(new Set<string>());

// True once the user has explicitly clicked "Kiểm tra giá".
// Reset to false whenever search params change so stale prices can't be booked.
export const priceChecked = ref(false);

export const selection = reactive<{ roomId: number | null; bookingType: BookingType }>({
  roomId: null,
  bookingType: 'room',
});

export function setSelection(roomId: number | null, type: BookingType = 'room') {
  selection.roomId = roomId;
  selection.bookingType = type;
}

export function getQuote(roomId: number, type: BookingType): Quote | undefined {
  return quotes.get(quoteKey(roomId, type));
}
export function isLoading(roomId: number, type: BookingType): boolean {
  return quoteLoading.has(quoteKey(roomId, type));
}
export function getError(roomId: number, type: BookingType): string | undefined {
  return quoteErrors.get(quoteKey(roomId, type));
}

export const nights = computed(() => {
  const a = new Date(search.checkin);
  const b = new Date(search.checkout);
  return Math.max(0, Math.round((b.getTime() - a.getTime()) / 86400000));
});

export function prefillFromQuery() {
  const sp = new URLSearchParams(window.location.search);
  const ci = sp.get('checkin');
  const co = sp.get('checkout');
  const a = sp.get('adults');
  const childAges = sp.get('child_ages');
  const rooms = sp.get('rooms');
  if (ci) search.checkin = ci;
  if (co) search.checkout = co;
  if (a) search.adults = parseInt(a, 10) || search.adults;
  if (childAges) {
    search.childAges = childAges.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => !isNaN(n));
  }
  if (rooms) {
    const n = parseInt(rooms, 10);
    if (n >= 1 && n <= 10) search.userRooms = n;
  }
}
