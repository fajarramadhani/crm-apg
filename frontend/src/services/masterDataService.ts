import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export interface MasterItem {
  id: number
  code: string
  name: string
  description?: string | null
  is_active: boolean
}
export interface Division extends MasterItem {
  parent_id: number | null
}
export interface Branch extends MasterItem {
  address?: string | null
  city?: string | null
}
export interface ApplicationModule extends MasterItem {
  application_id: number
}
export interface Application extends MasterItem {
  owner_division_id: number | null
  owner_division?: Pick<Division, 'id' | 'code' | 'name'> | null
  modules?: ApplicationModule[]
}
export interface TicketCategory extends MasterItem {
  type: 'incident' | 'request' | 'change' | 'problem'
}
export interface TicketPriority {
  id: number
  key: string
  name: string
  level: number
  description?: string | null
  is_active: boolean
}
export interface SlaPolicy {
  id: number
  priority_id: number
  response_minutes: number | null
  resolution_minutes: number
  working_calendar_id: number
  is_active: boolean
  priority?: Pick<TicketPriority, 'id' | 'key' | 'name' | 'level'>
  working_calendar?: { id: number; code: string; name: string; timezone: string }
}
export interface WorkingCalendar extends MasterItem {
  timezone: string
  workday_start: string
  workday_end: string
  working_days: number[]
}
export interface Holiday {
  id: number
  working_calendar_id: number
  date: string
  name: string
  is_recurring: boolean
}
export interface Paginated<T> {
  data: T[]
  meta: { request_id: string; pagination: { current_page: number; per_page: number; total: number; last_page: number } }
}

const data = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data

export const masterDataService = {
  getDivisions: () => data(apiClient.get<ApiResponse<Division[]>>('/master/divisions')),
  getBranches: () => data(apiClient.get<ApiResponse<Branch[]>>('/master/branches')),
  getApplications: () => data(apiClient.get<ApiResponse<Application[]>>('/master/applications')),
  getApplicationModules: (applicationId: number) =>
    data(apiClient.get<ApiResponse<ApplicationModule[]>>(`/master/applications/${applicationId}/modules`)),
  getTicketCategories: () => data(apiClient.get<ApiResponse<TicketCategory[]>>('/master/ticket-categories')),
  getTicketPriorities: () => data(apiClient.get<ApiResponse<TicketPriority[]>>('/master/ticket-priorities')),
  getSlaPolicies: () => data(apiClient.get<ApiResponse<SlaPolicy[]>>('/master/sla-policies')),
  getWorkingCalendars: () => data(apiClient.get<ApiResponse<WorkingCalendar[]>>('/master/working-calendars')),
  getHolidays: () => data(apiClient.get<ApiResponse<Holiday[]>>('/master/holidays')),
}

export const adminMasterDataService = {
  getDivisions: () => apiClient.get<Paginated<Division>>('/admin/divisions?per_page=100'),
  createDivision: (payload: Partial<Division>) =>
    data(apiClient.post<ApiResponse<Division>>('/admin/divisions', payload)),
  updateDivision: (id: number, payload: Partial<Division>) =>
    data(apiClient.put<ApiResponse<Division>>(`/admin/divisions/${id}`, payload)),
  deactivateDivision: (id: number) => data(apiClient.delete<ApiResponse<Division>>(`/admin/divisions/${id}`)),
  getApplications: () => apiClient.get<Paginated<Application>>('/admin/applications?per_page=100'),
  createApplication: (payload: Partial<Application>) =>
    data(apiClient.post<ApiResponse<Application>>('/admin/applications', payload)),
  updateApplication: (id: number, payload: Partial<Application>) =>
    data(apiClient.put<ApiResponse<Application>>(`/admin/applications/${id}`, payload)),
  deactivateApplication: (id: number) => data(apiClient.delete<ApiResponse<Application>>(`/admin/applications/${id}`)),
  createModule: (applicationId: number, payload: Partial<ApplicationModule>) =>
    data(apiClient.post<ApiResponse<ApplicationModule>>(`/admin/applications/${applicationId}/modules`, payload)),
  getCategories: () => apiClient.get<Paginated<TicketCategory>>('/admin/ticket-categories?per_page=100'),
  createCategory: (payload: Partial<TicketCategory>) =>
    data(apiClient.post<ApiResponse<TicketCategory>>('/admin/ticket-categories', payload)),
  deactivateCategory: (id: number) =>
    data(apiClient.delete<ApiResponse<TicketCategory>>(`/admin/ticket-categories/${id}`)),
  getSlaPolicies: () => apiClient.get<Paginated<SlaPolicy>>('/admin/sla-policies?per_page=100'),
}
