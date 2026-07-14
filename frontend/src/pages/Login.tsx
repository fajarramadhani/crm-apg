import { useState } from 'react'
import { ApiRequestError } from '../api/client'
import { Button } from '../components/ui'
import { useAuth } from '../context/AuthContext'

export default function Login() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleLogin = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setLoading(true)
    setError(null)

    try {
      await login(email, password)
    } catch (caught: unknown) {
      setError(caught instanceof ApiRequestError ? caught.message : 'Login tidak dapat diproses. Silakan coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#0F2554] via-[#1E3A8A] to-[#1e40af] flex">
      <div className="hidden lg:flex lg:w-1/2 flex-col justify-center p-12 text-white">
        <div>
          <div className="flex items-center gap-3 mb-12">
            <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center">
              <span className="text-[#0F2554] font-black text-lg">A</span>
            </div>
            <div>
              <p className="font-bold text-lg">Tic Hub</p>
              <p className="text-blue-300 text-sm">IT Service Management</p>
            </div>
          </div>

          <h1 className="text-4xl font-bold leading-tight mb-4">
            Kelola Tiket IT
            <br />
            dengan Efisien
          </h1>
          <p className="text-blue-200 text-lg leading-relaxed max-w-md">
            Platform terintegrasi untuk manajemen permintaan dan insiden IT di APG. Dari request hingga deployment —
            terlacak, terukur, dan terkontrol.
          </p>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
          <div className="flex items-center gap-2 mb-8 lg:hidden">
            <div className="w-8 h-8 bg-[#0F2554] rounded-lg flex items-center justify-center">
              <span className="text-white font-black text-sm">A</span>
            </div>
            <span className="font-bold text-gray-900">Tic Hub</span>
          </div>

          <h2 className="text-2xl font-bold text-gray-900 mb-1">Selamat Datang</h2>
          <p className="text-gray-500 text-sm mb-6">Masuk ke sistem CRM APG</p>

          <form className="space-y-4" onSubmit={handleLogin} noValidate={false}>
            {error && (
              <div role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {error}
              </div>
            )}

            <div>
              <label htmlFor="login-email" className="block text-sm font-medium text-gray-700 mb-1">
                Email
              </label>
              <input
                id="login-email"
                name="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="username"
                required
                disabled={loading}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A] disabled:bg-gray-100"
                placeholder="email@apg.co.id"
              />
            </div>
            <div>
              <label htmlFor="login-password" className="block text-sm font-medium text-gray-700 mb-1">
                Password
              </label>
              <input
                id="login-password"
                name="password"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
                required
                disabled={loading}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A] disabled:bg-gray-100"
              />
            </div>

            <Button type="submit" variant="primary" className="w-full justify-center py-2.5" loading={loading}>
              Masuk ke Sistem
            </Button>
          </form>
        </div>
      </div>
    </div>
  )
}
