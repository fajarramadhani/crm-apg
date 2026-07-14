const defaultApiBaseUrl = 'http://localhost:8000/api/v1'
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL?.replace(/\/$/, '') || defaultApiBaseUrl

export const env = {
  apiBaseUrl,
  backendBaseUrl: import.meta.env.VITE_BACKEND_URL?.replace(/\/$/, '') || apiBaseUrl.replace(/\/api\/v1$/, ''),
} as const
