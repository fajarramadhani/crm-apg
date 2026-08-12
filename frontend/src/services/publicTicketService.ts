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
  action?: PublicTicketActionDescriptor | null
}

export type PublicTicketAction = 'uat' | 'confirmation'

export interface PublicTicketActionAttachmentConstraints {
  allowed?: boolean
  max_files?: number
  max_size_mb?: number
  allowed_extensions?: string[]
}

export interface PublicTicketActionDescriptor {
  action: PublicTicketAction | null
  label: string | null
  current_public_status: string | PublicTicketStatus
  verification_required: boolean
  notes_required: { accepted: boolean; rejected: boolean }
  attachments?: PublicTicketActionAttachmentConstraints | null
}

interface PublicTicketActionApiDescriptor {
  type: PublicTicketAction | null
  label: string | null
  current_public_status: PublicTicketStatus
  verification_required: boolean
  notes_required: { accepted: boolean; rejected: boolean } | null
  attachment_constraints: {
    optional: boolean
    max_files: number
    max_size_mb: number
    extensions: string[]
  } | null
}

export interface PublicTicketActionChallenge {
  challenge_token: string
  masked_destination: string
  expires_at: string
  resend_available_at: string
}

export interface PublicTicketActionAccess {
  access_token: string
  expires_at: string
}

export interface PublicTicketActionResult {
  message?: string
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
  action: async (token: string): Promise<PublicTicketActionDescriptor> => {
    const result = (await data(
      apiClient.get<ApiResponse<PublicTicketActionDescriptor>>(
        `/public/tickets/track/${encodeURIComponent(token)}/actions`,
      ),
    )) as unknown as PublicTicketActionApiDescriptor
    return {
      action: result.type,
      label: result.label,
      current_public_status: result.current_public_status,
      verification_required: result.verification_required,
      notes_required: result.notes_required ?? { accepted: false, rejected: true },
      attachments: result.attachment_constraints
        ? {
            allowed: true,
            max_files: result.attachment_constraints.max_files,
            max_size_mb: result.attachment_constraints.max_size_mb,
            allowed_extensions: result.attachment_constraints.extensions,
          }
        : null,
    }
  },
  requestActionChallenge: (token: string, action: PublicTicketAction, email: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketActionChallenge>>(
        `/public/tickets/track/${encodeURIComponent(token)}/actions/challenge`,
        { action, email },
      ),
    ),
  verifyActionChallenge: (token: string, action: PublicTicketAction, challengeToken: string, code: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketActionAccess>>(
        `/public/tickets/track/${encodeURIComponent(token)}/actions/verify`,
        { action, challenge_token: challengeToken, code },
      ),
    ),
  submitUat: (
    token: string,
    accessToken: string,
    idempotencyKey: string,
    outcome: 'accepted' | 'rejected',
    notes: string,
    attachments: File[],
  ) => {
    const formData = new FormData()
    formData.append('outcome', outcome)
    if (notes) formData.append('notes', notes)
    attachments.forEach((file) => formData.append('files[]', file))
    return data(
      apiClient.postForm<ApiResponse<PublicTicketActionResult>>(
        `/public/tickets/track/${encodeURIComponent(token)}/uat`,
        formData,
        { headers: { Authorization: `Bearer ${accessToken}`, 'Idempotency-Key': idempotencyKey } },
      ),
    )
  },
  submitConfirmation: (
    token: string,
    accessToken: string,
    idempotencyKey: string,
    status: 'accepted' | 'rejected',
    notes: string,
    rejectionReason: string,
  ) =>
    data(
      apiClient.post<ApiResponse<PublicTicketActionResult>>(
        `/public/tickets/track/${encodeURIComponent(token)}/confirmation`,
        {
          outcome: status,
          ...((status === 'rejected' ? rejectionReason : notes)
            ? {
                notes: status === 'rejected' ? rejectionReason : notes,
              }
            : {}),
        },
        { headers: { Authorization: `Bearer ${accessToken}`, 'Idempotency-Key': idempotencyKey } },
      ),
    ),
  revokeActionAccess: (token: string, accessToken: string) =>
    data(
      apiClient.post<ApiResponse<null>>(
        `/public/tickets/track/${encodeURIComponent(token)}/actions/revoke`,
        undefined,
        { headers: { Authorization: `Bearer ${accessToken}` } },
      ),
    ),
  requestHistoryChallenge: (branchId: number, email: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryChallenge>>('/public/ticket-history/challenges', {
        identity_type: 'email',
        branch_id: branchId,
        email,
      }),
    ),
  verifyHistoryChallenge: (challengeToken: string, code: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryAccess>>('/public/ticket-history/verify', {
        challenge_token: challengeToken,
        code,
      }),
    ),
  history: async (accessToken: string, page: number, perPage = 10): Promise<PublicTicketHistoryPage> => {
    const response = await apiClient.get<ApiResponse<PublicTicketHistoryItem[]>>(
      `/public/ticket-history?page=${page}&per_page=${perPage}`,
      { headers: { Authorization: `Bearer ${accessToken}` } },
    )
    return { tickets: response.data, pagination: response.meta.pagination }
  },
  revokeHistoryAccess: (accessToken: string) =>
    data(
      apiClient.post<ApiResponse<null>>('/public/ticket-history/revoke', undefined, {
        headers: { Authorization: `Bearer ${accessToken}` },
      }),
    ),
  createHistoryTrackingLink: (accessToken: string, ticketNumber: string) =>
    data(
      apiClient.post<ApiResponse<PublicTicketHistoryTrackingLink>>(
        `/public/ticket-history/tickets/${encodeURIComponent(ticketNumber)}/tracking-link`,
        undefined,
        { headers: { Authorization: `Bearer ${accessToken}` } },
      ),
    ),
}
