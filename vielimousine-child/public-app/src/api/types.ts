export interface QuoteRequest {
  room_id: number;
  booking_type: 'room' | 'combo';
  checkin: string;
  checkout: string;
  adults: number;
  child_ages: number[];
  user_rooms?: number;
}

export interface ChildAssessment {
  age: number;
  is_free: boolean;
  treated_as_adult: boolean;
  child_index: number;
}

export interface NightlyEntry {
  date: string;
  price: number;
  extra_adult_price: number;
  ticket_price: number;
  child_surcharges: Array<{ label: string; age: number; amount: number }>;
}

export interface Quote {
  num_rooms: number;
  nights: number;
  effective_adults: number;
  effective_children: number;
  extra_adult_beds: number;
  seat_count: number;
  billable_seats: number;
  free_child_seats: number;
  nightly: NightlyEntry[];
  child_assessments: ChildAssessment[];
  room_subtotal: number;
  extra_adult_subtotal: number;
  child_surcharge_total: number;
  ticket_subtotal: number;
  subtotal: number;
  discount: number;
  total: number;
  cost_total: number;
  requires_quote: boolean;
  messages: string[];
  unavailable_date: string | null;
}

export interface RoomPriceRow {
  room_id: number;
  date: string;
  price: number;
  stock: number;
}

export interface RoomDefault {
  id: number;
  base_price: number;
  extra_adult_price: number;
}

export interface RoomPricesResponse {
  prices: RoomPriceRow[];
  rooms: RoomDefault[];
  date_from: string;
  date_to: string;
}

export interface CouponValidateResponse {
  discount: number;
  valid: boolean;
}

export interface CreateOrderItem {
  room_id: number;
  booking_type: 'room' | 'combo';
  checkin: string;
  checkout: string;
  adults: number;
  child_ages: number[];
  user_rooms?: number;
}

export interface CreateOrderRequest {
  customer: { phone: string; name: string; email: string | null };
  items: CreateOrderItem[];
  customer_note?: string | null;
  coupon_code?: string;
  payment_method?: string;
}

export interface CreateOrderResponse {
  id: number;
  code: string;
  total: number;
  paid_amount: number;
  status: string;
  payment_status: string;
  redirect_url?: string | null;
}

export interface OrderLookupItem {
  id: number;
  name?: string;
  room_name?: string;
  hotel_name?: string;
  checkin: string;
  checkout: string;
  adults: number;
  children: number;
  line_total: number;
}

export interface OrderLookup {
  code: string;
  customer_name: string;
  customer_email?: string | null;
  status: string;
  payment_status: string;
  total: number;
  paid_amount: number;
  items: OrderLookupItem[];
}

export interface QuoteInquiryRequest {
  customer: { phone: string; name: string; email: string | null };
  note?: string | null;
  item: {
    room_id: number;
    booking_type: 'room' | 'combo';
    checkin: string;
    checkout: string;
    adults: number;
    child_ages: number[];
    user_rooms?: number;
  };
}

export interface QuoteInquiryResponse {
  id: number;
  status: string;
  message: string;
}
