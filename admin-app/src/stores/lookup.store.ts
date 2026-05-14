import { defineStore } from 'pinia';
import { hotelsApi } from '@/api/hotels.api';
import { roomsApi } from '@/api/rooms.api';
import { surchargesApi, type Surcharge } from '@/api/surcharges.api';
import type { Hotel, Room } from '@/types/hotel';

export const useLookupStore = defineStore('lookup', {
  state: () => ({
    hotels: [] as Hotel[],
    rooms: [] as Room[],
    surcharges: [] as Surcharge[],
    loaded: false,
    loading: false,
  }),
  getters: {
    roomsByHotel: (s) => (hotelId: number): Room[] =>
      s.rooms.filter((r) => r.hotel_id === hotelId && r.is_active),
    hotelById: (s) => (id: number): Hotel | undefined =>
      s.hotels.find((h) => h.id === id),
    roomById: (s) => (id: number): Room | undefined =>
      s.rooms.find((r) => r.id === id),
    surchargesByRoom: (s) => (roomId: number): Surcharge[] =>
      s.surcharges.filter((sc) => sc.room_id === roomId && sc.is_active),
  },
  actions: {
    async ensureLoaded() {
      if (this.loaded || this.loading) return;
      this.loading = true;
      try {
        const [hotelsResp, roomsResp, surchargesResp] = await Promise.all([
          hotelsApi.list({ per_page: 100, is_active: 1 }),
          roomsApi.list({ per_page: 100, is_active: 1 }),
          surchargesApi.list({ per_page: 200, is_active: 1 }),
        ]);
        this.hotels = hotelsResp.data;
        this.rooms = roomsResp.data;
        this.surcharges = surchargesResp.data;
        this.loaded = true;
      } finally {
        this.loading = false;
      }
    },
    async refresh() {
      this.loaded = false;
      await this.ensureLoaded();
    },
  },
});

export const ORDER_SOURCES = [
  { label: 'Website', value: 'website' },
  { label: 'Admin', value: 'admin' },
  { label: 'Phone', value: 'phone' },
  { label: 'Walk-in', value: 'walkin' },
];

export const PAYMENT_METHODS = [
  { label: 'Chuyển khoản', value: 'bank_transfer' },
  { label: 'Tiền mặt', value: 'cash' },
  { label: 'SePay', value: 'sepay' },
  { label: 'Khác', value: 'manual' },
];

export const ORDER_STATUSES = [
  { label: 'Chờ thanh toán', value: 'pending', severity: 'warning' },
  { label: 'Đã xác nhận', value: 'confirmed', severity: 'info' },
  { label: 'Hoàn tất', value: 'completed', severity: 'success' },
  { label: 'Đã hủy', value: 'cancelled', severity: 'danger' },
  { label: 'Không xuất hiện', value: 'no_show', severity: 'secondary' },
];

export const PAYMENT_STATUSES = [
  { label: 'Chưa thanh toán', value: 'pending', severity: 'warning' },
  { label: 'Một phần', value: 'partial', severity: 'info' },
  { label: 'Đã thanh toán', value: 'paid', severity: 'success' },
  { label: 'Đã hoàn', value: 'refunded', severity: 'secondary' },
];

export const PAYMENT_TYPES = [
  { label: 'Đặt cọc', value: 'deposit', severity: 'info' },
  { label: 'Thanh toán', value: 'payment', severity: 'success' },
  { label: 'Hoàn tiền', value: 'refund', severity: 'danger' },
  { label: 'Hủy giao dịch', value: 'void', severity: 'secondary' },
];

export const BOOKING_TYPES = [
  { label: 'Theo đêm', value: 'night' },
  { label: 'Theo ngày', value: 'day' },
  { label: 'Phòng', value: 'room' },
  { label: 'Combo (phòng + vé)', value: 'combo' },
];

export const PARTNER_PAYMENT_STATUSES = [
  { label: 'Chưa tạo', value: 'not_created' },
  { label: 'Đã tạo', value: 'created' },
  { label: 'Đã thanh toán', value: 'paid' },
];

export const GATEWAYS = [
  { label: 'SePay', value: 'sepay' },
  { label: 'Thủ công', value: 'manual' },
];

// Lookup helpers — convert raw value → Vietnamese label
export function label(list: Array<{ label: string; value: string }>, value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—';
  return list.find((x) => x.value === value)?.label ?? value;
}

export const labelSource         = (v: string | null | undefined) => label(ORDER_SOURCES, v);
export const labelPaymentMethod  = (v: string | null | undefined) => label(PAYMENT_METHODS, v);
export const labelOrderStatus    = (v: string | null | undefined) => label(ORDER_STATUSES, v);
export const labelPaymentStatus  = (v: string | null | undefined) => label(PAYMENT_STATUSES, v);
export const labelPaymentType    = (v: string | null | undefined) => label(PAYMENT_TYPES, v);
export const labelBookingType    = (v: string | null | undefined) => label(BOOKING_TYPES, v);
export const labelPartnerStatus  = (v: string | null | undefined) => label(PARTNER_PAYMENT_STATUSES, v);
export const labelGateway        = (v: string | null | undefined) => label(GATEWAYS, v);
