import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'
import type { Role } from '../types'

export interface AccountRole {
  id: number
  key: Role
  name: string
  description: string | null
  active_user_count: number
  users_count: number
}

export interface AccountOrganization {
  id: number
  code: string
  name: string
}

export interface AccountOffice {
  id: number
  name: string
  office_type: 'pusat' | 'cabang'
}

export interface AdminAccount {
  id: number
  name: string
  email: string
  phone: string | null
  role: Pick<AccountRole, 'id' | 'key' | 'name'>
  division: AccountOrganization | null
  branch: AccountOrganization | null
  office: AccountOffice | null
  is_active: boolean
  last_login_at: string | null
  created_at: string | null
}

export interface AccountPayload {
  name: string
  email: string
  role_id: number
  division_id: number | null
  branch_id: number | null
  is_active: boolean
  password?: string
  password_confirmation?: string
}

export interface WorkflowStep {
  stage: string
  owner_role: string
  description: string
}

export interface AccountOptions {
  roles: AccountRole[]
  divisions: AccountOrganization[]
  branches: AccountOrganization[]
  offices: AccountOffice[]
  workflow_role_keys: Role[]
  workflow: WorkflowStep[]
}

export interface AccountListResponse extends ApiResponse<AdminAccount[]> {
  meta: ApiResponse<unknown>['meta'] & {
    pagination: NonNullable<ApiResponse<unknown>['meta']['pagination']>
  }
}

const unwrap = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data

export const userService = {
  list: (params: { search?: string; role?: string; isActive?: string; page?: number } = {}) => {
    const query = new URLSearchParams({ per_page: '20' })
    if (params.search) query.set('search', params.search)
    if (params.role) query.set('role', params.role)
    if (params.isActive !== undefined && params.isActive !== '') query.set('is_active', params.isActive)
    if (params.page) query.set('page', String(params.page))
    return apiClient.get<AccountListResponse>(`/admin/users?${query.toString()}`)
  },
  options: () => unwrap(apiClient.get<ApiResponse<AccountOptions>>('/admin/users/options')),
  createRequester: (payload: {
    office_mode: 'pusat' | 'cabang'
    office_id: number | null
    name: string
    email: string
    phone: string
    password: string
    password_confirmation: string
  }) => unwrap(apiClient.post<ApiResponse<AdminAccount>>('/admin/users/requester', payload)),
  createIt: (payload: {
    role: 'supervisor_it' | 'pic_it_develop' | 'pic_it_support'
    name: string
    email: string
    phone: string
    password: string
    password_confirmation: string
  }) => unwrap(apiClient.post<ApiResponse<AdminAccount>>('/admin/users/it', payload)),
  update: (id: number, payload: AccountPayload) =>
    unwrap(apiClient.put<ApiResponse<AdminAccount>>(`/admin/users/${id}`, payload)),
  remove: (id: number) => unwrap(apiClient.delete<ApiResponse<unknown>>(`/admin/users/${id}`)),
  removeRole: (id: number) => unwrap(apiClient.delete<ApiResponse<unknown>>(`/admin/roles/${id}`)),
}
