const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL?.trim() || '/api/v1').replace(/\/$/, '')
const backendBaseUrl = (import.meta.env.VITE_BACKEND_URL?.trim() || apiBaseUrl.replace(/\/api\/v1$/, '')).replace(
  /\/$/,
  '',
)

export const env = {
  apiBaseUrl,
  backendBaseUrl,
} as const
