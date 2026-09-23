import { api } from '@/api/client';

export type BookingQuoteEffectiveStatus = 'draft' | 'published' | 'revoked' | 'expired';
export type BookingQuotePaymentStatus = 'unpaid' | 'deposit_paid' | 'paid' | 'review_required';

export interface BookingQuoteLine {
  label: string;
  quantity: number;
  unit: string;
  unit_price: number;
  line_total: number;
}

/** The frozen room selection and selling prices published with the quote. */
export interface BookingQuoteRoomItem {
  room_id: number;
  hotel_id: number;
  hotel_name: string;
  room_name: string;
  booking_type: 'room' | 'combo';
  checkin: string;
  checkout: string;
  adults: number;
  child_ages: number[];
  user_rooms: number;
  num_rooms: number;
  nights: number;
  room_subtotal: number;
  extra_adult_total: number;
  child_surcharge_total: number;
  ticket_count: number;
  ticket_subtotal: number;
  line_total: number;
}

export interface BookingQuoteBrand {
  company_name: string | null;
  company_phone: string | null;
  company_email: string | null;
  company_address: string | null;
  company_tax_id: string | null;
  logo_url: string | null;
}

/** Fields deliberately exposed by the public booking-quote projection. */
export interface PublicBookingQuote {
  code: string;
  customer_name: string;
  title: string;
  image_url: string | null;
  greeting: string | null;
  trip_start: string | null;
  trip_end: string | null;
  description: string | null;
  inclusions: string | null;
  exclusions: string | null;
  terms: string | null;
  contact_name: string | null;
  contact_phone: string | null;
  contact_zalo: string | null;
  lines: BookingQuoteLine[];
  items?: BookingQuoteRoomItem[];
  discount: number;
  subtotal: number;
  total: number;
  deposit_type: 'percent' | 'fixed';
  deposit_value: number;
  deposit_amount: number;
  paid_amount: number;
  brand: BookingQuoteBrand;
  effective_status: BookingQuoteEffectiveStatus;
  payment_status: BookingQuotePaymentStatus;
  remaining_amount: number;
  due_amount: number;
  can_checkout: boolean;
  expires_at: string | null;
  public_url: string;
}

export interface BookingQuoteTransfer {
  bank_name: string;
  bank_code: string;
  bank_account: string;
  bank_holder: string;
  amount: number;
  memo: string;
  qr_url: string;
  currency: 'VND';
}

export interface BookingQuoteCheckout {
  transfer: BookingQuoteTransfer;
  amount: number;
  purpose: 'deposit' | 'balance';
}

function quotePath(publicId: string, suffix = ''): string {
  return `public/booking-quotes/${encodeURIComponent(publicId)}${suffix}`;
}

export function getPublicBookingQuote(publicId: string, signal?: AbortSignal): Promise<PublicBookingQuote> {
  return api.get<PublicBookingQuote>(quotePath(publicId), undefined, signal);
}

export function createBookingQuoteCheckout(publicId: string, signal?: AbortSignal): Promise<BookingQuoteCheckout> {
  return api.post<BookingQuoteCheckout>(quotePath(publicId, '/checkout'), undefined, { signal });
}
