import { reactive } from 'vue';
import { defineStore } from 'pinia';
import { hotelsApi } from '@/api/hotels.api';
import { roomsApi } from '@/api/rooms.api';
import { settingsApi } from '@/api/settings.api';
import { surchargesApi, type Surcharge } from '@/api/surcharges.api';
import { usersApi, type VieUser } from '@/api/users.api';
import type { Hotel, Room } from '@/types/hotel';
import type { Envelope } from '@/types/envelope';
import { decodeEntities } from '@/composables/useFormat';

// Backend clamps per_page at 100 (AbstractRepository::pagination). Lookup
// endpoints must cover the entire active dataset for filtering/UI, so we
// follow pagination metadata and fetch all pages instead of relying on a
// large per_page hint that would be silently truncated.
const LOOKUP_PAGE_SIZE = 100;

async function fetchAllPages<T>(
  list: (params: Record<string, unknown>) => Promise<Envelope<T[]>>,
  params: Record<string, unknown>,
): Promise<T[]> {
  const first = await list({ ...params, per_page: LOOKUP_PAGE_SIZE, page: 1 });
  const totalPages = first.meta?.pagination?.total_pages ?? 1;
  if (totalPages <= 1) return first.data;

  const rest = await Promise.all(
    Array.from({ length: totalPages - 1 }, (_, i) =>
      list({ ...params, per_page: LOOKUP_PAGE_SIZE, page: i + 2 }),
    ),
  );
  return first.data.concat(...rest.map((r) => r.data));
}

export const useLookupStore = defineStore('lookup', {
  state: () => ({
    hotels: [] as Hotel[],
    rooms: [] as Room[],
    surcharges: [] as Surcharge[],
    users: [] as VieUser[],
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
    userById: (s) => (id: number): VieUser | undefined =>
      s.users.find((u) => u.id === id),
    displayUser: (s) => (id: number | null | undefined): string => {
      if (!id) return '—';
      const u = s.users.find((x) => x.id === id);
      return u?.display_name || `User #${id}`;
    },
  },
  actions: {
    async ensureLoaded() {
      if (this.loaded || this.loading) return;
      this.loading = true;
      try {
        const [hotelsAll, roomsAll, surchargesAll, usersResp] = await Promise.all([
          fetchAllPages(hotelsApi.list, { is_active: 1 }),
          fetchAllPages(roomsApi.list, { is_active: 1 }),
          fetchAllPages(surchargesApi.list, { is_active: 1 }),
          usersApi.list().catch(() => ({ data: [] as VieUser[] })),
          loadOrderSources(),
        ]);
        this.hotels = hotelsAll.map((h) => ({ ...h, name: decodeEntities(h.name) }));
        this.rooms = roomsAll.map((r) => ({ ...r, name: decodeEntities(r.name) }));
        this.surcharges = surchargesAll.map((s) => ({ ...s, label: decodeEntities(s.label) }));
        this.users = (usersResp.data ?? []).map((u) => ({ ...u, display_name: decodeEntities(u.display_name) }));
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

// Fallback khi chưa load được cấu hình từ server (Thiết lập → Nguồn đơn).
const DEFAULT_ORDER_SOURCES = [
  { label: 'Website', value: 'website' },
  { label: 'Admin', value: 'admin' },
  { label: 'Phone', value: 'phone' },
  { label: 'Walk-in', value: 'walkin' },
];

export const ORDER_SOURCES = reactive([...DEFAULT_ORDER_SOURCES]);

let orderSourcesLoaded = false;
export async function loadOrderSources(force = false): Promise<void> {
  if (orderSourcesLoaded && !force) return;
  try {
    const resp = await settingsApi.getOrderSources();
    const list = resp.data?.sources;
    if (Array.isArray(list) && list.length > 0) {
      ORDER_SOURCES.splice(
        0,
        ORDER_SOURCES.length,
        ...list.map((s) => ({ value: s.value, label: decodeEntities(s.label) })),
      );
    }
    orderSourcesLoaded = true;
  } catch {
    // giữ danh sách mặc định
  }
}

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
