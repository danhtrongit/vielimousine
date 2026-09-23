export type BookingQuoteStatus = 'draft' | 'published' | 'revoked';
export type BookingQuoteEffectiveStatus = BookingQuoteStatus | 'expired';
export type BookingQuotePaymentStatus = 'unpaid' | 'deposit_paid' | 'paid' | 'review_required';
export type BookingQuoteDepositType = 'percent' | 'fixed';

export interface BookingQuoteLine {
  label: string;
  quantity: number;
  unit: string;
  unit_price: number;
  line_total: number;
  room_id?: number | null;
  hotel_id?: number | null;
  hotel_name?: string | null;
  room_name?: string | null;
  booking_type?: 'room' | 'combo' | string;
  checkin?: string | null;
  checkout?: string | null;
  adults?: number;
  child_ages?: number[];
  user_rooms?: number;
  num_rooms?: number;
  nights?: number;
  room_subtotal?: number;
  extra_adult_total?: number;
  child_surcharge_total?: number;
  ticket_count?: number;
  ticket_subtotal?: number;
  pricing_snapshot?: Record<string, unknown>;
}

export interface BookingQuoteItemPayload {
  room_id: number;
  booking_type: 'room' | 'combo';
  checkin: string;
  checkout: string;
  adults: number;
  child_ages: number[];
  user_rooms: number;
}

export interface BookingQuoteBrand {
  company_name: string;
  company_phone: string;
  company_email: string;
  company_address: string;
  company_tax_id: string;
  logo_url: string;
}

export interface BookingQuotePayment {
  id: number;
  invoice: string;
  purpose: 'deposit' | 'balance';
  expected_amount: number;
  received_amount: number;
  currency: 'VND';
  status: 'pending' | 'paid' | 'review';
  transaction_id: string | null;
  paid_at: string | null;
  created_at: string;
  receipts: BookingQuotePaymentReceipt[];
}

export interface BookingQuotePaymentReceipt {
  id: number;
  intent_id: number;
  invoice: string;
  transaction_id: string;
  received_amount: number;
  order_amount: number;
  currency: string;
  outcome: 'accepted' | 'review';
  reason: string | null;
  paid_at: string;
  created_at: string;
}

export interface BookingQuote {
  id: number;
  public_id: string;
  code: string;
  sales_user_id: number;
  customer_id: number | null;
  status: BookingQuoteStatus;
  effective_status: BookingQuoteEffectiveStatus;
  payment_status: BookingQuotePaymentStatus;
  customer_name: string;
  customer_phone: string;
  customer_email: string | null;
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
  items: BookingQuoteItemPayload[];
  lines: BookingQuoteLine[];
  discount: number;
  deposit_type: BookingQuoteDepositType;
  deposit_value: number;
  subtotal: number;
  total: number;
  deposit_amount: number;
  paid_amount: number;
  remaining_amount: number;
  due_amount: number;
  payment_review: boolean;
  valid_until: string | null;
  expires_at: string | null;
  can_checkout: boolean;
  public_url: string;
  brand: BookingQuoteBrand | null;
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface BookingQuoteDetail extends BookingQuote {
  payments: BookingQuotePayment[];
}

export interface BookingQuoteLinePayload {
  label: string;
  quantity: number;
  unit: string;
  unit_price: number;
  line_total?: number;
  hotel_name?: string | null;
  room_name?: string | null;
}

export interface BookingQuotePayload {
  customer_id: number | null;
  customer_name: string;
  customer_phone: string;
  customer_email: string;
  title: string;
  image_url: string;
  greeting: string;
  trip_start: string | null;
  trip_end: string | null;
  description: string;
  inclusions: string;
  exclusions: string;
  terms: string;
  contact_name: string;
  contact_phone: string;
  contact_zalo: string;
  items: BookingQuoteItemPayload[];
  lines: BookingQuoteLinePayload[];
  discount: number;
  deposit_type: BookingQuoteDepositType;
  deposit_value: number;
  valid_until: string;
}
