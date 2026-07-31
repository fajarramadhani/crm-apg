import { useState } from 'react'
import { Mail, Lock, Eye, EyeOff, Loader2 } from 'lucide-react'
import { ApiRequestError } from '../api/client'
import { Button } from '../components/ui'
import { useAuth } from '../context/AuthContext'

export default function Login() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)

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
    <div className="min-h-screen bg-gradient-to-br from-[#0F2554] via-[#1E3A8A] to-[#0b1227] flex items-center justify-center p-6">
      <div className="w-full max-w-5xl grid lg:grid-cols-2 overflow-hidden rounded-3xl shadow-2xl bg-white">
        <div className="hidden lg:flex p-12 flex-col justify-between text-white bg-gradient-to-br from-[#0F2554] via-[#1E3A8A] to-[#1d4ed8]">
          <div>
            <div className="flex items-center gap-3 mb-12">
              <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg">
                <span className="text-[#0F2554] font-black text-lg">A</span>
              </div>
              <div>
                <p className="font-bold text-lg tracking-wide">Tic Hub</p>
                <p className="text-blue-200 text-xs uppercase tracking-widest font-semibold">APG Service Portal</p>
              </div>
            </div>

            <h1 className="text-4xl font-extrabold leading-tight mb-6">
              Selamat Datang <br />
              Kembali.
            </h1>
            <p className="text-blue-100 text-lg leading-relaxed max-w-sm">
              Manajemen tiket IT yang presisi. Terlacak, terukur, dan terkontrol penuh untuk operasional APG.
            </p>
          </div>

          <p className="text-sm text-blue-100/90">Internal Use Only · APG CRM Platform</p>
        </div>

        <div className="p-8 sm:p-10 lg:p-12 bg-white">
          <div className="flex items-center gap-2 mb-6 lg:hidden">
            <div className="w-8 h-8 bg-[#0F2554] rounded-lg flex items-center justify-center">
              <span className="text-white font-black text-sm">A</span>
            </div>
            <span className="font-bold text-gray-900">Tic Hub</span>
          </div>

          <div className="mb-8">
            <h2 className="text-3xl font-extrabold text-gray-900">Masuk</h2>
            <p className="text-gray-600 mt-2">Gunakan kredensial akun APG Anda.</p>
          </div>

          <form className="space-y-5" onSubmit={handleLogin} noValidate>
            {error && (
              <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {error}
              </div>
            )}

            <div>
              <label htmlFor="login-email" className="mb-1.5 block text-sm font-medium text-gray-700">
                Email
              </label>
              <div className="relative group">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[#1E3A8A] transition-colors">
                  <Mail size={18} />
                </div>
                <input
                  id="login-email"
                  name="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="username"
                  required
                  disabled={loading}
                  className="w-full pl-10 pr-3 py-3 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] disabled:bg-gray-100 disabled:text-gray-500"
                  placeholder="email@apg.co.id"
                />
              </div>
            </div>

            <div>
              <label htmlFor="login-password" className="mb-1.5 block text-sm font-medium text-gray-700">
                Password
              </label>
              <div className="relative group">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[#1E3A8A] transition-colors">
                  <Lock size={18} />
                </div>
                <input
                  id="login-password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  autoComplete="current-password"
                  required
                  disabled={loading}
                  className="w-full pl-10 pr-10 py-3 text-sm border border-gray-300 rounded-xl bg-white text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] disabled:bg-gray-100 disabled:text-gray-500"
                  placeholder="Masukkan password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none disabled:opacity-50"
                  disabled={loading}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <Button type="submit" variant="primary" className="w-full justify-center py-3 mt-8 rounded-xl" disabled={loading}>
              {loading ? <Loader2 className="animate-spin" size={20} /> : 'Masuk ke Sistem'}
            </Button>
          </form>
        </div>
      </div>
    </div>
  )
}
