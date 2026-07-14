import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import { KPICard, SectionCard, StatusBadge, PriorityBadge, SLAIndicator, PageHeader, Button } from '../../components/ui'

const myTickets = TICKETS.filter((t) => t.picId === 'u4')
const active = myTickets.filter((t) => !['closed', 'done'].includes(t.status))
const overSla = myTickets.filter((t) => t.overSla)

export default function PICDashboard() {
  const navigate = useNavigate()

  return (
    <div>
      <PageHeader title="Dashboard PIC" subtitle="Tiket yang di-assign kepada Anda" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total Tiket Saya" value={myTickets.length} subtitle="Semua waktu" color="blue" icon="💻" />
        <KPICard title="Tiket Aktif" value={active.length} subtitle="Perlu dikerjakan" color="purple" icon="⚡" />
        <KPICard title="Over SLA" value={overSla.length} subtitle="Terlambat" color="red" icon="🚨" />
        <KPICard
          title="Selesai"
          value={myTickets.filter((t) => ['closed', 'done'].includes(t.status)).length}
          subtitle="Diselesaikan"
          color="green"
          icon="✅"
        />
      </div>

      {overSla.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3">
          <span className="text-red-500 text-xl">🚨</span>
          <div>
            <p className="text-sm font-bold text-red-800">Perhatian: {overSla.length} tiket Anda melewati SLA</p>
            <p className="text-xs text-red-600">Segera update progress dan informasikan IT Lead</p>
          </div>
          <Button size="sm" variant="danger" onClick={() => navigate('/pic/workspace')} className="ml-auto">
            Buka Workspace
          </Button>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <SectionCard
            title="Tiket Aktif Saya"
            actions={
              <button onClick={() => navigate('/pic/workspace')} className="text-xs text-[#1E3A8A] hover:underline">
                Buka Workspace →
              </button>
            }
          >
            <div className="space-y-3">
              {active.map((t) => (
                <div
                  key={t.id}
                  onClick={() => navigate('/pic/workspace')}
                  className={`border rounded-xl p-4 cursor-pointer hover:shadow-md transition-all ${t.overSla ? 'border-red-200 bg-red-50/30' : 'border-gray-200 hover:border-[#1E3A8A]/30'}`}
                >
                  {t.overSla && <p className="text-xs text-red-600 font-semibold mb-1">🚨 Over SLA</p>}
                  <div className="flex items-start justify-between mb-2">
                    <div>
                      <span className="font-mono text-xs text-gray-400">{t.id}</span>
                      <p className="text-sm font-semibold text-gray-900">{t.title}</p>
                      <p className="text-xs text-gray-500">
                        {t.application} · {formatDate(t.createdAt)}
                      </p>
                    </div>
                    <div className="flex flex-col items-end gap-1">
                      <PriorityBadge priority={t.priority} />
                      <StatusBadge status={t.status} />
                    </div>
                  </div>
                  <SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} />
                </div>
              ))}
            </div>
          </SectionCard>
        </div>

        <div className="space-y-4">
          <SectionCard title="Aksi Cepat">
            <div className="space-y-2">
              {[
                { label: '💻 Workspace Tiket', path: '/pic/workspace', color: 'bg-[#1E3A8A]/5 text-[#1E3A8A]' },
                { label: '🔍 Root Cause Analysis', path: '/pic/rca', color: 'bg-purple-50 text-purple-700' },
                { label: '🔬 Internal Testing', path: '/pic/testing', color: 'bg-teal-50 text-teal-700' },
              ].map((item) => (
                <button
                  key={item.path}
                  onClick={() => navigate(item.path)}
                  className={`w-full text-left p-3 rounded-xl text-sm font-medium transition-colors ${item.color} hover:opacity-80`}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </SectionCard>

          <SectionCard title="Ringkasan Status">
            {(['assigned', 'in_progress', 'internal_testing', 'uat'] as const).map((status) => {
              const count = myTickets.filter((t) => t.status === status).length
              return (
                <div
                  key={status}
                  className="flex justify-between items-center py-2 border-b border-gray-50 last:border-0"
                >
                  <StatusBadge status={status} />
                  <span className="font-bold text-gray-900">{count}</span>
                </div>
              )
            })}
          </SectionCard>
        </div>
      </div>
    </div>
  )
}
