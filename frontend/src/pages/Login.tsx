import { useState } from 'react'
import type { Role } from '../types'
import { Button } from '../components/ui'

export default function Login({ onLogin }: { onLogin: (role: Role) => void }) {
  const [email, setEmail] = useState('rina.marlina@apg.co.id')
  const [password, setPassword] = useState('Demo@2026')
  const [loading, setLoading] = useState(false)
  const handleLogin = () => {
    setLoading(true)
    setTimeout(() => {
      setLoading(false)
      onLogin('user')
    }, 800)
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#0F2554] via-[#1E3A8A] to-[#1e40af] flex">
      {/* Left Panel */}
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

      {/* Right Panel */}
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

          <div className="space-y-4">
            <div>
              <label htmlFor="login-email" className="block text-sm font-medium text-gray-700 mb-1">
                Email
              </label>
              <input
                id="login-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]"
                placeholder="email@apg.co.id"
              />
            </div>
            <div>
              <label htmlFor="login-password" className="block text-sm font-medium text-gray-700 mb-1">
                Password
              </label>
              <input
                id="login-password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]"
              />
            </div>

            <Button variant="primary" className="w-full justify-center py-2.5" loading={loading} onClick={handleLogin}>
              Masuk ke Sistem
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
