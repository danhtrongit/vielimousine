import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface ActivityLog {
  id: number;
  actor_user_id: number;
  entity_type: string;
  entity_id: number;
  action: string;
  before_json: Record<string, unknown> | null;
  after_json: Record<string, unknown> | null;
  ip: string | null;
  user_agent: string | null;
  created_at: string;
}

export const activityLogApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<ActivityLog[]>>('/activity-log', { params }).then((r) => r.data),

  get: (id: number) =>
    api.get<Envelope<ActivityLog>>(`/activity-log/${id}`).then((r) => r.data),
};
