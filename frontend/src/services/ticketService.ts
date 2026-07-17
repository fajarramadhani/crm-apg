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
  | 'development_in_progress'
  | 'internal_testing'
  | 'ready_for_qa'
  | 'qa_assignment'
  | 'qa_in_progress'
  | 'qa_failed'
  | 'qa_retest'
  | 'ready_for_uat'
  | 'closed'
  | 'rejected'
  | 'transferred'
  | 'cancelled'

export interface TicketAttachmentRecord {
  id: number
  original_name: string
  mime_type: string
  size: number
  category: string
  visibility?: 'internal' | 'requester'
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
}
