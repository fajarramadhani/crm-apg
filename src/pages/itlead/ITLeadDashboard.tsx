import { useNavigate } from 'react-router-dom'
import { TICKETS, CHART_DATA, KPI_DATA, formatDate } from '../../data'
import { KPICard, SectionCard, StatusBadge, PriorityBadge, SLAIndicator, PageHeader, Button, Table, TR, TD } from '../../components/ui'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line } from 'recharts'

const triageQueue = TICKETS.filter(t => t.status === 'validated' || t.status === 'triage')
const overSla = TICKETS.filter(t => t.overSla)
const active = TICKETS.filter(t => !['closed', 'done', 'draft', 'pending_validation', 'rejected'].includes(t.status))

export default function ITLeadDashboard() {
  const navigate = useNavigate()

  return (
    <div>
      <PageHeader title="Dashboard IT Lead" subtitle="Kelola triage, prioritas, SLA, dan penugasan PIC" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Triage Queue" value={triageQueue.length} subtitle="Menunggu penentuan prioritas" color="purple" icon="🎯" />
        <KPICard title="Total Aktif" value={active.length} subtitle="Tiket berjalan" color="blue" icon="⚡" />
        <KPICard title="Over SLA" value={overSla.length} subtitle="Memerlukan eskalasi" color="red" icon="🚨" />
        <KPICard title="SLA Compliance" value={`${KPI_DATA.slaComplianceRate}%`} subtitle="Bulan ini" color="green" icon="📊" />
      </div>

      {/* Over SLA Alert */}
      {overSla.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-red-500 font-bold">🚨</span>
            <p className="text-sm font-bold text-red-800">PERINGATAN: {overSla.length} Tiket Over SLA</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {overSla.map(t => (
              <div key={t.id} className="bg-white border border-red-200 rounded-lg p-3">
                <div className="flex items-center justify-between">
                  <span className="font-mono text-xs text-red-600">{t.id}</span>
                  <PriorityBadge priority={t.priority} />
                </div>
                <p className="text-xs font-medium text-gray-800 mt-1 truncate">{t.title}</p>
                <p className="text-xs text-red-600 mt-1">Terlambat {Math.abs(t.slaRemaining)} jam · PIC: {t.pic || '—'}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Charts */}
        <div className="lg:col-span-2 space-y-5">
          <SectionCard title="Tren Tiket Mingguan">
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={CHART_DATA.weeklyTickets} barGap={4}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="day" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Bar dataKey="masuk" fill="#1E3A8A" radius={[4, 4, 0, 0]} name="Masuk" />
                <Bar dataKey="selesai" fill="#10B981" radius={[4, 4, 0, 0]} name="Selesai" />
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Tren Bulanan">
            <ResponsiveContainer width="100%" height={160}>
              <LineChart data={CHART_DATA.monthlyTrend}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Line type="monotone" dataKey="masuk" stroke="#1E3A8A" strokeWidth={2} dot={{ r: 3 }} name="Masuk" />
                <Line type="monotone" dataKey="selesai" stroke="#10B981" strokeWidth={2} dot={{ r: 3 }} name="Selesai" />
                <Line type="monotone" dataKey="overSla" stroke="#EF4444" strokeWidth={2} dot={{ r: 3 }} name="Over SLA" />
              </LineChart>
            </ResponsiveContainer>
          </SectionCard>
        </div>

        {/* Triage Queue */}
        <div>
          <SectionCard
            title={`Triage Queue (${triageQueue.length})`}
            actions={<Button size="sm" variant="primary" onClick={() => navigate('/itlead/triage')}>Buka</Button>}
          >
            {triageQueue.length === 0 ? (
              <p className="text-sm text-gray-500 text-center py-6">Tidak ada tiket di triage</p>
            ) : (
              <div className="space-y-2">
                {triageQueue.map(t => (
                  <div key={t.id} onClick={() => navigate('/itlead/triage')} className="border border-gray-200 rounded-lg p-3 cursor-pointer hover:bg-gray-50">
                    <div className="flex justify-between items-start">
                      <span className="font-mono text-xs text-gray-400">{t.id}</span>
                      <PriorityBadge priority={t.priority} />
                    </div>
                    <p className="text-xs font-medium text-gray-800 mt-1 line-clamp-2">{t.title}</p>
                    <p className="text-xs text-gray-400 mt-1">{t.requester} · {formatDate(t.createdAt)}</p>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>
      </div>

      {/* Active Tickets Table */}
      <div className="mt-6">
        <SectionCard title="Semua Tiket Aktif" actions={<Button size="sm" variant="ghost" onClick={() => navigate('/sla-monitoring')}>Monitoring SLA →</Button>}>
          <Table headers={['ID', 'Judul', 'Prioritas', 'Status', 'PIC', 'SLA', 'Aplikasi']}>
            {active.map(t => (
              <TR key={t.id} highlight={t.overSla} onClick={() => navigate(`/user/tickets/${t.id}`)}>
                <TD><span className="font-mono text-xs">{t.id}</span></TD>
                <TD><p className="text-sm font-medium max-w-[200px] truncate">{t.title}</p></TD>
                <TD><PriorityBadge priority={t.priority} /></TD>
                <TD><StatusBadge status={t.status} /></TD>
                <TD><span className="text-xs">{t.pic || '—'}</span></TD>
                <TD><div className="w-28"><SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} /></div></TD>
                <TD><span className="text-xs text-gray-500 max-w-[120px] truncate block">{t.application}</span></TD>
              </TR>
            ))}
          </Table>
        </SectionCard>
      </div>
    </div>
  )
}
