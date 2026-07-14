import { useState } from 'react'
import type { Role } from '../types'
import { ROLE_LABELS } from '../data'
import { Button } from '../components/ui'

const DEMO_ACCOUNTS: { role: Role; name: string; email: string; desc: string }[] = [
  { role: 'user', name: 'Rina Marlina', email: 'rina.marlina@apg.co.id', desc: 'Buat & pantau tiket IT' },
  { role: 'supervisor', name: 'Budi Santoso', email: 'budi.santoso@apg.co.id', desc: 'Validasi tiket masuk' },
  { role: 'itlead', name: 'Hendra Wijaya', email: 'hendra.wijaya@apg.co.id', desc: 'Triage, prioritas & assign PIC' },
  { role: 'pic', name: 'Dian Kusuma', email: 'dian.kusuma@apg.co.id', desc: 'Kerjakan & resolve tiket' },
  { role: 'qa', name: 'Sari Pratiwi', email: 'sari.pratiwi@apg.co.id', desc: 'Internal testing & QA' },
  { role: 'manager', name: 'Ahmad Fauzi', email: 'ahmad.fauzi@apg.co.id', desc: 'Approval & monitoring SLA' },
  { role: 'executive', name: 'Direktur Teknologi', email: 'cto@apg.co.id', desc: 'KPI & executive dashboard' },
  { role: 'admin', name: 'Admin Sistem', email: 'admin@apg.co.id', desc: 'Kelola user, role & konfigurasi' },
]

export default function Login({ onLogin }: { onLogin: (role: Role) => void }) {
  const [email, setEmail] = useState('rina.marlina@apg.co.id')
  const [password, setPassword] = useState('Demo@2026')
  const [loading, setLoading] = useState(false)
  const [selected, setSelected] = useState<Role>('user')

  const handleLogin = () => {
    setLoading(true)
    setTimeout(() => {
      setLoading(false)
      onLogin(selected)
    }, 800)
  }

  const handleQuickLogin = (role: Role, email: string) => {
    setSelected(role)
    setEmail(email)
    setLoading(true)
    setTimeout(() => {
      setLoading(false)
      onLogin(role)
    }, 500)
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#0F2554] via-[#1E3A8A] to-[#1e40af] flex">
      {/* Left Panel */}
      <div className="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 text-white">
        <div>
          <div className="flex items-center gap-3 mb-12">
            <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center">
              <span className="text-[#0F2554] font-black text-lg">A</span>
            </div>
            <div>
              <p className="font-bold text-lg">APG Enterprise</p>
              <p className="text-blue-300 text-sm">IT Service Management</p>
            </div>
          </div>

          <h1 className="text-4xl font-bold leading-tight mb-4">
            Kelola Tiket IT<br />dengan Efisien
          </h1>
          <p className="text-blue-200 text-lg leading-relaxed max-w-md">
            Platform terintegrasi untuk manajemen permintaan dan insiden IT di APG.
            Dari request hingga deployment — terlacak, terukur, dan terkontrol.
          </p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-3 gap-4">
          {[
            { value: '142', label: 'Total Tiket Bulan Ini' },
            { value: '82.4%', label: 'SLA Compliance Rate' },
            { value: '31.4j', label: 'Rata-rata Resolusi' },
          ].map(s => (
            <div key={s.label} className="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
              <p className="text-2xl font-bold">{s.value}</p>
              <p className="text-blue-300 text-xs mt-1">{s.label}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Right Panel */}
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
          <div className="flex items-center gap-2 mb-8 lg:hidden">
            <div className="w-8 h-8 bg-[#0F2554] rounded-lg flex items-center justify-center">
              <span className="text-white font-black text-sm">A</span>
            </div>
            <span className="font-bold text-gray-900">APG Enterprise CRM</span>
          </div>

          <h2 className="text-2xl font-bold text-gray-900 mb-1">Selamat Datang</h2>
          <p className="text-gray-500 text-sm mb-6">Masuk ke sistem CRM APG</p>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                type="email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]"
                placeholder="email@apg.co.id"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input
                type="password"
                value={password}
                onChange={e => setPassword(e.target.value)}
                className="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]"
              />
            </div>

            <Button variant="primary" className="w-full justify-center py-2.5" loading={loading} onClick={handleLogin}>
              Masuk ke Sistem
            </Button>
          </div>

          {/* Demo Accounts */}
          <div className="mt-6">
            <div className="flex items-center gap-2 mb-3">
              <div className="flex-1 h-px bg-gray-200" />
              <span className="text-xs text-gray-400 font-medium">Demo Accounts</span>
              <div className="flex-1 h-px bg-gray-200" />
            </div>
            <div className="grid grid-cols-2 gap-2">
              {DEMO_ACCOUNTS.map(acc => (
                <button
                  key={acc.role}
                  onClick={() => handleQuickLogin(acc.role, acc.email)}
                  className={`text-left p-2.5 rounded-lg border transition-all text-xs ${selected === acc.role ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'}`}
                >
                  <p className="font-semibold text-gray-800">{ROLE_LABELS[acc.role]}</p>
                  <p className="text-gray-500 mt-0.5 leading-tight">{acc.desc}</p>
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
