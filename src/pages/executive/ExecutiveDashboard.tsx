import { useNavigate } from 'react-router-dom'
import { CHART_DATA, KPI_DATA, TICKETS } from '../../data'
import { KPICard, SectionCard, PageHeader, Button, StatusBadge, PriorityBadge } from '../../components/ui'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line, PieChart, Pie, Cell } from 'recharts'

export default function ExecutiveDashboard() {
  const navigate = useNavigate()
  const critical = TICKETS.filter(t => t.priority === 'critical' && !['closed', 'done'].includes(t.status))
  const overSla = TICKETS.filter(t => t.overSla)

  return (
    <div>
      <PageHeader
        title="Executive Dashboard"
        subtitle="Ringkasan kinerja IT Service Management — Juli 2026"
        actions={<Button variant="primary" onClick={() => navigate('/executive/statistics')}>📈 Lihat Statistik Detail</Button>}
      />

      {/* Alert Strip */}
      {(critical.length > 0 || overSla.length > 0) && (
        <div className="flex flex-wrap gap-3 mb-6">
          {critical.length > 0 && (
            <div className="flex-1 min-w-[200px] bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-center gap-3">
              <span className="text-red-500 text-lg">🔴</span>
              <span className="text-sm font-semibold text-red-800">{critical.length} Tiket Critical Aktif</span>
            </div>
          )}
          {overSla.length > 0 && (
            <div className="flex-1 min-w-[200px] bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-center gap-3">
              <span className="text-red-500 text-lg">⏰</span>
              <span className="text-sm font-semibold text-red-800">{overSla.length} Tiket Melewati SLA</span>
            </div>
          )}
        </div>
      )}

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total Tiket Bulan Ini" value={KPI_DATA.totalTickets} subtitle="↑ 12% vs bulan lalu" color="blue" icon="📋" />
        <KPICard title="SLA Compliance" value={`${KPI_DATA.slaComplianceRate}%`} subtitle="Target: 90%" color={KPI_DATA.slaComplianceRate >= 90 ? 'green' : 'amber'} icon="📊" />
        <KPICard title="Avg. Resolusi" value={`${KPI_DATA.avgResolutionHours}j`} subtitle="Target: < 30 jam" color={KPI_DATA.avgResolutionHours <= 30 ? 'green' : 'amber'} icon="⏱️" />
        <KPICard title="Tiket Closed" value={KPI_DATA.closedThisMonth} subtitle="Bulan ini" color="green" icon="✅" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Monthly Trend Chart */}
        <div className="lg:col-span-2">
          <SectionCard title="Tren Tiket 6 Bulan Terakhir">
            <ResponsiveContainer width="100%" height={240}>
              <LineChart data={CHART_DATA.monthlyTrend}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 12 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 12 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Line type="monotone" dataKey="masuk" stroke="#1E3A8A" strokeWidth={2.5} dot={{ r: 4 }} name="Masuk" />
                <Line type="monotone" dataKey="selesai" stroke="#10B981" strokeWidth={2.5} dot={{ r: 4 }} name="Selesai" />
                <Line type="monotone" dataKey="overSla" stroke="#EF4444" strokeWidth={2.5} dot={{ r: 4 }} name="Over SLA" strokeDasharray="5 5" />
              </LineChart>
            </ResponsiveContainer>
            <div className="flex gap-4 justify-center mt-2">
              {[{ color: '#1E3A8A', label: 'Masuk' }, { color: '#10B981', label: 'Selesai' }, { color: '#EF4444', label: 'Over SLA' }].map(l => (
                <div key={l.label} className="flex items-center gap-1.5 text-xs text-gray-600">
                  <div className="w-3 h-0.5" style={{ backgroundColor: l.color }} />
                  {l.label}
                </div>
              ))}
            </div>
          </SectionCard>
        </div>

        {/* By Priority */}
        <SectionCard title="Tiket by Prioritas">
          <ResponsiveContainer width="100%" height={180}>
            <PieChart>
              <Pie data={CHART_DATA.byPriority} cx="50%" cy="50%" outerRadius={70} dataKey="value">
                {CHART_DATA.byPriority.map((entry, i) => <Cell key={i} fill={entry.color} />)}
              </Pie>
              <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
            </PieChart>
          </ResponsiveContainer>
          <div className="space-y-2 mt-2">
            {CHART_DATA.byPriority.map(p => (
              <div key={p.name} className="flex items-center gap-2">
                <div className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: p.color }} />
                <span className="text-xs text-gray-600 flex-1">{p.name}</span>
                <span className="text-xs font-bold text-gray-900">{p.value}</span>
              </div>
            ))}
          </div>
        </SectionCard>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {/* By App */}
        <SectionCard title="Tiket per Aplikasi">
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={CHART_DATA.byApp} layout="vertical" barSize={16}>
              <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#f0f0f0" />
              <XAxis type="number" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
              <YAxis dataKey="app" type="category" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} width={50} />
              <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              <Bar dataKey="tickets" fill="#1E3A8A" radius={[0, 4, 4, 0]} name="Tiket" />
            </BarChart>
          </ResponsiveContainer>
        </SectionCard>

        {/* Division Performance */}
        <SectionCard title="Performa Divisi IT">
          <div className="space-y-4">
            {CHART_DATA.divisionPerf.map(d => (
              <div key={d.division}>
                <div className="flex justify-between items-center mb-1.5">
                  <span className="text-sm font-medium text-gray-700">{d.division}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400">{d.tickets} tiket</span>
                    <span className={`text-sm font-bold ${d.slaRate >= 90 ? 'text-emerald-600' : d.slaRate >= 75 ? 'text-amber-600' : 'text-red-600'}`}>
                      {d.slaRate}%
                    </span>
                  </div>
                </div>
                <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                  <div
                    className={`h-full rounded-full ${d.slaRate >= 90 ? 'bg-emerald-500' : d.slaRate >= 75 ? 'bg-amber-500' : 'bg-red-500'}`}
                    style={{ width: `${d.slaRate}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
          <div className="mt-4 flex gap-3 text-xs">
            <span className="flex items-center gap-1"><span className="w-2 h-2 bg-emerald-500 rounded-full" /> ≥90% Target</span>
            <span className="flex items-center gap-1"><span className="w-2 h-2 bg-amber-500 rounded-full" /> 75-89% Warning</span>
            <span className="flex items-center gap-1"><span className="w-2 h-2 bg-red-500 rounded-full" /> &lt;75% Critical</span>
          </div>
        </SectionCard>
      </div>

      {/* Critical Tickets */}
      {critical.length > 0 && (
        <SectionCard title="Tiket Critical Aktif" className="mt-6">
          <div className="space-y-3">
            {critical.map(t => (
              <div key={t.id} className={`flex items-center gap-4 p-3 rounded-xl border ${t.overSla ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'}`}>
                <span className="font-mono text-xs text-gray-500 shrink-0">{t.id}</span>
                <p className="text-sm font-medium text-gray-900 flex-1 truncate">{t.title}</p>
                <StatusBadge status={t.status} />
                {t.overSla && <span className="text-xs font-bold text-red-600">🚨 OVER SLA</span>}
                <span className="text-xs text-gray-500 shrink-0">PIC: {t.pic || '—'}</span>
              </div>
            ))}
          </div>
        </SectionCard>
      )}
    </div>
  )
}
