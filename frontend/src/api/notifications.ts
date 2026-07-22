import { apiClient } from './client'
import { Notification, NotificationDetail, NotificationPreference } from '../types/notifications'
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

export const getNotifications = async (params?: Record<string, any>) => {
  return apiClient.get<PaginatedResponse<Notification>>(`/notifications${buildQuery(params)}`)
}

export const getUnreadCount = async () => {
  return apiClient.get<{ count: number }>('/notifications/unread-count')
}

export const getNotificationDetail = async (id: string) => {
  const response = await apiClient.get<{ data: NotificationDetail }>(`/notifications/${id}`)
  return response.data
}

export const markAsRead = async (id: string) => {
  return apiClient.post(`/notifications/${id}/read`)
}

export const markAsUnread = async (id: string) => {
  return apiClient.post(`/notifications/${id}/unread`)
}

export const markAllAsRead = async () => {
  return apiClient.post('/notifications/read-all')
}

export const archiveNotification = async (id: string) => {
  return apiClient.post(`/notifications/${id}/archive`)
}

export const archiveReadNotifications = async () => {
  return apiClient.post('/notifications/archive-read')
}

export const getNotificationPreferences = async () => {
  const response = await apiClient.get<{ data: NotificationPreference[] }>('/notification-preferences')
  return response.data
}

export const updateNotificationPreference = async (type: string, data: Partial<NotificationPreference>) => {
  const response = await apiClient.put<{ data: NotificationPreference }>(`/notification-preferences/${type}`, data)
  return response.data
}
