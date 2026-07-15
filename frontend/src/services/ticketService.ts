import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export type TicketState =
  | 'draft'
  | 'pending_validation'
  | 'need_revision'
  | 'validated'
  | 'triage'
  | 'assigned'
  | 'analysis'
  | 'solution_planning'
  | 'plan_review'
  | 'ready_for_development'
  | 'rejected'
  | 'transferred'
  | 'cancelled'

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
  final_priority: { id: number; key: string; name: string } | null
  sla_policy: { id: number; response_minutes: number | null; resolution_minutes: number } | null
  assignee: { id: number; name: string } | null
  response_due_at: string | null
  resolution_due_at: string | null
  sla_timezone: string | null
  triage_started_at: string | null
  assigned_at: string | null
  analysis_started_at: string | null
  analysis_completed_at: string | null
  plan_submitted_at: string | null
  plan_approved_at: string | null
  analysis_summary: { status: 'not_started' | 'in_progress' | 'completed'; completed_at: string | null }
  solution_plan_summary: {
    status: 'not_started' | 'draft' | 'submitted' | 'approved'
    submitted_at: string | null
    approved_at: string | null
  }
  solution_plan_preview?: {
    version: number
    estimated_effort_minutes: number
    risk_level: string
    submitted_at: string
  }
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
export interface TicketAnalysisRecord {
  id: number
  version: number
  lock_version: number
  is_current: boolean
  analyst: { id: number; name: string }
  problem_summary: string
  root_cause: string | null
  technical_impact: string
  business_impact: string | null
  affected_components: string[]
  evidence: string | null
  assumptions: string | null
  limitations: string | null
  analysis_started_at: string
  completed_at: string | null
  updated_at: string
}
export type AnalysisPayload = Pick<
  TicketAnalysisRecord,
  | 'problem_summary'
  | 'root_cause'
  | 'technical_impact'
  | 'business_impact'
  | 'affected_components'
  | 'evidence'
  | 'assumptions'
  | 'limitations'
> & { expected_lock_version?: number }
export interface SolutionPlanRecord {
  id: number
  version: number
  lock_version: number
  is_current: boolean
  created_by: { id: number; name: string }
  solution_summary: string
  implementation_steps: { order: number; description: string }[]
  affected_components: string[]
  dependencies: string[]
  estimated_effort_minutes: number
  risk_level: 'low' | 'medium' | 'high' | 'critical'
  risk_description: string | null
  rollback_plan: string | null
  testing_plan: string
  deployment_consideration: string | null
  status: 'draft' | 'submitted' | 'revision_requested' | 'approved'
  submitted_at: string | null
  reviewed_at: string | null
  reviewed_by: { id: number; name: string } | null
  review_notes: string | null
  updated_at: string
}
export type SolutionPlanPayload = Pick<
  SolutionPlanRecord,
  | 'solution_summary'
  | 'implementation_steps'
  | 'affected_components'
  | 'dependencies'
  | 'estimated_effort_minutes'
  | 'risk_level'
  | 'risk_description'
  | 'rollback_plan'
  | 'testing_plan'
  | 'deployment_consideration'
> & { expected_lock_version?: number }
export interface VersionedRecord<T> {
  current: T | null
  versions: T[]
}
export interface PlanReviewDetail {
  ticket: TicketRecord
  analysis: TicketAnalysisRecord
  solution_plan: SolutionPlanRecord
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
export interface PicOption {
  id: number
  name: string
  email: string
  division: { id: number; name: string } | null
  active_assignment_count: number
  critical_count: number
  high_count: number
  workload_indicator: 'low' | 'medium' | 'high'
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
  triageQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/triage-queue?${query(filters)}`),
  itLeadGet: (id: number) => data(apiClient.get<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}`)),
  startTriage: (id: number) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}/start-triage`, {})),
  assign: (id: number, payload: { final_priority_id: number; pic_user_id: number; notes?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}/assign`, payload)),
  picOptions: () => data(apiClient.get<ApiResponse<PicOption[]>>('/it-lead/pic-options')),
  picWorkloads: () => data(apiClient.get<ApiResponse<PicOption[]>>('/it-lead/pic-workloads')),
  picAssignments: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/pic/assignments?${query(filters)}`),
  picGet: (id: number) => data(apiClient.get<ApiResponse<TicketRecord>>(`/pic/tickets/${id}`)),
  startAnalysis: (id: number) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/start-analysis`, {})),
  getAnalysis: (id: number) =>
    data(apiClient.get<ApiResponse<VersionedRecord<TicketAnalysisRecord>>>(`/pic/tickets/${id}/analysis`)),
  createAnalysis: (id: number, payload: AnalysisPayload) =>
    data(apiClient.post<ApiResponse<TicketAnalysisRecord>>(`/pic/tickets/${id}/analysis`, payload)),
  updateAnalysis: (ticketId: number, analysisId: number, payload: AnalysisPayload) =>
    data(apiClient.put<ApiResponse<TicketAnalysisRecord>>(`/pic/tickets/${ticketId}/analysis/${analysisId}`, payload)),
  completeAnalysis: (ticketId: number, analysisId: number) =>
    data(
      apiClient.post<ApiResponse<TicketAnalysisRecord>>(`/pic/tickets/${ticketId}/analysis/${analysisId}/complete`, {}),
    ),
  getSolutionPlan: (id: number) =>
    data(apiClient.get<ApiResponse<VersionedRecord<SolutionPlanRecord>>>(`/pic/tickets/${id}/solution-plan`)),
  createSolutionPlan: (id: number, payload: SolutionPlanPayload) =>
    data(apiClient.post<ApiResponse<SolutionPlanRecord>>(`/pic/tickets/${id}/solution-plan`, payload)),
  updateSolutionPlan: (ticketId: number, planId: number, payload: SolutionPlanPayload) =>
    data(apiClient.put<ApiResponse<SolutionPlanRecord>>(`/pic/tickets/${ticketId}/solution-plan/${planId}`, payload)),
  submitSolutionPlan: (ticketId: number, planId: number) =>
    data(
      apiClient.post<ApiResponse<SolutionPlanRecord>>(`/pic/tickets/${ticketId}/solution-plan/${planId}/submit`, {}),
    ),
  planReviewQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/plan-review-queue?${query(filters)}`),
  planReviewDetail: (id: number) =>
    data(apiClient.get<ApiResponse<PlanReviewDetail>>(`/it-lead/tickets/${id}/solution-plan`)),
  reviewPlan: (ticketId: number, planId: number, action: 'approve' | 'request-revision', review_notes?: string) =>
    data(
      apiClient.post<ApiResponse<SolutionPlanRecord>>(
        `/it-lead/tickets/${ticketId}/solution-plan/${planId}/${action}`,
        { review_notes },
      ),
    ),
}
