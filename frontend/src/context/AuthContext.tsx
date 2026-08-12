import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { ApiRequestError, SESSION_EXPIRED_EVENT, type SessionExpiredDetail } from '../api/client'
import { authService } from '../services/authService'
import { useLoading } from './LoadingContext'
import type { AuthenticatedUser, Role } from '../types'

type AuthStatus = 'initializing' | 'authenticating' | 'authenticated' | 'unauthenticated'

interface AuthContextValue {
  user: AuthenticatedUser | null
  status: AuthStatus
  login(email: string, password: string): Promise<AuthenticatedUser>
  logout(): Promise<void>
  hasRole(...roles: Role[]): boolean
  hasPermission(permission: string): boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthenticatedUser | null>(null)
  const [status, setStatus] = useState<AuthStatus>('initializing')

  useEffect(() => {
    let active = true

    authService
      .currentUser()
      .then((currentUser) => {
        if (!active) return
        setUser(currentUser)
        setStatus('authenticated')
      })
      .catch((error: unknown) => {
        if (!active) return
        if (!(error instanceof ApiRequestError) || error.status !== 401)
          console.error('Auth initialization failed', error)
        setUser(null)
        setStatus('unauthenticated')
      })

    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    const expireSession = (event: Event) => {
      const detail = (event as CustomEvent<SessionExpiredDetail>).detail
      if (detail) sessionStorage.setItem('tic-hub:last-session-expired', JSON.stringify(detail))
      setUser(null)
      setStatus('unauthenticated')
    }

    window.addEventListener(SESSION_EXPIRED_EVENT, expireSession)
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, expireSession)
  }, [])

  const { begin, end } = useLoading()

  const login = useCallback(
    async (email: string, password: string) => {
      setStatus('authenticating')
      begin('Sedang masuk ke dashboard Anda...')

      try {
        const authenticatedUser = await authService.login(email, password)
        setUser(authenticatedUser)
        setStatus('authenticated')
        return authenticatedUser
      } catch (error) {
        setUser(null)
        setStatus('unauthenticated')
        throw error
      } finally {
        end()
      }
    },
    [begin, end],
  )

  const logout = useCallback(async () => {
    begin('Keluar dari sesi dan menyimpan keadaan...')
    try {
      setUser(null)
      setStatus('unauthenticated')
      await authService.logout()
    } finally {
      end()
    }
  }, [begin, end])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      status,
      login,
      logout,
      hasRole: (...roles) => Boolean(user && roles.includes(user.role.key)),
      hasPermission: (permission) => Boolean(user?.permissions.includes(permission)),
    }),
    [login, logout, status, user],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside AuthProvider')
  return context
}
