import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'
import type { PicDashboardStats } from '../types'

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
  | 'development_in_progress'
  | 'internal_testing'
  | 'ready_for_qa'
  | 'qa_assignment'
  | 'qa_in_progress'
  | 'qa_failed'
  | 'qa_retest'
  | 'ready_for_uat'
  | 'uat_assignment'
  | 'uat_in_progress'
  | 'uat_failed'
  | 'uat_retest'
  | 'uat_approved'
  | 'approval_pending'
  | 'approval_revision'
  | 'release_preparation'
  | 'release_ready'
  | 'deployment_scheduled'
  | 'deployment_in_progress'
  | 'deployment_failed'
  | 'rollback_in_progress'
  | 'rolled_back'
  | 'deployed'
  | 'monitoring'
  | 'post_release_issue'
  | 'awaiting_requester_confirmation'
  | 'reopened'
  | 'closed'
  | 'rejected'
  | 'transferred'
  | 'cancelled'
  | 'under_analysis'
  | 'need_info'
  | 'waiting_external'
  | 'on_hold'
  | 'in_progress'
  | 'pending_approval'
  | 'done'

export interface TicketAttachmentRecord {
  id: number
  original_name: string
  mime_type: string
  size: number
  category: string
  visibility?: 'internal' | 'requester' | 'requester_visible'
  path?: string
  uploader?: { id: number; name: string }
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
export interface TicketDeploymentStepRecord {
  id: number
  deployment_id: number
  step_number: number
  title: string
  description?: string
  step_type: string
  is_required: boolean
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'skipped'
  executed_by?: number
  started_at?: string
  completed_at?: string
  notes?: string
  evidence_attachment_id?: number
}

export interface TicketDeploymentRecord {
  id: number
  ticket_id: number
  deployment_number: string
  cycle_number: number
  title: string
  description?: string
  environment: string
  release_version: string
  scheduled_start_at: string
  scheduled_end_at?: string
  actual_start_at?: string
  actual_end_at?: string
  status: 'scheduled' | 'in_progress' | 'succeeded' | 'failed' | 'cancelled'
  deployment_summary?: string
  result_summary?: string
  deployment_owner_id: number
  release_owner_id: number
  approved_by?: number
  steps?: TicketDeploymentStepRecord[]
}

export interface TicketMonitoringSessionRecord {
  id: number
  ticket_id: number
  session_number: string
  cycle_number: number
  started_at: string
  ended_at?: string
  status: 'active' | 'completed' | 'incident_reported'
  started_by: number
  completed_by?: number
  overall_result?: 'healthy' | 'unstable' | 'failed'
  notes?: string
}

export interface TicketPostReleaseIncidentRecord {
  id: number
  ticket_id: number
  monitoring_session_id?: number
  incident_number: string
  title: string
  description: string
  reported_at: string
  reported_by: number
  assigned_to: number
  status: 'open' | 'investigating' | 'resolved'
  business_impact?: string
  resolution_summary?: string
  resolved_at?: string
}

export interface TicketClosureRecord {
  id: number
  ticket_id: number
  closure_summary: string
  resolution_summary: string
  business_outcome?: string
  knowledge_base_reference?: string
  closed_at: string
  closed_by: number
  sla_met: boolean
  sla_breach_reason?: string
}

export interface TicketRecord {
  id: number
  ticket_number: string
  title: string
  description: string
  affected_url?: string | null
  reference?: string | null
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
  workflow_mode?: 'dynamic' | 'simplified' | 'legacy' | null
  requester: { id: number; name: string }
  division: { id: number; code: string; name: string }
  current_division: { id: number; code: string; name: string }
  branch?: { id: number; code: string; name: string } | null
  application: { id: number; code: string; name: string } | null
  application_module: { id: number; code: string; name: string } | null
  category: { id: number; code: string; name: string; type: 'incident' | 'request' | 'change' | 'problem' }
  requested_priority: { id: number; key: string; name: string } | null
  final_priority: { id: number; key: string; name: string } | null
  sla_policy: { id: number; response_minutes: number | null; resolution_minutes: number } | null
  assignee: { id: number; name: string } | null
  assignments?: Array<{
    id: number
    assigned_to: number
    assignment_type: 'primary' | 'secondary'
    is_current: boolean
    assignee?: { id: number; name: string }
  }>
  qa_assignee: { id: number; name: string } | null
  qa_cycle_number: number | null
  qa_run_number: number | null
  latest_qa_result: 'passed' | 'failed' | null
  qa_defects?: QaDefectRecord[]
  response_due_at: string | null
  resolution_due_at: string | null
  sla_timezone: string | null
  triage_started_at: string | null
  assigned_at: string | null
  analysis_started_at: string | null
  analysis_completed_at: string | null
  plan_submitted_at: string | null
  plan_approved_at: string | null
  development_started_at: string | null
  development_completed_at: string | null
  internal_testing_started_at: string | null
  internal_testing_completed_at: string | null
  ready_for_qa_at: string | null
  uat_assignee?: { id: number; name: string } | null
  uat_assigned_at?: string | null
  uat_started_at?: string | null
  uat_completed_at?: string | null
  uat_cycle_number?: number | null
  latest_uat_result?: 'accepted' | 'rejected' | null
  uat_approved_at?: string | null
  uat_finding_count?: number
  uat_finding_open_count?: number
  approval_requested_at?: string | null
  approval_completed_at?: string | null
  approved_for_release_at?: string | null
  release_preparation_started_at?: string | null
  release_ready_at?: string | null
  release_risk_level?: string | null
  release_owner?: { id: number; name: string } | null
  approval_summary?: {
    status: string | null
    cycle_number: number | null
    business_approval: string | null
    technical_readiness: string | null
    release_risk_level: string | null
    proposed_release_at: string | null
    revision_reason: string | null
    technical_details_available: boolean
  }
  release_preparation?: {
    plan_status: string | null
    rollback_status: string | null
    checklist_total: number
    checklist_completed: number
    checklist_blocked: number
    release_ready_at: string | null
  }
  progress_percentage: number
  latest_progress_at: string | null
  internal_testing_status: 'in_progress' | 'passed' | null
  actual_work_minutes?: number
  latest_development_update?: {
    progress_percentage: number
    summary: string
    blockers: string[]
    created_at: string
  } | null
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

  // Phase 12 additions
  deployment_cycle_number?: number
  deployment_scheduled_at?: string
  deployment_started_at?: string
  deployed_at?: string
  monitoring_started_at?: string
  monitoring_completed_at?: string
  requester_confirmation_requested_at?: string
  requester_confirmed_at?: string
  closed_at?: string
  closed_by?: number
  latest_deployment_result?: string
  current_deployment_id?: number
  current_monitoring_session_id?: number
  post_release_status?: string
  current_deployment?: TicketDeploymentRecord
  current_monitoring_session?: TicketMonitoringSessionRecord
  user_assignment_role?: 'primary' | 'secondary' | 'supervisor' | null
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
export interface TicketWorklogRecord {
  id: number
  user: { id: number; name: string }
  work_date: string
  minutes_spent: number
  activity_type: string
  description: string
  progress_before: number
  progress_after: number
  created_at: string
}
export interface TicketDevelopmentUpdateRecord {
  id: number
  creator: { id: number; name: string }
  progress_percentage: number
  summary: string
  completed_items: string[]
  remaining_items: string[]
  blockers: string[]
  next_steps: string[]
  created_at: string
}
export interface InternalTestCaseRecord {
  id: number
  case_number: string
  title: string
  preconditions: string | null
  steps: string[]
  expected_result: string
  is_active: boolean
  created_at: string
  updated_at: string
}
export interface InternalTestResultRecord {
  id: number
  test_case_id: number
  status: 'passed' | 'failed' | 'blocked' | 'not_run'
  actual_result: string | null
  notes: string | null
  executed_at: string
}
export interface InternalTestRunRecord {
  id: number
  run_number: number
  started_at: string
  completed_at: string | null
  status: 'in_progress' | 'passed' | 'failed' | 'cancelled'
  environment: string
  build_reference: string | null
  summary: string | null
  results: InternalTestResultRecord[]
}
export interface DevelopmentDetail {
  ticket: TicketRecord
  solution_plan: SolutionPlanRecord | null
  worklogs: TicketWorklogRecord[]
  updates: TicketDevelopmentUpdateRecord[]
  test_cases: InternalTestCaseRecord[]
  test_runs: InternalTestRunRecord[]
}
export interface QaTestCaseRecord {
  id: number
  case_number: string
  title: string
  test_type: string
  preconditions: string | null
  steps: string[]
  expected_result: string
  priority: string
  is_active: boolean
  created_at: string
}
export interface QaTestResultRecord {
  id: number
  qa_test_case_id: number
  status: 'passed' | 'failed' | 'blocked' | 'not_run'
  actual_result: string | null
  notes: string | null
  executed_by?: { id: number; name: string }
  executed_at?: string
}
export interface QaTestRunRecord {
  id: number
  run_number: number
  cycle_number: number
  environment: string
  status: 'in_progress' | 'passed' | 'failed'
  started_at: string
  completed_at: string | null
  summary: string | null
  results?: QaTestResultRecord[]
}
export interface QaDefectRecord {
  id: number
  defect_number: string
  title: string
  description: string
  status: 'open' | 'in_progress' | 'resolved' | 'retest' | 'verified' | 'reopened' | 'closed'
  severity: 'minor' | 'major' | 'critical' | 'blocker'
  priority: 'low' | 'medium' | 'high' | 'urgent'
  steps_to_reproduce: string | null
  expected_result: string | null
  actual_result: string | null
  resolution_notes: string | null
  resolved_at: string | null
  verified_at: string | null
  qa_test_run_id: number | null
  qa_test_case_id: number | null
  created_by?: { id: number; name: string }
  created_at: string
}
export interface QaUserWorkload {
  id: number
  name: string
  active_tickets_count: number
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
export interface ApprovalStepRecord {
  id: number
  step_type: 'business_approval' | 'technical_readiness'
  sequence: number
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  approver: { id: number; name: string }
  assigned_at: string
  acted_at: string | null
  decision_notes: string | null
  version: number
}
export interface ApprovalRequestRecord {
  id: number
  ticket?: { id: number; ticket_number: string; title: string; status: TicketState }
  cycle_number: number
  status: 'draft' | 'pending' | 'approved' | 'rejected' | 'cancelled'
  summary: string
  business_impact: string | null
  release_risk_level: string
  proposed_release_at: string | null
  revision_reason: string | null
  steps: ApprovalStepRecord[]
  version: number
}
export interface ReleaseChecklistItemRecord {
  id: number
  ticket_id: number
  release_plan_id: number
  label: string
  description: string | null
  category: string
  is_required: boolean
  status: 'pending' | 'completed' | 'not_applicable' | 'blocked'
  completed_by: { id: number; name: string } | null
  completed_at: string | null
  notes: string | null
  version: number
}
export interface ReleasePlanRecord {
  id: number
  ticket_id: number
  version: number
  release_owner: { id: number; name: string }
  release_type: string
  target_environment: string
  change_summary: string
  technical_summary?: string
  affected_components?: string[]
  dependencies?: string[] | null
  database_changes?: string | null
  data_migration_required?: boolean
  downtime_required: boolean
  estimated_downtime_minutes: number | null
  proposed_start_at: string | null
  estimated_duration_minutes: number
  validation_steps?: string[]
  monitoring_plan?: string[]
  communication_notes: string | null
  status: string
  lock_version: number
  checklist_items?: ReleaseChecklistItemRecord[]
}
export interface RollbackPlanRecord {
  id: number
  ticket_id: number
  release_plan_id: number
  version: number
  rollback_trigger?: string
  rollback_steps?: string[]
  data_recovery_steps?: string[] | null
  estimated_rollback_minutes: number
  validation_after_rollback?: string[]
  responsible_user: { id: number; name: string }
  status: string
  lock_version: number
}
export interface ReleasePreparationRecord {
  ticket: TicketRecord
  approval: ApprovalRequestRecord | null
  release_plan: ReleasePlanRecord | null
  rollback_plan: RollbackPlanRecord | null
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
  createRequesterTicket: (formData: FormData) =>
    data(apiClient.postForm<ApiResponse<TicketRecord>>('/requester/tickets', formData)),
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
  startDevelopment: (id: number) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/start-development`, {})),
  worklogs: (id: number) => data(apiClient.get<ApiResponse<TicketWorklogRecord[]>>(`/pic/tickets/${id}/worklogs`)),
  addWorklog: (
    id: number,
    payload: {
      work_date: string
      minutes_spent: number
      activity_type: string
      description: string
      progress_after: number
      expected_progress: number
    },
  ) => data(apiClient.post<ApiResponse<TicketWorklogRecord>>(`/pic/tickets/${id}/worklogs`, payload)),
  developmentUpdates: (id: number) =>
    data(apiClient.get<ApiResponse<TicketDevelopmentUpdateRecord[]>>(`/pic/tickets/${id}/development-updates`)),
  addDevelopmentUpdate: (
    id: number,
    payload: {
      progress_percentage: number
      expected_progress: number
      summary: string
      completed_items?: string[]
      remaining_items?: string[]
      blockers?: string[]
      next_steps?: string[]
    },
  ) =>
    data(apiClient.post<ApiResponse<TicketDevelopmentUpdateRecord>>(`/pic/tickets/${id}/development-updates`, payload)),
  uploadDevelopmentEvidence: (id: number, file: File, category = 'development_evidence') => {
    const body = new FormData()
    body.append('file', file)
    body.append('category', category)
    return data(
      apiClient.postForm<ApiResponse<TicketAttachmentRecord>>(`/pic/tickets/${id}/development-evidence`, body),
    )
  },
  uploadUatEvidence: (id: number, file: File, category = 'uat_evidence', findingId?: number) => {
    const body = new FormData()
    body.append('file', file)
    body.append('category', category)
    if (findingId) body.append('uat_finding_id', String(findingId))
    return data(apiClient.postForm<ApiResponse<TicketAttachmentRecord>>(`/requester/tickets/${id}/uat-evidence`, body))
  },
  internalTestCases: (id: number) =>
    data(apiClient.get<ApiResponse<InternalTestCaseRecord[]>>(`/pic/tickets/${id}/internal-test-cases`)),
  createInternalTestCase: (
    id: number,
    payload: { case_number: string; title: string; preconditions?: string; steps: string[]; expected_result: string },
  ) => data(apiClient.post<ApiResponse<InternalTestCaseRecord>>(`/pic/tickets/${id}/internal-test-cases`, payload)),
  updateInternalTestCase: (
    ticketId: number,
    caseId: number,
    payload: { case_number: string; title: string; preconditions?: string; steps: string[]; expected_result: string },
  ) =>
    data(
      apiClient.put<ApiResponse<InternalTestCaseRecord>>(
        `/pic/tickets/${ticketId}/internal-test-cases/${caseId}`,
        payload,
      ),
    ),
  deactivateInternalTestCase: (ticketId: number, caseId: number) =>
    data(
      apiClient.delete<ApiResponse<InternalTestCaseRecord>>(`/pic/tickets/${ticketId}/internal-test-cases/${caseId}`),
    ),
  internalTestRuns: (id: number) =>
    data(apiClient.get<ApiResponse<InternalTestRunRecord[]>>(`/pic/tickets/${id}/internal-test-runs`)),
  startInternalTestRun: (id: number, payload: { environment: string; build_reference?: string; summary?: string }) =>
    data(apiClient.post<ApiResponse<InternalTestRunRecord>>(`/pic/tickets/${id}/internal-test-runs`, payload)),
  recordInternalTestResult: (
    ticketId: number,
    runId: number,
    payload: { test_case_id: number; status: string; actual_result?: string; notes?: string },
  ) =>
    data(
      apiClient.post<ApiResponse<InternalTestResultRecord>>(
        `/pic/tickets/${ticketId}/internal-test-runs/${runId}/results`,
        payload,
      ),
    ),
  completeInternalTestRun: (ticketId: number, runId: number, summary?: string) =>
    data(
      apiClient.post<ApiResponse<InternalTestRunRecord>>(
        `/pic/tickets/${ticketId}/internal-test-runs/${runId}/complete`,
        { summary },
      ),
    ),
  developmentQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/development-queue?${query(filters)}`),
  developmentDetail: (id: number) =>
    data(apiClient.get<ApiResponse<DevelopmentDetail>>(`/it-lead/tickets/${id}/development`)),

  // IT Lead QA penugasan
  assignQa: (id: number, payload: { qa_user_id: number; notes?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}/assign-qa`, payload)),
  qaWorkloads: () => data(apiClient.get<ApiResponse<QaUserWorkload[]>>('/it-lead/qa-workloads')),

  // QA pengujian & eksekusi
  qaAssignments: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/qa/assignments?${query(filters)}`),
  qaStart: (id: number) => data(apiClient.post<ApiResponse<TicketRecord>>(`/qa/tickets/${id}/start`, {})),
  qaTestCases: (id: number) => data(apiClient.get<ApiResponse<QaTestCaseRecord[]>>(`/qa/tickets/${id}/test-cases`)),
  createQaTestCase: (
    id: number,
    payload: {
      case_number: string
      title: string
      test_type: string
      preconditions?: string
      steps: string[]
      expected_result: string
      priority: string
    },
  ) => data(apiClient.post<ApiResponse<QaTestCaseRecord>>(`/qa/tickets/${id}/test-cases`, payload)),
  updateQaTestCase: (
    ticketId: number,
    caseId: number,
    payload: {
      case_number: string
      title: string
      test_type: string
      preconditions?: string
      steps: string[]
      expected_result: string
      priority: string
    },
  ) => data(apiClient.put<ApiResponse<QaTestCaseRecord>>(`/qa/tickets/${ticketId}/test-cases/${caseId}`, payload)),
  deactivateQaTestCase: (ticketId: number, caseId: number) =>
    data(apiClient.delete<ApiResponse<QaTestCaseRecord>>(`/qa/tickets/${ticketId}/test-cases/${caseId}`)),
  qaTestRuns: (id: number) => data(apiClient.get<ApiResponse<QaTestRunRecord[]>>(`/qa/tickets/${id}/test-runs`)),
  startQaTestRun: (id: number, payload: { environment: string; build_reference?: string; summary?: string }) =>
    data(apiClient.post<ApiResponse<QaTestRunRecord>>(`/qa/tickets/${id}/test-runs`, payload)),
  recordQaTestResult: (
    ticketId: number,
    runId: number,
    payload: { qa_test_case_id: number; status: string; actual_result?: string; notes?: string },
  ) =>
    data(
      apiClient.post<ApiResponse<QaTestResultRecord>>(`/qa/tickets/${ticketId}/test-runs/${runId}/results`, payload),
    ),
  completeQaTestRun: (ticketId: number, runId: number, summary?: string) =>
    data(
      apiClient.post<ApiResponse<QaTestRunRecord>>(`/qa/tickets/${ticketId}/test-runs/${runId}/complete`, { summary }),
    ),
  createQaDefect: (
    id: number,
    payload: {
      qa_test_run_id: number
      qa_test_case_id?: number
      title: string
      description: string
      severity: string
      priority: string
      steps_to_reproduce?: string
      expected_result?: string
      actual_result?: string
    },
  ) => data(apiClient.post<ApiResponse<QaDefectRecord>>(`/qa/tickets/${id}/defects`, payload)),
  verifyQaDefect: (ticketId: number, defectId: number, payload: { status: 'verified' | 'reopened'; notes?: string }) =>
    data(apiClient.post<ApiResponse<QaDefectRecord>>(`/qa/tickets/${ticketId}/defects/${defectId}/verify`, payload)),
  reopenQaDefect: (ticketId: number, defectId: number, payload: { notes?: string }) =>
    data(apiClient.post<ApiResponse<QaDefectRecord>>(`/qa/tickets/${ticketId}/defects/${defectId}/reopen`, payload)),

  // PIC Rework
  startDefectFix: (ticketId: number, defectId: number) =>
    data(apiClient.post<ApiResponse<QaDefectRecord>>(`/pic/tickets/${ticketId}/qa-defects/${defectId}/start`, {})),
  resolveDefect: (ticketId: number, defectId: number, payload: { resolution_notes: string }) =>
    data(
      apiClient.post<ApiResponse<QaDefectRecord>>(`/pic/tickets/${ticketId}/qa-defects/${defectId}/resolve`, payload),
    ),
  submitQaRetest: (id: number) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/submit-qa-retest`, {})),

  // IT Lead UAT
  uatQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/uat-assignment-queue?${query(filters)}`),
  assignUat: (id: number, payload: { requester_user_id: number; notes?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}/assign-uat`, payload)),

  // Phase 12 - Deployment & Monitoring
  deploymentQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/deployment-queue?${query(filters)}`),
  scheduleDeployment: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketDeploymentRecord>>(`/tickets/${id}/deployments/schedule`, payload)),
  startDeployment: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketDeploymentRecord>>(`/tickets/${id}/deployments/start`, payload)),
  manageDeploymentStep: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketDeploymentStepRecord>>(`/tickets/${id}/deployments/step`, payload)),
  completeDeployment: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketDeploymentRecord>>(`/tickets/${id}/deployments/complete`, payload)),
  failDeployment: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketDeploymentRecord>>(`/tickets/${id}/deployments/fail`, payload)),

  startMonitoring: (id: number) =>
    data(apiClient.post<ApiResponse<TicketMonitoringSessionRecord>>(`/tickets/${id}/monitoring/start`, {})),
  completeMonitoring: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketMonitoringSessionRecord>>(`/tickets/${id}/monitoring/complete`, payload)),
  reportIncident: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketPostReleaseIncidentRecord>>(`/tickets/${id}/monitoring/incident`, payload)),

  // IT Lead Closure
  closeTicket: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<TicketClosureRecord>>(`/tickets/${id}/close`, payload)),

  // Requester Confirmations
  requesterConfirmationsQueue: () => data(apiClient.get<ApiResponse<TicketRecord[]>>(`/requester/confirmations`)),
  submitRequesterConfirmation: (
    id: number,
    payload: { status: 'accepted' | 'rejected'; rejection_reason?: string; notes?: string },
  ) => data(apiClient.post<ApiResponse<any>>(`/tickets/${id}/confirmation`, payload)),

  // Requester UAT Workspace
  requesterUatAssignments: () => data(apiClient.get<ApiResponse<TicketRecord[]>>('/requester/uat-assignments')),
  startUat: (id: number) => data(apiClient.post<ApiResponse<TicketRecord>>(`/requester/tickets/${id}/uat/start`, {})),
  getUatScenarios: (id: number) => data(apiClient.get<ApiResponse<any[]>>(`/requester/tickets/${id}/uat-scenarios`)),
  createUatScenario: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<any>>(`/requester/tickets/${id}/uat-scenarios`, payload)),
  updateUatScenario: (ticketId: number, scenarioId: number, payload: any) =>
    data(apiClient.put<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-scenarios/${scenarioId}`, payload)),
  deleteUatScenario: (ticketId: number, scenarioId: number) =>
    data(apiClient.delete<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-scenarios/${scenarioId}`)),
  getUatRuns: (id: number) => data(apiClient.get<ApiResponse<any[]>>(`/requester/tickets/${id}/uat-runs`)),
  startUatRun: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<any>>(`/requester/tickets/${id}/uat-runs`, payload)),
  recordUatResult: (ticketId: number, runId: number, payload: any) =>
    data(apiClient.post<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-runs/${runId}/results`, payload)),
  completeUatRun: (ticketId: number, runId: number, summary?: string) =>
    data(apiClient.post<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-runs/${runId}/complete`, { summary })),
  getUatFindings: (id: number) => data(apiClient.get<ApiResponse<any[]>>(`/requester/tickets/${id}/uat-findings`)),
  createUatFinding: (id: number, payload: any) =>
    data(apiClient.post<ApiResponse<any>>(`/requester/tickets/${id}/uat-findings`, payload)),
  verifyUatFinding: (ticketId: number, findingId: number, notes?: string) =>
    data(
      apiClient.post<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-findings/${findingId}/verify`, { notes }),
    ),
  reopenUatFinding: (ticketId: number, findingId: number, notes?: string) =>
    data(
      apiClient.post<ApiResponse<any>>(`/requester/tickets/${ticketId}/uat-findings/${findingId}/reopen`, { notes }),
    ),

  // PIC UAT Rework
  picUatFindings: (id: number) => data(apiClient.get<ApiResponse<any[]>>(`/pic/tickets/${id}/uat-findings`)),
  picStartUatFinding: (ticketId: number, findingId: number) =>
    data(apiClient.post<ApiResponse<any>>(`/pic/tickets/${ticketId}/uat-findings/${findingId}/start`, {})),
  picResolveUatFinding: (ticketId: number, findingId: number, payload: { resolution_notes: string }) =>
    data(apiClient.post<ApiResponse<any>>(`/pic/tickets/${ticketId}/uat-findings/${findingId}/resolve`, payload)),
  picSubmitUatRetest: (id: number, payload: { requires_qa_retest: boolean }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/submit-uat-retest`, payload)),
  uploadPicUatEvidence: (id: number, file: File, findingId?: number, category = 'uat_retest_evidence') => {
    const body = new FormData()
    body.append('file', file)
    body.append('category', category)
    if (findingId) body.append('uat_finding_id', String(findingId))
    return data(apiClient.postForm<ApiResponse<TicketAttachmentRecord>>(`/pic/tickets/${id}/uat-evidence`, body))
  },
  releaseApprovalQueue: (filters: Record<string, string | number | undefined> = {}) =>
    apiClient.get<TicketPage>(`/it-lead/approval-request-queue?${query(filters)}`),
  requestReleaseApproval: (
    id: number,
    payload: { summary: string; business_impact?: string; release_risk_level: string; proposed_release_at?: string },
  ) =>
    data(
      apiClient.post<ApiResponse<ApprovalRequestRecord>>(`/it-lead/tickets/${id}/request-release-approval`, payload),
    ),
  technicalApprovalQueue: () =>
    data(apiClient.get<ApiResponse<ApprovalRequestRecord[]>>('/it-lead/technical-approval-queue')),
  businessApprovalQueue: () =>
    data(apiClient.get<ApiResponse<ApprovalRequestRecord[]>>('/manager/business-approval-queue')),
  businessApproval: (
    id: number,
    decision: 'approve' | 'reject',
    payload: { notes?: string; expected_version: number },
  ) =>
    data(
      apiClient.post<ApiResponse<ApprovalRequestRecord>>(
        `/manager/tickets/${id}/business-approval/${decision}`,
        payload,
      ),
    ),
  technicalApproval: (
    id: number,
    decision: 'approve' | 'reject',
    payload: { notes?: string; expected_version: number },
  ) =>
    data(
      apiClient.post<ApiResponse<ApprovalRequestRecord>>(
        `/it-lead/tickets/${id}/technical-approval/${decision}`,
        payload,
      ),
    ),
  releasePreparation: (id: number) =>
    data(apiClient.get<ApiResponse<ReleasePreparationRecord>>(`/it-lead/tickets/${id}/release-preparation`)),
  picReleasePreparation: (id: number) =>
    data(apiClient.get<ApiResponse<ReleasePreparationRecord>>(`/pic/tickets/${id}/release-preparation`)),
  createReleasePlan: (id: number, payload: Record<string, unknown>) =>
    data(apiClient.post<ApiResponse<ReleasePlanRecord>>(`/it-lead/tickets/${id}/release-plan`, payload)),
  createPicReleasePlan: (id: number, payload: Record<string, unknown>) =>
    data(apiClient.post<ApiResponse<ReleasePlanRecord>>(`/pic/tickets/${id}/release-plan`, payload)),
  submitReleasePlan: (ticketId: number, planId: number) =>
    data(
      apiClient.post<ApiResponse<ReleasePlanRecord>>(`/it-lead/tickets/${ticketId}/release-plan/${planId}/submit`, {}),
    ),
  reviewReleasePlan: (ticketId: number, planId: number, decision: 'approve' | 'request-revision') =>
    data(
      apiClient.post<ApiResponse<ReleasePlanRecord>>(
        `/it-lead/tickets/${ticketId}/release-plan/${planId}/${decision}`,
        { decision },
      ),
    ),
  createRollbackPlan: (id: number, payload: Record<string, unknown>) =>
    data(apiClient.post<ApiResponse<RollbackPlanRecord>>(`/it-lead/tickets/${id}/rollback-plan`, payload)),
  createPicRollbackPlan: (id: number, payload: Record<string, unknown>) =>
    data(apiClient.post<ApiResponse<RollbackPlanRecord>>(`/pic/tickets/${id}/rollback-plan`, payload)),
  reviewRollbackPlan: (ticketId: number, planId: number, decision: 'approve' | 'request-revision') =>
    data(
      apiClient.post<ApiResponse<RollbackPlanRecord>>(
        `/it-lead/tickets/${ticketId}/rollback-plan/${planId}/${decision}`,
        { decision },
      ),
    ),
  releaseChecklist: (id: number) =>
    data(apiClient.get<ApiResponse<ReleaseChecklistItemRecord[]>>(`/it-lead/tickets/${id}/release-checklist`)),
  updateReleaseChecklist: (
    ticketId: number,
    itemId: number,
    payload: { status: string; notes?: string; expected_version: number },
  ) =>
    data(
      apiClient.post<ApiResponse<ReleaseChecklistItemRecord>>(
        `/it-lead/tickets/${ticketId}/release-checklist/${itemId}/decision`,
        payload,
      ),
    ),
  confirmReleaseReady: (id: number) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/it-lead/tickets/${id}/confirm-release-ready`, {})),
  uploadReleaseEvidence: (id: number, file: File, category: string) => {
    const body = new FormData()
    body.append('file', file)
    body.append('category', category)
    return data(apiClient.postForm<ApiResponse<unknown>>(`/pic/tickets/${id}/release-evidence`, body))
  },
  supervisorItDashboard: () =>
    data(
      apiClient.get<
        ApiResponse<{
          stats: {
            new_tickets: number
            under_analysis: number
            unassigned: number
            in_progress: number
            waiting_info: number
            waiting_external: number
            pending_final_review: number
            overdue: number
            completed_today: number
          }
          action_required_tickets: TicketRecord[]
          high_priority_tickets: TicketRecord[]
          unassigned_tickets: TicketRecord[]
          overdue_tickets: TicketRecord[]
          pending_approval_tickets: TicketRecord[]
        }>
      >('/supervisor-it/dashboard'),
    ),
  supervisorItTickets: (params?: Record<string, unknown>) => {
    const query = params
      ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([, v]) => v !== undefined && v !== null && v !== '')
            .map(([k, v]) => [k, String(v)]),
        ).toString()
      : ''
    return apiClient.get<ApiResponse<TicketRecord[]>>(`/supervisor-it/tickets${query}`)
  },
  supervisorItTicketDetail: (id: number | string) =>
    data(apiClient.get<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}`)),
  supervisorItAssignees: (params?: { search?: string; page?: number; per_page?: number }) => {
    const query = params
      ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)]),
        ).toString()
      : ''
    return data(
      apiClient.get<
        ApiResponse<
          Array<{
            id: number
            name: string
            email: string
            role: { id: number; key: string; name: string }
            division: { id: number; name: string } | null
            branch: { id: number; name: string } | null
            active_ticket_count: number
          }>
        >
      >(`/supervisor-it/assignees${query}`),
    )
  },
  analyzeTicket: (id: number | string, payload: Record<string, unknown>) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/analyze`, payload)),
  requestTicketInfo: (id: number | string, payload: { notes: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/request-info`, payload)),
  assignPrimaryPic: (
    id: number | string,
    payload: { user_id: number; notes?: string; reason?: string; target_completed_at?: string },
  ) =>
    data(
      apiClient.post<ApiResponse<{ ticket: TicketRecord; assignment_id: number }>>(
        `/supervisor-it/tickets/${id}/assign-primary`,
        payload,
      ),
    ),
  addSecondaryPic: (id: number | string, payload: { user_id: number; notes?: string; reason?: string }) =>
    data(
      apiClient.post<ApiResponse<{ ticket: TicketRecord; assignment_id: number }>>(
        `/supervisor-it/tickets/${id}/secondary-assignees`,
        payload,
      ),
    ),
  removeSecondaryPic: (id: number | string, userId: number) =>
    data(
      apiClient.delete<ApiResponse<{ ticket: TicketRecord }>>(
        `/supervisor-it/tickets/${id}/secondary-assignees/${userId}`,
      ),
    ),
  reassignPic: (id: number | string, payload: { user_id: number; notes?: string; reason?: string }) =>
    data(
      apiClient.post<ApiResponse<{ ticket: TicketRecord; assignment_id: number }>>(
        `/supervisor-it/tickets/${id}/reassign`,
        payload,
      ),
    ),
  takeoverPic: (id: number | string, payload?: { notes?: string; reason?: string }) =>
    data(
      apiClient.post<ApiResponse<{ ticket: TicketRecord; assignment_id: number }>>(
        `/supervisor-it/tickets/${id}/takeover`,
        payload ?? {},
      ),
    ),
  requestTicketRevision: (id: number | string, payload: { notes: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/request-revision`, payload)),
  approveTicket: (id: number | string, payload?: { notes?: string; summary_for_requester?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/approve`, payload ?? {})),
  rejectTicket: (id: number | string, payload: { reason: string; summary_for_requester?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/reject`, payload)),
  cancelTicket: (id: number | string, payload: { reason: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/cancel`, payload)),
  reopenTicket: (
    id: number | string,
    payload: { reason: string; primary_user_id?: number; target_completion_date?: string },
  ) => data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/reopen`, payload)),
  supervisorCloseTicket: (id: number | string, payload?: { notes?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/supervisor-it/tickets/${id}/close`, payload ?? {})),

  // Stage 6 PIC Workspace Endpoints
  getPicDashboard: () => data(apiClient.get<ApiResponse<PicDashboardSummaryData>>('/pic/dashboard')),
  getPicTickets: (filters: Record<string, any> = {}) =>
    apiClient.get<TicketPage>(`/pic/tickets?${query(filters)}`),
  getPicTicketDetail: (id: number | string) =>
    data(apiClient.get<ApiResponse<PicTicketDetailData>>(`/pic/tickets/${id}`)),
  startPicTicket: (id: number | string) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/start`)),
  addPicWorkNote: (id: number | string, payload: { content: string; visibility?: 'internal' | 'requester_visible' }) =>
    data(
      apiClient.post<ApiResponse<{ id: number; comment: string; is_internal: boolean; created_at: string }>>(
        `/pic/tickets/${id}/work-notes`,
        payload,
      ),
    ),
  uploadPicAttachment: (id: number | string, formData: FormData) =>
    data(
      apiClient.post<ApiResponse<TicketAttachmentRecord>>(`/pic/tickets/${id}/attachments`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      }),
    ),
  updatePicProgress: (id: number | string, payload: { progress_percentage: number; notes?: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/progress`, payload)),
  requestPicInfo: (id: number | string, payload: { question: string }) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/request-info`, payload)),
  markPicWaitingExternal: (
    id: number | string,
    payload: { external_party_name: string; reference_number?: string; follow_up_date?: string; notes: string },
  ) => data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/waiting-external`, payload)),
  resumePicTicket: (id: number | string) =>
    data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/resume`)),
  submitPicInternalCheck: (
    id: number | string,
    payload: { result: 'passed' | 'needs_rework'; notes: string; evidence_attachment_ids?: number[] },
  ) =>
    data(
      apiClient.post<ApiResponse<{ result: string; notes: string; ticket: TicketRecord }>>(
        `/pic/tickets/${id}/internal-check`,
        payload,
      ),
    ),
  submitPicForApproval: (
    id: number | string,
    payload: { result_summary: string; internal_notes?: string; requester_summary?: string; attachment_ids?: number[] },
  ) => data(apiClient.post<ApiResponse<TicketRecord>>(`/pic/tickets/${id}/submit-for-approval`, payload)),
  requestPicAssistance: (
    id: number | string,
    payload: { reason: string; required_expertise?: string; suggested_pic_id?: number },
  ) => data(apiClient.post<ApiResponse<unknown>>(`/pic/tickets/${id}/request-assistance`, payload)),
  requestPicTransfer: (id: number | string, payload: { reason: string; suggested_pic_id?: number }) =>
    data(apiClient.post<ApiResponse<unknown>>(`/pic/tickets/${id}/request-transfer`, payload)),
}

export interface PicDashboardSummaryData {
  summary_cards: PicDashboardStats
  latest_assigned: TicketRecord[]
  high_priority: TicketRecord[]
  nearing_due_tickets: TicketRecord[]
  revision_requested_tickets: TicketRecord[]
  action_required_tickets: TicketRecord[]
}

export interface PicTicketDetailData extends TicketRecord {
  user_assignment_role?: 'primary' | 'secondary' | 'supervisor' | null
  active_primary_pic?: { id: number; name: string } | null
  active_secondary_pics?: Array<{ id: number; name: string }>
}
