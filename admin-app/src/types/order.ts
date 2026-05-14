export interface Order {
  id: number;
  code: string;
  customer_id: number;
  customer_phone: string;
  customer_name: string;
  customer_email: string | null;
  sales_user_id: number | null;
  source: string;
  checkin: string;
  checkout: string;
  nights: number;
  adults: number;
  children: number;
  child_ages: number[] | null;
  subtotal: number;
  discount: number;
  total: number;
  paid_amount: number;
  currency: string;
  coupon_code: string | null;
  payment_status: 'pending' | 'partial' | 'paid' | 'refunded';
  status: 'pending' | 'confirmed' | 'cancelled' | 'completed' | 'no_show';
  cancelled_at: string | null;
  cancel_reason: string | null;
  confirmed_at: string | null;
  customer_note: string | null;
  internal_note: string | null;
  pickup: Record<string, unknown> | null;
  dropoff: Record<string, unknown> | null;
  created_at: string;
}

export interface OrderItem {
  id: number;
  order_id: number;
  hotel_id: number;
  room_id: number;
  name: string;
  booking_type: string;
  quantity: number;
  checkin: string;
  checkout: string;
  nights: number;
  adults: number;
  children: number;
  child_ages: number[] | null;
  room_subtotal: number;
  extra_adult_total: number;
  child_surcharge_total: number;
  ticket_count: number;
  ticket_subtotal: number;
  line_discount: number;
  line_total: number;
  pricing_snapshot: Record<string, unknown>;
  status: 'active' | 'cancelled';
  cancelled_at: string | null;
}

export interface Payment {
  id: number;
  order_id: number;
  type: 'deposit' | 'payment' | 'refund' | 'void';
  amount: number;
  method: string;
  gateway: string | null;
  transaction_id: string | null;
  note: string | null;
  paid_at: string | null;
  created_at: string;
}

export interface Customer {
  id: number;
  phone: string;
  name: string;
  email: string | null;
  booking_count: number;
}

export interface OrderDetail extends Order {
  items: OrderItem[];
  payments: Payment[];
  customer: Customer | null;
  redirect_url?: string | null;
  refund_preview?: {
    item_refunds: Array<{ item_id: number; line_total: number; penalty_percent: number; refundable: number; notes: string }>;
    total_refundable: number;
    paid_amount: number;
    actual_refund: number;
    remaining_held: number;
  };
}
