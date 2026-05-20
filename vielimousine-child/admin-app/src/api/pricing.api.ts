import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { RoomPrice } from './roomPrices.api';
import type { SurchargePrice } from './surcharges.api';
import type { TicketPrice } from './ticketPrices.api';

export interface MatrixData {
  room_prices: RoomPrice[];
  surcharge_prices: SurchargePrice[];
  ticket_prices: TicketPrice[];
  date_from: string;
  date_to: string;
}

export type CellChange =
  | { kind: 'room_price'; room_id: number; date: string; fields: Partial<Pick<RoomPrice, 'price' | 'extra_adult_price' | 'stock' | 'is_active' | 'source'>> }
  | { kind: 'surcharge_price'; surcharge_id: number; date: string; fields: { amount?: number; is_active?: boolean } }
  | { kind: 'ticket_price'; hotel_id: number; date: string; fields: { ticket_price?: number; route_id?: number; is_active?: boolean } };

export interface SaveCellsResult {
  saved: number;
  errors: Array<{ index: number; message: string }>;
}

export const pricingApi = {
  matrix: (params: { date_from: string; date_to: string }) =>
    api.get<Envelope<MatrixData>>('/pricing/matrix', { params }).then((r) => r.data),
  saveCells: (changes: CellChange[]) =>
    api.post<Envelope<SaveCellsResult>>('/pricing/cells', { changes }).then((r) => r.data),
};
