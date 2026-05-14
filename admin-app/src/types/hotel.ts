export interface Hotel {
  id: number;
  post_id: number;
  name: string;
  slug: string;
  description: string | null;
  address: string | null;
  city: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  star_rating: number | null;
  default_checkin: string | null;
  default_checkout: string | null;
  default_ticket_price: number;
  ticket_free_children_count: number;
  ticket_free_children_max_age: number;
  pricing_policy: Record<string, unknown> | null;
  cancellation_policy: CancellationPolicy | null;
  thumbnail_id: number | null;
  gallery: number[] | null;
  is_active: boolean;
  sort_order: number;
}

export interface CancellationPolicy {
  rules: Array<{ hours_before_checkin: number; penalty_percent: number; description: string }>;
  refund_method?: string;
  notes?: string;
}

export interface Room {
  id: number;
  hotel_id: number;
  name: string;
  included_adults: number;
  max_adults: number;
  max_children: number;
  base_price: number;
  extra_adult_price: number;
  free_children_count: number;
  free_children_max_age: number;
  is_active: boolean;
}
