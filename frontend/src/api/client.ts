import { env } from '../config/env'
import type { ApiError } from './types'

export interface ApiClient {
  get<T>(path: string, init?: RequestInit): Promise<T>
  post<T>(path: string, body?: unknown, init?: RequestInit): Promise<T>
  put<T>(path: string, body?: unknown, init?: RequestInit): Promise<T>
  delete<T>(path: string, init?: RequestInit): Promise<T>
  csrf(): Promise<void>
}

export class ApiRequestError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly requestId?: string,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message)
  }
}

function cookie(name: string): string | undefined {
  return document.cookie
    .split('; ')
    .find((item) => item.startsWith(`${name}=`))
    ?.split('=')
    .slice(1)
    .join('=')
}

async function fetchCsrfCookie(): Promise<void> {
  const response = await fetch(`${env.backendBaseUrl}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) throw new ApiRequestError('Tidak dapat memulai sesi aman.', response.status)
}

async function request<T>(path: string, init: RequestInit = {}, retriedCsrf = false): Promise<T> {
  const method = init.method?.toUpperCase() || 'GET'
  const xsrfToken = cookie('XSRF-TOKEN')
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')

  if (init.body) headers.set('Content-Type', 'application/json')
  if (method !== 'GET' && method !== 'HEAD' && xsrfToken) {
    headers.set('X-XSRF-TOKEN', decodeURIComponent(xsrfToken))
  }

  const response = await fetch(`${env.apiBaseUrl}${path}`, {
    ...init,
    credentials: 'include',
    headers,
  })
  const payload = (await response.json().catch(() => null)) as ApiError | T | null

  if (response.status === 419 && !retriedCsrf) {
    await fetchCsrfCookie()
    return request<T>(path, init, true)
  }

  if (!response.ok) {
    const error = payload as ApiError | null
    throw new ApiRequestError(
      error?.message || 'Permintaan tidak dapat diproses.',
      response.status,
      error?.error?.code,
      error?.meta?.request_id || response.headers.get('X-Request-ID') || undefined,
      error?.errors,
    )
  }

  return payload as T
}

export const apiClient: ApiClient = {
  get: <T>(path: string, init?: RequestInit) => request<T>(path, { ...init, method: 'GET' }),
  post: <T>(path: string, body?: unknown, init?: RequestInit) =>
    request<T>(path, {
      ...init,
      method: 'POST',
      body: body === undefined ? undefined : JSON.stringify(body),
    }),
  put: <T>(path: string, body?: unknown, init?: RequestInit) =>
    request<T>(path, {
      ...init,
      method: 'PUT',
      body: body === undefined ? undefined : JSON.stringify(body),
    }),
  delete: <T>(path: string, init?: RequestInit) => request<T>(path, { ...init, method: 'DELETE' }),
  async csrf(): Promise<void> {
    await fetchCsrfCookie()
  },
}
