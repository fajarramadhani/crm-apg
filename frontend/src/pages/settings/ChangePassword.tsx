import { useState } from 'react'
import { KeyRound, Loader2 } from 'lucide-react'
import { Navigate, useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { Button } from '../../components/ui'
import { useAuth } from '../../context/AuthContext'
import { DEFAULT_ROUTES } from '../../App'

export default function ChangePassword() {
  const { user, refreshUser, logout } = useAuth()
  const navigate = useNavigate()
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!user) return null
  // Users without a pending rotation keep their normal workspace access.
  if (!user.must_change_password) return <Navigate to={DEFAULT_ROUTES[user.role.key]} replace />

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setLoading(true)
    setError(null)

    try {
      await authServiceChange()
      await refreshUser()
      navigate(DEFAULT_ROUTES[user.role.key], { replace: true })
    } catch (caught: unknown) {
      if (caught instanceof ApiRequestError) {
        const reference = caught.requestId ? ` Referensi: ${caught.requestId}` : ''
        setError(`${caught.message}.${reference}`)
      } else {
        setError('Perubahan password tidak dapat diproses. Silakan coba lagi.')
      }
    } finally {
      setLoading(false)
    }
  }

  const authServiceChange = async () => {
    const { authService } = await import('../../services/authService')
    return authService.changePassword(currentPassword, newPassword, confirmPassword)
  }

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex min-h-[70vh] items-center justify-center p-6">
      <div className="w-full max-w-md rounded-3xl border border-gray-200 bg-white p-8 shadow-xl">
        <div className="mb-6 flex items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#0F2554] text-white">
            <KeyRound size={22} />
          </div>
          <div>
            <h1 className="text-xl font-extrabold text-gray-900">Ganti Password</h1>
            <p className="text-sm text-gray-600">Akun Anda wajib mengganti password sebelum melanjutkan ke sistem.</p>
          </div>
        </div>

        {error && (
          <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        <form className="space-y-5" onSubmit={handleSubmit}>
          <div>
            <label htmlFor="cp-current" className="mb-1.5 block text-sm font-medium text-gray-700">
              Password Saat Ini
            </label>
            <input
              id="cp-current"
              type="password"
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              autoComplete="current-password"
              required
              disabled={loading}
              className="w-full px-3 py-3 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] disabled:bg-gray-100 disabled:text-gray-500"
              placeholder="Masukkan password saat ini"
            />
          </div>

          <div>
            <label htmlFor="cp-new" className="mb-1.5 block text-sm font-medium text-gray-700">
              Password Baru
            </label>
            <input
              id="cp-new"
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              autoComplete="new-password"
              minLength={12}
              required
              disabled={loading}
              className="w-full px-3 py-3 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] disabled:bg-gray-100 disabled:text-gray-500"
              placeholder="Minimal 12 karakter"
            />
          </div>

          <div>
            <label htmlFor="cp-confirm" className="mb-1.5 block text-sm font-medium text-gray-700">
              Konfirmasi Password Baru
            </label>
            <input
              id="cp-confirm"
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              autoComplete="new-password"
              required
              disabled={loading}
              className="w-full px-3 py-3 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] disabled:bg-gray-100 disabled:text-gray-500"
              placeholder="Ulangi password baru"
            />
          </div>

          <Button
            type="submit"
            variant="primary"
            className="w-full justify-center rounded-xl"
            disabled={loading || newPassword !== confirmPassword}
          >
            {loading ? <Loader2 className="animate-spin" size={20} /> : 'Simpan Password Baru'}
          </Button>
        </form>

        <button
          type="button"
          onClick={handleLogout}
          disabled={loading}
          className="mt-6 w-full text-center text-sm text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline disabled:opacity-50"
        >
          Keluar dari akun
        </button>
      </div>
    </div>
  )
}
