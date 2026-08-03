import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export interface PublicFormOption {
  id: number
  code: string
  name: string
}

export interface PublicTicketFormOptions {
  branches: PublicFormOption[]
  divisions: PublicFormOption[]
  categories: PublicFormOption[]
  applications: PublicFormOption[]
}

export interface PublicTicketReceipt {
  ticket_number: string
  requester_name: string
  branch_name: string
  title: string
  submitted_at: string
  tracking_token: string
  tracking_url: string
  tracking_expires_at: string | null
}

export interface PublicTicketStatus {
  code: string
  label: string
  description: string
}

export interface PublicTicketTimelineItem extends PublicTicketStatus {
  occurred_at: string
  is_current?: boolean
}

export interface PublicTicketRequesterUpdate {
  message: string
  occurred_at: string
}

export interface PublicTicketTracking {
  ticket_number: string
  title: string
  branch: string
  category: string
  submitted_at: string
  status: PublicTicketStatus
  timeline: PublicTicketTimelineItem[]
  requester_updates: PublicTicketRequesterUpdate[]
  last_updated_at: string
}

export interface PublicTicketHistoryChallenge {
  challenge_token: string
  masked_destination: string
  expires_at: string
  resend_available_at: string
}

export interface PublicTicketHistoryAccess {
  access_token: string
  expires_at: string
}

export interface PublicTicketHistoryItem {
  ticket_number: string
  title: string
  branch: string
  category: string
  submitted_at: string
  status: PublicTicketStatus
  last_updated_at: string
}

export interface PublicTicketHistoryPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PublicTicketHistoryPage {
  tickets: PublicTicketHistoryItem[]
  pagination?: PublicTicketHistoryPagination
}

export interface PublicTicketHistoryTrackingLink {
  tracking_path: string
  tracking_expires_at: string | null
}

const data = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data

export const publicTicketService = {
  options: () => data(apiClient.get<ApiResponse<PublicTicketFormOptions>>('/public/ticket-form-options')),
  submit: (formData: FormData, idempotencyKey: string) =>
    data(
      apiClient.postForm<ApiResponse<PublicTicketReceipt>>('/public/tickets', formData, {
        headers: { 'Idempotency-Key': idempotencyKey },
      }),
    ),
  track: (token: string) =>
    data(apiClient.get<ApiResponse<PublicTicketTracking>>(`/public/tickets/track/${encodeURIComponent(token)}`)),
  requestHistoryChallenge: (branchId: number, email: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryChallenge>>('/public/ticket-history/challenges', {
        branch_id: branchId,
        email,
      }),
    ),
  verifyHistoryChallenge: (challengeToken: string, code: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryAccess>>(
        `/public/ticket-history/challenges/${encodeURIComponent(challengeToken)}/verify`,
        { code },
      ),
    ),
  history: async (accessToken: string, page: number, perPage = 10): Promise<PublicTicketHistoryPage> => {
    const response = await apiClient.get<ApiResponse<PublicTicketHistoryItem[]>>(
      `/public/ticket-history?page=${page}&per_page=${perPage}`,
      { headers: { Authorization: `Bearer ${accessToken}` } },
    )
    return { tickets: response.data, pagination: response.meta.pagination }
  },
  createHistoryTrackingLink: (accessToken: string, ticketNumber: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryTrackingLink>>(
        `/public/ticket-history/tickets/${encodeURIComponent(ticketNumber)}/tracking-link`,
        undefined,
        { headers: { Authorization: `Bearer ${accessToken}` } },
      ),
    ),
}
