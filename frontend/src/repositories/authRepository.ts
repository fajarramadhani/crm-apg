import { apiClient } from '../api/client'
import type { ApiResponse } from '../api/types'
import type { AuthenticatedUser } from '../types'

interface AuthPayload {
  user: AuthenticatedUser
}

interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export const authRepository = {
  async login(email: string, password: string): Promise<AuthenticatedUser> {
    await apiClient.csrf()
    const loginResponse = await apiClient.post<ApiResponse<AuthPayload>>('/auth/login', { email, password })

    try {
      const session = await apiClient.get<ApiResponse<AuthPayload>>('/auth/me')
      return session.data.user
    } catch {
      return loginResponse.data.user
    }
  },

  async me(): Promise<AuthenticatedUser> {
    const response = await apiClient.get<ApiResponse<AuthPayload>>('/auth/me')
    return response.data.user
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout')
  },

  async changePassword(payload: ChangePasswordPayload): Promise<AuthenticatedUser> {
    await apiClient.csrf()
    const response = await apiClient.post<ApiResponse<AuthPayload>>('/auth/change-password', payload)
    return response.data.user
  },
}
