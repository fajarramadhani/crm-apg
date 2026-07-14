import { env } from '../config/env'

export interface ApiClient {
  get<T>(path: string, init?: RequestInit): Promise<T>
}

export const apiClient: ApiClient = {
  async get<T>(path: string, init?: RequestInit): Promise<T> {
    const response = await fetch(`${env.apiBaseUrl}${path}`, {
      ...init,
      headers: { Accept: 'application/json', ...init?.headers },
    })

    if (!response.ok) throw new Error(`API request failed with status ${response.status}`)
    return response.json() as Promise<T>
  },
}
