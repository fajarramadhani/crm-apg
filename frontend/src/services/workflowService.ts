import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export interface WorkflowField {
  id: number
  stage_id: number
  field_name: string
  is_required: boolean
  is_readonly: boolean
  is_hidden: boolean
  created_at?: string
}

export interface WorkflowStage {
  id: number
  workflow_id: number
  stage_key: string
  name: string
  description: string | null
  order: number
  stage_type: 'normal' | 'special' | 'approval'
  is_initial: boolean
  is_terminal: boolean
  fields?: WorkflowField[]
  created_at?: string
}

export interface WorkflowTransitionPermission {
  id: number
  transition_id: number
  role_key: string | null
  permission_code: string | null
}

export interface WorkflowTransitionNotification {
  id: number
  transition_id: number
  recipient_type: 'requester' | 'primary_pic' | 'secondary_pics' | 'supervisor_it' | 'specific_role'
  channel: 'database' | 'email'
  template_code: string | null
}

export interface WorkflowApprovalStep {
  id: number
  approval_config_id: number
  step_order: number
  approver_role_key: string
}

export interface WorkflowApprovalConfig {
  id: number
  workflow_id: number
  stage_id: number
  approval_type: string
  label: string
  notes_required: boolean
  is_active: boolean
  steps?: WorkflowApprovalStep[]
}

export interface WorkflowApproval {
  id: number
  workflow_id: number
  stage_id: number
  approval_type: 'single'
  label: string
  notes_required: boolean
  is_active: boolean
  approver_role_key: 'supervisor_it'
}

export type WorkflowApprovalPayload = Pick<WorkflowApproval, 'stage_id' | 'label' | 'notes_required' | 'is_active'>

export interface WorkflowTransition {
  id: number
  workflow_id: number
  from_stage_id: number
  to_stage_id: number
  action_key: string
  name: string
  requires_notes: boolean
  permissions?: WorkflowTransitionPermission[]
  notifications?: WorkflowTransitionNotification[]
  created_at?: string
}

export interface Workflow {
  id: number
  code: string
  name: string
  description: string | null
  version: number
  is_active: boolean
  config_status: 'draft' | 'published' | 'active' | 'inactive'
  created_by?: number
  created_by_user?: { id: number; name: string }
  published_at?: string | null
  stages_count?: number
  transitions_count?: number
  stages?: WorkflowStage[]
  transitions?: WorkflowTransition[]
  approval_configs?: WorkflowApprovalConfig[]
  is_editable?: boolean
  workflow_type_badge?: string
  created_at?: string
  updated_at?: string
}

const data = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data
const resource = async <T, K extends string>(promise: Promise<ApiResponse<Record<K, T>>>, key: K): Promise<T> =>
  (await promise).data[key]

export type WorkflowStagePayload = Pick<
  WorkflowStage,
  'stage_key' | 'name' | 'description' | 'order' | 'stage_type' | 'is_initial' | 'is_terminal'
>

export type WorkflowFieldPayload = Pick<WorkflowField, 'field_name' | 'is_required' | 'is_readonly' | 'is_hidden'>

export type WorkflowTransitionPayload = Pick<
  WorkflowTransition,
  'from_stage_id' | 'to_stage_id' | 'action_key' | 'name' | 'requires_notes'
> & {
  permissions: Array<{ role_key: string }>
  notifications: Array<{
    recipient_type: WorkflowTransitionNotification['recipient_type']
    channel: WorkflowTransitionNotification['channel']
    template_code: string | null
  }>
}

export const workflowService = {
  getWorkflows: (params?: Record<string, unknown>) => {
    const query = params
      ? '?' +
        new URLSearchParams(
          Object.entries(params)
            .filter(([, v]) => v !== undefined && v !== null && v !== '')
            .map(([k, v]) => [k, String(v)]),
        ).toString()
      : ''
    return apiClient
      .get<ApiResponse<{ workflows: Workflow[]; meta: any }>>(`/admin/workflows${query}`)
      .then((res) => res.data)
  },

  createWorkflow: (payload: { code: string; name: string; description?: string; version?: number }) =>
    resource(apiClient.post<ApiResponse<{ workflow: Workflow }>>('/admin/workflows', payload), 'workflow'),

  getWorkflow: (id: number | string) =>
    resource(apiClient.get<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}`), 'workflow'),

  updateWorkflow: (id: number | string, payload: { name?: string; description?: string }) =>
    resource(apiClient.put<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}`, payload), 'workflow'),

  deleteWorkflow: (id: number | string) => data(apiClient.delete<ApiResponse<any>>(`/admin/workflows/${id}`)),

  validateWorkflow: (id: number | string) =>
    data(apiClient.post<ApiResponse<{ valid: boolean; errors: string[] }>>(`/admin/workflows/${id}/validate`)),

  publishWorkflow: (id: number | string) =>
    resource(apiClient.post<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}/publish`), 'workflow'),

  activateWorkflow: (id: number | string) =>
    resource(apiClient.post<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}/activate`), 'workflow'),

  deactivateWorkflow: (id: number | string) =>
    resource(apiClient.post<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}/deactivate`), 'workflow'),

  createVersion: (id: number | string) =>
    resource(apiClient.post<ApiResponse<{ workflow: Workflow }>>(`/admin/workflows/${id}/create-version`), 'workflow'),

  previewWorkflow: (id: number | string) =>
    resource(apiClient.get<ApiResponse<{ preview: unknown }>>(`/admin/workflows/${id}/preview`), 'preview'),

  getApproval: (id: number | string) =>
    resource(
      apiClient.get<ApiResponse<{ approval: WorkflowApproval | null }>>(`/admin/workflows/${id}/approval`),
      'approval',
    ),

  updateApproval: (id: number | string, payload: WorkflowApprovalPayload) =>
    resource(
      apiClient.put<ApiResponse<{ approval: WorkflowApproval }>>(`/admin/workflows/${id}/approval`, payload),
      'approval',
    ),

  // Stages
  createStage: (workflowId: number | string, payload: WorkflowStagePayload) =>
    resource(
      apiClient.post<ApiResponse<{ stage: WorkflowStage }>>(`/admin/workflows/${workflowId}/stages`, payload),
      'stage',
    ),

  updateStage: (
    workflowId: number | string,
    stageId: number | string,
    payload: Partial<Omit<WorkflowStagePayload, 'stage_key'>>,
  ) =>
    resource(
      apiClient.put<ApiResponse<{ stage: WorkflowStage }>>(`/admin/workflows/${workflowId}/stages/${stageId}`, payload),
      'stage',
    ),

  deleteStage: (workflowId: number | string, stageId: number | string) =>
    data(apiClient.delete<ApiResponse<any>>(`/admin/workflows/${workflowId}/stages/${stageId}`)),

  // Stage Fields
  createField: (workflowId: number | string, stageId: number | string, payload: WorkflowFieldPayload) =>
    resource(
      apiClient.post<ApiResponse<{ field: WorkflowField }>>(
        `/admin/workflows/${workflowId}/stages/${stageId}/fields`,
        payload,
      ),
      'field',
    ),

  deleteField: (workflowId: number | string, stageId: number | string, fieldId: number | string) =>
    data(apiClient.delete<ApiResponse<any>>(`/admin/workflows/${workflowId}/stages/${stageId}/fields/${fieldId}`)),

  // Transitions
  createTransition: (workflowId: number | string, payload: WorkflowTransitionPayload) =>
    resource(
      apiClient.post<ApiResponse<{ transition: WorkflowTransition }>>(
        `/admin/workflows/${workflowId}/transitions`,
        payload,
      ),
      'transition',
    ),

  updateTransition: (
    workflowId: number | string,
    transitionId: number | string,
    payload: Pick<WorkflowTransitionPayload, 'name' | 'requires_notes' | 'permissions' | 'notifications'>,
  ) =>
    resource(
      apiClient.put<ApiResponse<{ transition: WorkflowTransition }>>(
        `/admin/workflows/${workflowId}/transitions/${transitionId}`,
        payload,
      ),
      'transition',
    ),

  deleteTransition: (workflowId: number | string, transitionId: number | string) =>
    data(apiClient.delete<ApiResponse<any>>(`/admin/workflows/${workflowId}/transitions/${transitionId}`)),
}
