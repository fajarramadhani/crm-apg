import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'

export interface Office {
  id: number
  name: string
  office_type: 'pusat' | 'cabang'
  created_at: string | null
  updated_at: string | null
}

const unwrap = async <T>(promise: Promise<ApiResponse<T>>): Promise<T> => (await promise).data

export const officeService = {
  list: (officeType = '') =>
    unwrap(apiClient.get<ApiResponse<Office[]>>(`/admin/offices${officeType ? `?office_type=${officeType}` : ''}`)),
  create: (payload: Pick<Office, 'name' | 'office_type'>) =>
    unwrap(apiClient.post<ApiResponse<Office>>('/admin/offices', payload)),
  update: (id: number, payload: Pick<Office, 'name' | 'office_type'>) =>
    unwrap(apiClient.put<ApiResponse<Office>>(`/admin/offices/${id}`, payload)),
  remove: (id: number) => unwrap(apiClient.delete<ApiResponse<unknown>>(`/admin/offices/${id}`)),
}
