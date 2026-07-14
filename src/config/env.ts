const defaultApiBaseUrl = 'http://localhost:8000/api'

export const env = {
  apiBaseUrl: import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || defaultApiBaseUrl,
} as const
