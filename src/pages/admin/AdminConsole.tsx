import { useNavigate } from 'react-router-dom'
import { USERS, TICKETS, KPI_DATA } from '../../data'
import { PageHeader, KPICard, SectionCard, Button, Table, TR, TD } from '../../components/ui'

export default function AdminConsole() {
  const navigate = useNavigate()

  const modules = [
    { label: 'Manajemen User & Role', icon: '👥', path: '/admin/users', desc: `${USERS.length} user aktif`, color: 'bg-blue-50 border-blue-200' },
    { label: 'Divisi & Aplikasi', icon: '🏢', path: '/admin/divisions', desc: '9 aplikasi terdaftar', color: 'bg-purple-50 border-purple-200' },
    { label: 'Aturan SLA', icon: '📏', path: '/admin/sla-rules', desc: '4 aturan SLA aktif', color: 'bg-amber-50 border-amber-200' },
    { label: 'Matrix Eskalasi', icon: '🔺', path: '/admin/escalation', desc: '3 level eskalasi', color: 'bg-orange-50 border-orange-200' },
    { label: 'Audit Log', icon: '📜', path: '/admin/audit-log', desc: 'Lacak semua aktivitas', color: 'bg-slate-50 border-slate-200' },
  ]

  return (
    <div>
      <PageHeader title="Admin Console" subtitle="Kelola konfigurasi sistem APG Internal CRM" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total User" value={USERS.length} color="blue" icon="👥" />
        <KPICard title="Total Tiket" value={TICKETS.length} color="purple" icon="📋" />
        <KPICard title="SLA Compliance" value={`${KPI_DATA.slaComplianceRate}%`} color="green" icon="📊" />
        <KPICard title="Aplikasi Aktif" value={9} color="amber" icon="💻" />
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        {modules.map(m => (
          <div
            key={m.path}
            onClick={() => navigate(m.path)}
            className={`border rounded-xl p-5 cursor-pointer hover:shadow-md transition-all ${m.color}`}
          >
            <div className="text-3xl mb-3">{m.icon}</div>
            <p className="text-sm font-bold text-gray-900">{m.label}</p>
            <p className="text-xs text-gray-500 mt-1">{m.desc}</p>
          </div>
        ))}
      </div>

      {/* System Status */}
      <SectionCard title="Status Sistem">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[
            { label: 'API Gateway', status: 'online', latency: '12ms' },
            { label: 'Database Primary', status: 'online', latency: '8ms' },
            { label: 'Email Service', status: 'degraded', latency: '850ms' },
            { label: 'PDF Generator', status: 'online', latency: '45ms' },
            { label: 'Notification Queue', status: 'online', latency: '23ms' },
            { label: 'Backup Service', status: 'online', latency: '—' },
          ].map(s => (
            <div key={s.label} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
              <div className={`w-2.5 h-2.5 rounded-full shrink-0 ${s.status === 'online' ? 'bg-emerald-500' : s.status === 'degraded' ? 'bg-amber-500 animate-pulse' : 'bg-red-500'}`} />
              <div className="flex-1 min-w-0">
                <p className="text-xs font-semibold text-gray-800 truncate">{s.label}</p>
                <p className={`text-xs ${s.status === 'online' ? 'text-emerald-600' : 'text-amber-600'}`}>{s.status} · {s.latency}</p>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>
    </div>
  )
}
