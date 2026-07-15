import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export type TicketState =
  'draft' | 'pending_validation' | 'need_revision' | 'validated' | 'rejected' | 'transferred' | 'cancelled'

export interface TicketAttachmentRecord {
  id: number
  original_name: string
  mime_type: string
  size: number
  category: string
  created_at: string
}
export interface TicketHistoryRecord {
  id: number
  from_status: string | null
  to_status: string
  action: string
  actor: { id: number; name: string }
  actor_role: string
  notes: string | null
  created_at: string
}
export interface TicketCommentRecord {
  id: number
  type: string
  comment: string
  is_internal: boolean
  user?: { id: number; name: string }
  created_at: string
}
export interface TicketRecord {
  id: number
  ticket_number: string
  title: string
  description: string
  business_impact: string | null
  urgency: string | null
  expected_result: string | null
  actual_result: string | null
  reproduction_steps: string | null
  request_purpose: string | null
  change_reason: string | null
  expected_impact: string | null
  recurring_indication: string | null
  status: TicketState
  requester: { id: number; name: string }
  division: { id: number; code: string; name: string }
  current_division: { id: number; code: string; name: string }
  application: { id: number; code: string; name: string } | null
  application_module: { id: number; code: string; name: string } | null
  category: { id: number; code: string; name: string; type: 'incident' | 'request' | 'change' | 'problem' }
  requested_priority: { id: number; key: string; name: string } | null
  submitted_at: string | null
  validated_at: string | null
  rejected_at: string | null
  created_at: string
  updated_at: string
  allowed_actions: string[]
  attachments: TicketAttachmentRecord[]
  comments: TicketCommentRecord[]
  history: TicketHistoryRecord[]
}
export interface TicketPayload {
  ticket_category_id: number
  application_id?: number
  application_module_id?: number
  requested_priority_id?: number
  title: string
  description: string
  business_impact?: string
  urgency?: string
  expected_result?: string
  actual_result?: string
  reproduction_steps?: string
  request_purpose?: string
  change_reason?: string
  expected_impact?: string
  recurring_indication?: string
}
export interface TicketPage {
  data: TicketRecord[]
  meta: { request_id: string; pagination: { current_page: number; per_page: number; total: number; last_page: number } }
}

const data = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data
const query = (filters: Record<string, string | number | undefined>) => {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(
    ([key, value]) => value !== undefined && value !== '' && params.set(key, String(value)),
  )
  return params.toString()
}

export const ticketService = {
  listMyTickets: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/tickets?${query(filters)}`),
  get: (id: number) => data(apiClient.get<ApiResponse<TicketRecord>>(`/tickets/${id}`)),
  create: (payload: TicketPayload) => data(apiClient.post<ApiResponse<TicketRecord>>('/tickets', payload)),
  update: (id: number, payload: TicketPayload) =>
    data(apiClient.put<ApiResponse<TicketRecord>>(`/tickets/${id}`, payload)),
  resubmit: (id: number, notes?: string) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/tickets/${id}/resubmit`, { notes })),
  cancel: (id: number, notes?: string) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/tickets/${id}/cancel`, { notes })),
  upload: (id: number, file: File, category = 'evidence') => {
    const body = new FormData()
    body.append('file', file)
    body.append('category', category)
    return data(apiClient.postForm<ApiResponse<TicketAttachmentRecord>>(`/tickets/${id}/attachments`, body))
  },
  removeAttachment: (ticketId: number, attachmentId: number) =>
    apiClient.delete<ApiResponse<unknown>>(`/tickets/${ticketId}/attachments/${attachmentId}`),
  downloadUrl: (ticketId: number, attachmentId: number) =>
    `${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1'}/tickets/${ticketId}/attachments/${attachmentId}/download`,
  supervisorQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/supervisor/validation-queue?${query(filters)}`),
  supervisorGet: (id: number) => data(apiClient.get<ApiResponse<TicketRecord>>(`/supervisor/tickets/${id}`)),
  supervisorAction: (
    id: number,
    action: 'validate' | 'request-revision' | 'reject' | 'transfer',
    payload: { notes?: string; target_division_id?: number },
  ) => data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor/tickets/${id}/${action}`, payload)),
}
