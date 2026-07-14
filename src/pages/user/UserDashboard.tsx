import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import {
  KPICard,
  Card,
  StatusBadge,
  PriorityBadge,
  SLAIndicator,
  PageHeader,
  Button,
  SectionCard,
} from '../../components/ui'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

const myTickets = TICKETS.filter((t) => t.requesterId === 'u1')
const weeklyData = [
  { month: 'Apr', total: 3 },
  { month: 'Mei', total: 5 },
  { month: 'Jun', total: 4 },
  { month: 'Jul', total: myTickets.length },
]

export default function UserDashboard() {
  const navigate = useNavigate()
  const open = myTickets.filter((t) => !['closed', 'done'].includes(t.status))
  const overSla = myTickets.filter((t) => t.overSla)

  return (
    <div>
      <PageHeader
        title="Dashboard Saya"
        subtitle="Pantau tiket dan permintaan IT Anda"
        actions={
          <Button variant="primary" onClick={() => navigate('/user/create-ticket')}>
            ➕ Buat Tiket Baru
          </Button>
        }
      />

      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total Tiket Saya" value={myTickets.length} subtitle="Sepanjang waktu" color="blue" icon="📋" />
        <KPICard title="Tiket Aktif" value={open.length} subtitle="Sedang diproses" color="purple" icon="⚡" />
        <KPICard title="Over SLA" value={overSla.length} subtitle="Perlu perhatian" color="red" icon="🚨" />
        <KPICard title="Selesai Bulan Ini" value={1} subtitle="Closed tiket" color="green" icon="✅" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Tiket Aktif */}
        <div className="lg:col-span-2">
          <SectionCard
            title="Tiket Aktif Saya"
            actions={
              <button onClick={() => navigate('/user/tickets')} className="text-xs text-[#1E3A8A] hover:underline">
                Lihat Semua →
              </button>
            }
          >
            <div className="space-y-3">
              {open.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-8">Tidak ada tiket aktif</p>
              ) : (
                open.map((ticket) => (
                  <div
                    key={ticket.id}
                    onClick={() => navigate(`/user/tickets/${ticket.id}`)}
                    className={`border rounded-xl p-4 cursor-pointer hover:shadow-md transition-all ${ticket.overSla ? 'border-red-200 bg-red-50/30' : 'border-gray-200 hover:border-[#1E3A8A]/30'}`}
                  >
                    {ticket.overSla && (
                      <div className="flex items-center gap-1.5 text-xs text-red-600 font-semibold mb-2">
                        <span>🚨</span> Over SLA — Perlu tindakan segera
                      </div>
                    )}
                    <div className="flex items-start justify-between gap-2 mb-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="text-xs text-gray-400 font-mono">{ticket.id}</span>
                          <PriorityBadge priority={ticket.priority} />
                        </div>
                        <p className="text-sm font-semibold text-gray-900 truncate">{ticket.title}</p>
                        <p className="text-xs text-gray-500 mt-0.5">
                          {ticket.application} · {formatDate(ticket.updatedAt)}
                        </p>
                      </div>
                      <StatusBadge status={ticket.status} />
                    </div>
                    <SLAIndicator
                      slaRemaining={ticket.slaRemaining}
                      overSla={ticket.overSla}
                      slaHours={ticket.slaHours}
                    />
                  </div>
                ))
              )}
            </div>
          </SectionCard>
        </div>

        {/* Sidebar */}
        <div className="space-y-4">
          {/* Quick Actions */}
          <SectionCard title="Aksi Cepat">
            <div className="space-y-2">
              <button
                onClick={() => navigate('/user/create-ticket')}
                className="w-full flex items-center gap-3 p-3 bg-[#1E3A8A]/5 hover:bg-[#1E3A8A]/10 rounded-xl text-sm font-medium text-[#1E3A8A] transition-colors"
              >
                <span>➕</span> Buat Tiket Baru
              </button>
              <button
                onClick={() => navigate('/user/uat')}
                className="w-full flex items-center gap-3 p-3 bg-cyan-50 hover:bg-cyan-100 rounded-xl text-sm font-medium text-cyan-700 transition-colors"
              >
                <span>🧪</span> UAT Menunggu Saya
              </button>
              <button
                onClick={() => navigate('/user/tickets')}
                className="w-full flex items-center gap-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl text-sm font-medium text-gray-700 transition-colors"
              >
                <span>📋</span> Lihat Semua Tiket
              </button>
            </div>
          </SectionCard>

          {/* Chart */}
          <SectionCard title="Tiket per Bulan">
            <ResponsiveContainer width="100%" height={140}>
              <BarChart data={weeklyData} barSize={24}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Bar dataKey="total" fill="#1E3A8A" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>

          {/* UAT Pending */}
          {myTickets.filter((t) => t.status === 'uat').length > 0 && (
            <Card className="p-4 border-cyan-200 bg-cyan-50">
              <div className="flex items-start gap-3">
                <span className="text-2xl">🧪</span>
                <div>
                  <p className="text-sm font-semibold text-cyan-800">UAT Menunggu Anda</p>
                  <p className="text-xs text-cyan-600 mt-0.5">
                    Tiket IT-2026-000001 siap untuk UAT. Silakan lakukan pengujian.
                  </p>
                  <button
                    onClick={() => navigate('/user/uat')}
                    className="mt-2 text-xs text-cyan-700 font-semibold hover:underline"
                  >
                    Mulai UAT →
                  </button>
                </div>
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}
