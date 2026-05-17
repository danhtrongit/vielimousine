import { watch } from 'vue';
import { api } from '@/api/client';
import type { Quote, QuoteRequest } from '@/api/types';
import {
  search, quotes, quoteErrors, quoteLoading, quoteKey,
  type BookingType,
} from './useBookingState';

const inflight = new Map<string, AbortController>();
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const TYPES: BookingType[] = ['room', 'combo'];

export function startQuotePolling(roomIds: number[]) {
  const refresh = () => {
    if (!search.checkin || !search.checkout) return;
    for (const rid of roomIds) {
      for (const t of TYPES) void fetchQuoteForRoom(rid, t);
    }
  };
  watch(search, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(refresh, 500);
  });
  refresh();
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
