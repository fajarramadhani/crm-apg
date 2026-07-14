const defaultApiBaseUrl = 'http://localhost:8000/api/v1'

export const env = {
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || defaultApiBaseUrl,
} as const
