import { watch } from 'vue';
import { api } from '@/api/client';
import type { Quote, QuoteRequest } from '@/api/types';
import {
  search, quotes, quoteErrors, quoteLoading, quoteKey, priceChecked, setSelection,
  type BookingType,
} from './useBookingState';

const inflight = new Map<string, AbortController>();
let registeredRoomIds: number[] = [];

const TYPES: BookingType[] = ['room', 'combo'];

// When user edits the search form, previously-loaded prices become stale.
// Clear them and force a fresh "Kiểm tra giá" click before any booking action.
watch(search, () => {
  priceChecked.value = false;
  quotes.clear();
  quoteErrors.clear();
  setSelection(null);
}, { deep: true });

export function registerRooms(roomIds: number[]) {
  registeredRoomIds = roomIds.slice();
}

export async function refreshQuotes(): Promise<void> {
  if (!search.checkin || !search.checkout) return;
  const tasks: Promise<void>[] = [];
  for (const rid of registeredRoomIds) {
    for (const t of TYPES) tasks.push(fetchQuoteForRoom(rid, t));
  }
  await Promise.all(tasks);
  priceChecked.value = true;
}

export async function fetchQuoteForRoom(roomId: number, bookingType: BookingType): Promise<void> {
  const k = quoteKey(roomId, bookingType);
  const prev = inflight.get(k);
  if (prev) prev.abort();
  const ac = new AbortController();
  inflight.set(k, ac);

  quoteLoading.add(k);
  quoteErrors.delete(k);

  const body: QuoteRequest = {
    room_id: roomId,
    booking_type: bookingType,
    checkin: search.checkin,
    checkout: search.checkout,
    adults: search.adults,
    child_ages: search.childAges.slice(),
    user_rooms: search.userRooms,
  };
  try {
    const data = await api.post<Quote>('quote', body, { signal: ac.signal });
    quotes.set(k, data);
    quoteErrors.delete(k);
  } catch (e: any) {
    if (e?.name === 'AbortError') return;
    quoteErrors.set(k, e?.errors?.[0]?.message || 'Lỗi kết nối');
    quotes.delete(k);
  } finally {
    quoteLoading.delete(k);
  }
}
