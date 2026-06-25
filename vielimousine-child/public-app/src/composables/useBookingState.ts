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

// Mã giảm giá đang áp dụng — chia sẻ giữa InlineCheckout (nơi áp dụng) và
// BookingWidget (nơi hiển thị "Tổng cộng") để tổng tiền trừ đúng phần giảm.
export const appliedCoupon = reactive<{ code: string | null; discount: number }>({
  code: null,
  discount: 0,
});
export function resetCoupon(): void {
  appliedCoupon.code = null;
  appliedCoupon.discount = 0;
}

// Điểm trả cố định cho đơn combo (đưa đón limousine khứ hồi).
export const DROPOFF_OPTIONS: string[] = [
  '131 Nguyễn Thái Bình, Phường Bến Thành, TPHCM',
  '217 Hoàng Hoa Thám, Phường Tân Bình, TPHCM',
];

let pushedBookingState = false;

export function setSelection(roomId: number | null, type: BookingType = 'room') {
  const wasEmpty = selection.roomId === null;
  // Đổi phòng/loại đặt → mã giảm giá cũ (validate theo phòng+subtotal cũ) không còn đúng.
  resetCoupon();
  selection.roomId = roomId;
  selection.bookingType = type;
  // Push a history entry the first time a room is selected so the browser
  // Back button returns to the room list instead of leaving the hotel page.
  if (roomId !== null && wasEmpty && typeof history !== 'undefined') {
    history.pushState({ vhBooking: true }, '');
    pushedBookingState = true;
  }
}

// Clear selection, returning the browser to the room list without leaving the page.
export function clearSelectionBack() {
  if (pushedBookingState && typeof history !== 'undefined') {
    history.back(); // triggers popstate → handleBackToRooms
  } else {
    setSelection(null);
  }
}

// Called from the popstate handler when the user presses browser Back.
// Returns true if it consumed the event (i.e. there was a selection to clear).
export function handleBackToRooms(): boolean {
  pushedBookingState = false;
  if (selection.roomId !== null) {
    selection.roomId = null;
    return true;
  }
  return false;
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
