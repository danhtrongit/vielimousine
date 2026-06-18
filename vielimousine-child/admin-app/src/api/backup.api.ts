import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface BackupTable { name: string; rows: number; size_mb: number }
export interface ExportResult { filename: string; sql: string; tables: string[]; bytes: number }
export interface RestoreResult { tables_restored: string[]; statements: number; errors: string[]; snapshot_file: string }

export const backupApi = {
  tables: () => api.get<Envelope<BackupTable[]>>('/backup/tables').then((r) => r.data),
  export: (tables: string[]) => api.post<Envelope<ExportResult>>('/backup/export', { tables }).then((r) => r.data),
  restore: (sql: string, confirm: string) =>
    api.post<Envelope<RestoreResult>>('/backup/restore', { sql, confirm }).then((r) => r.data),
};
