import { apiClient } from './client'
import type { ReportFilter, ReportSummaryResponse } from '../types/reports'
import { env } from '../config/env'

function buildQuery(filters: Record<string, any>): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, String(value));
    }
  });
  return params.toString();
}

export const reportApi = {
  getManagerSummary: (filters: ReportFilter) => 
    apiClient.get<ReportSummaryResponse>(`/reports/manager/summary?${buildQuery(filters)}`),
    
  getItLeadSummary: (filters: ReportFilter) => 
    apiClient.get<ReportSummaryResponse>(`/reports/it-lead/summary?${buildQuery(filters)}`),
    
  getSupervisorSummary: (filters: ReportFilter) => 
    apiClient.get<ReportSummaryResponse>(`/reports/supervisor/summary?${buildQuery(filters)}`),
    
  getExecutiveSummary: (filters: ReportFilter) => 
    apiClient.get<ReportSummaryResponse>(`/reports/executive/summary?${buildQuery(filters)}`),
    
  getPicPerformance: (filters: ReportFilter) => 
    apiClient.get<ReportSummaryResponse>(`/reports/pic/performance?${buildQuery(filters)}`),

  exportManagerReport: (filters: ReportFilter, reportType: string, format = 'csv') => {
    const query = buildQuery({ ...filters, report_type: reportType, format });
    window.location.href = `${env.apiBaseUrl}/reports/manager/export?${query}`;
  },

  exportExecutiveReport: (filters: ReportFilter, format = 'csv') => {
    const query = buildQuery({ ...filters, format });
    window.location.href = `${env.apiBaseUrl}/reports/executive/export?${query}`;
  },
}
