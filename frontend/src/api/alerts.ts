import { apiClient } from './client'
import { TicketSlaAlert, OperationalAlertSummary, SlaEscalationPolicy } from '../types/notifications'
import { PaginatedResponse } from '../types/common'

function buildQuery(filters: Record<string, any> = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, String(value))
    }
  })
  const queryString = params.toString()
  return queryString ? `?${queryString}` : ''
}

export const getItLeadAlerts = async (params?: Record<string, any>) => {
  return apiClient.get<PaginatedResponse<TicketSlaAlert>>(`/it-lead/alerts${buildQuery(params)}`)
}

export const getManagerAlerts = async (params?: Record<string, any>) => {
  return apiClient.get<PaginatedResponse<TicketSlaAlert>>(`/manager/alerts${buildQuery(params)}`)
}

export const getExecutiveAlertSummary = async () => {
  const response = await apiClient.get<{ data: OperationalAlertSummary }>('/executive/alerts/summary')
  return response.data
}

export const getSlaPolicies = async (params?: Record<string, any>) => {
  return apiClient.get<PaginatedResponse<SlaEscalationPolicy>>(`/admin/sla-escalation-policies${buildQuery(params)}`)
}

export const createSlaPolicy = async (data: Partial<SlaEscalationPolicy>) => {
  const response = await apiClient.post<{ data: SlaEscalationPolicy }>('/admin/sla-escalation-policies', data)
  return response.data
}

export const updateSlaPolicy = async (id: number, data: Partial<SlaEscalationPolicy>) => {
  const response = await apiClient.put<{ data: SlaEscalationPolicy }>(`/admin/sla-escalation-policies/${id}`, data)
  return response.data
}

export const deactivateSlaPolicy = async (id: number) => {
  return apiClient.delete(`/admin/sla-escalation-policies/${id}`)
}
