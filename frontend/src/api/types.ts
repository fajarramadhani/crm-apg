export interface ApiError {
  message: string
  error?: { code: string }
  errors?: Record<string, string[]>
  meta?: { request_id?: string }
}

export interface ApiResponse<T> {
  success: true
  message: string
  data: T
  meta: { request_id: string }
}
