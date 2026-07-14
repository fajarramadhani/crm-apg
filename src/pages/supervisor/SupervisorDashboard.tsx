import { useNavigate } from 'react-router-dom'
import { TICKETS, KPI_DATA, formatDate } from '../../data'
import { KPICard, SectionCard, StatusBadge, PriorityBadge, SLAIndicator, PageHeader, Button, Table, TR, TD } from '../../components/ui'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend } from 'recharts'

const pending = TICKETS.filter(t => t.status === 'pending_validation')
const allActive = TICKETS.filter(t => !['closed', 'done', 'draft'].includes(t.status))
const overSla = TICKETS.filter(t => t.overSla)

const byStatus = [
  { name: 'Validasi', value: pending.length, color: '#F59E0B' },
  { name: 'In Progress', value: TICKETS.filter(t => t.status === 'in_progress').length, color: '#3B82F6' },
  { name: 'UAT', value: TICKETS.filter(t => t.status === 'uat').length, color: '#06B6D4' },
  { name: 'Over SLA', value: overSla.length, color: '#EF4444' },
]

export default function SupervisorDashboard() {
  const navigate = useNavigate()

  return (
    <div>
      <PageHeader title="Dashboard Supervisor" subtitle="Validasi tiket masuk dan pantau status tim" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Menunggu Validasi" value={pending.length} subtitle="Perlu aksi Anda" color="amber" icon="✅" />
        <KPICard title="Tiket Aktif Divisi" value={allActive.length} subtitle="Sedang diproses" color="blue" icon="📋" />
        <KPICard title="Over SLA" value={overSla.length} subtitle="Melewati batas waktu" color="red" icon="🚨" />
        <KPICard title="Closed Bulan Ini" value={KPI_DATA.closedThisMonth} subtitle="Tiket selesai" color="green" icon="✓" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Validation Queue */}
        <div className="lg:col-span-2">
          <SectionCard
            title={`Antrean Validasi (${pending.length})`}
            actions={<Button variant="primary" size="sm" onClick={() => navigate('/supervisor/validation-queue')}>Lihat Semua</Button>}
          >
            {pending.length === 0 ? (
              <p className="text-sm text-gray-500 text-center py-8">✅ Tidak ada tiket menunggu validasi</p>
            ) : (
              <div className="space-y-3">
                {pending.map(t => (
                  <div key={t.id} onClick={() => navigate('/supervisor/validation-queue')} className="border border-amber-200 bg-amber-50/30 rounded-xl p-4 cursor-pointer hover:shadow-md transition-all">
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <span className="font-mono text-xs text-gray-400">{t.id}</span>
                        <p className="text-sm font-semibold text-gray-900 mt-0.5">{t.title}</p>
                        <p className="text-xs text-gray-500">{t.requester} · {t.division}</p>
                      </div>
                      <PriorityBadge priority={t.priority} />
                    </div>
                    <div className="flex items-center gap-3">
                      <StatusBadge status={t.status} />
                      <span className="text-xs text-gray-400">{formatDate(t.createdAt)}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        {/* Pie Chart */}
        <div className="space-y-4">
          <SectionCard title="Distribusi Tiket">
            <ResponsiveContainer width="100%" height={200}>
              <PieChart>
                <Pie data={byStatus} cx="50%" cy="50%" outerRadius={70} dataKey="value" label={({ name, value }) => `${name} (${value})`} labelLine={false} fontSize={10}>
                  {byStatus.map((entry, i) => <Cell key={i} fill={entry.color} />)}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </SectionCard>

          {/* Over SLA Alert */}
          {overSla.length > 0 && (
            <SectionCard>
              <div className="space-y-2">
                <p className="text-xs font-semibold text-red-700 flex items-center gap-1">🚨 Over SLA — Perlu Eskalasi</p>
                {overSla.map(t => (
                  <div key={t.id} className="p-2.5 bg-red-50 border border-red-200 rounded-lg">
                    <p className="text-xs font-semibold text-red-800">{t.id}</p>
                    <p className="text-xs text-red-600 truncate">{t.title}</p>
                  </div>
                ))}
              </div>
            </SectionCard>
          )}
        </div>
      </div>

      {/* All Active Tickets Table */}
      <div className="mt-6">
        <SectionCard title="Semua Tiket Aktif">
          <Table headers={['ID', 'Judul', 'Prioritas', 'Status', 'PIC', 'SLA', 'Divisi']}>
            {allActive.slice(0, 8).map(t => (
              <TR key={t.id} highlight={t.overSla} onClick={() => navigate(`/user/tickets/${t.id}`)}>
                <TD><span className="font-mono text-xs">{t.id}</span></TD>
                <TD>
                  <p className="text-sm font-medium truncate max-w-[200px]">{t.title}</p>
                </TD>
                <TD><PriorityBadge priority={t.priority} /></TD>
                <TD><StatusBadge status={t.status} /></TD>
                <TD><span className="text-xs">{t.pic || '—'}</span></TD>
                <TD>
                  <div className="w-28">
                    <SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} />
                  </div>
                </TD>
                <TD><span className="text-xs text-gray-500">{t.division}</span></TD>
              </TR>
            ))}
          </Table>
        </SectionCard>
      </div>
    </div>
  )
}
