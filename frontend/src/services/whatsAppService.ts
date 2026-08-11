import { apiClient } from '../api/client'

export type WhatsAppStatus = 'queued' | 'processing' | 'sent' | 'pending' | 'failed' | 'invalid' | 'expired'

export interface WhatsAppSettings {
  enabled: boolean
  ready: boolean
  driver: 'fonnte'
  provider: 'FONNTE'
  delivery_mode: 'live' | 'disabled'
  status_message: string
  base_url: string
  country_code: string
  connect_only: boolean
  timeout: number
  retry_times: number
  it_support_number_masked: string
  token_configured: boolean
  webhook_secret_configured: boolean
  last_sent_at: string | null
  stats: Record<WhatsAppStatus, number> & { total: number }
  events: Record<string, boolean>
  config_defaults: {
    enabled: boolean
    events: Record<string, boolean>
  }
  has_database_override: boolean
  templates: Record<string, string>
}

export interface UpdateWhatsAppSettingsPayload {
  enabled: boolean
  events: Record<string, boolean>
}

export interface WhatsAppDeviceProfile {
  success: boolean
  data: {
    device?: string | null
    device_status?: string | null
    status?: string | boolean | null
    package?: string | null
    quota?: string | number | null
    expired?: string | null
    messages?: string | number | null
    [key: string]: unknown
  }
  status: WhatsAppStatus | string | null
  message: string | null
}

export interface WhatsAppHistoryItem {
  id: number
  event_type: string
  ticket_number: string | null
  recipient_role: string | null
  recipient_type: string | null
  recipient_masked: string
  template_name: string
  provider: string
  status: WhatsAppStatus
  attempts: number
  failure_code: string | null
  failure_message: string | null
  queued_at: string | null
  sent_at: string | null
  failed_at: string | null
}

export interface WhatsAppHistoryPage {
  data: WhatsAppHistoryItem[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface TestMessageResponse {
  success: boolean
  message: string
  data?: {
    notification_id: number
    recipient_masked: string
    status: WhatsAppStatus
  }
}

export interface RetryMessageResponse {
  success: boolean
  data: {
    notification_id: number
    status: 'queued'
  }
}

export const whatsAppService = {
  getSettings: (): Promise<WhatsAppSettings> => apiClient.get<WhatsAppSettings>('/admin/whatsapp/settings'),
  updateSettings: (payload: UpdateWhatsAppSettingsPayload): Promise<WhatsAppSettings> =>
    apiClient.patch<WhatsAppSettings>('/admin/whatsapp/settings', payload),
  getDeviceProfile: (): Promise<WhatsAppDeviceProfile> =>
    apiClient.get<WhatsAppDeviceProfile>('/admin/whatsapp/device-profile'),
  getHistory: (page = 1, perPage = 10): Promise<WhatsAppHistoryPage> =>
    apiClient.get<WhatsAppHistoryPage>(`/admin/whatsapp/history?page=${page}&per_page=${perPage}`),
  retryMessage: (id: number): Promise<RetryMessageResponse> =>
    apiClient.post<RetryMessageResponse>(`/admin/whatsapp/history/${id}/retry`),
  sendTestMessage: (note?: string): Promise<TestMessageResponse> =>
    apiClient.post<TestMessageResponse>('/admin/whatsapp/test-message', { note }),
}
