import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface EmailTemplateConfig {
  enabled: boolean;
  subject: string;
  body: string;
}

export interface EmailConfig {
  from_name: string;
  from_email: string;
  reply_to: string;
  logo_url: string;
  admin_recipients: string[];
  templates: Record<string, EmailTemplateConfig>;
}

export interface EmailSettingsResponse {
  config: EmailConfig;
  template_keys: string[];
}

export interface SepayConfig {
  enabled: boolean;
  merchant_id: string;
  secret_key_set: boolean;
  environment: 'sandbox' | 'production';
  auto_confirm_on_paid: boolean;
}

export interface GeneralConfig {
  site_name: string;
  site_url: string;
  admin_email: string;
  timezone: string;
  currency: string;
}

export const settingsApi = {
  getGeneral: () =>
    api.get<Envelope<GeneralConfig>>('/settings/general').then((r) => r.data),

  getEmail: () =>
    api.get<Envelope<EmailSettingsResponse>>('/settings/email').then((r) => r.data),

  updateEmail: (body: Partial<EmailConfig>) =>
    api.put<Envelope<EmailSettingsResponse>>('/settings/email', body).then((r) => r.data),

  testEmail: (template: string, to?: string) =>
    api.post<Envelope<{ sent: boolean; template: string; to: string }>>('/settings/email/test', { template, to }).then((r) => r.data),

  getSepay: () =>
    api.get<Envelope<SepayConfig>>('/settings/sepay').then((r) => r.data),

  updateSepay: (body: Partial<SepayConfig & { secret_key: string }>) =>
    api.put<Envelope<SepayConfig>>('/settings/sepay', body).then((r) => r.data),
};
