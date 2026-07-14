import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'
import type { AuthenticatedUser } from '../types'

interface AuthPayload {
  user: AuthenticatedUser
}

export const authRepository = {
  async login(email: string, password: string): Promise<AuthenticatedUser> {
    await apiClient.csrf()
    const response = await apiClient.post<ApiResponse<AuthPayload>>('/auth/login', { email, password })
    return response.data.user
  },

  async me(): Promise<AuthenticatedUser> {
    const response = await apiClient.get<ApiResponse<AuthPayload>>('/auth/me')
    return response.data.user
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  },
}
