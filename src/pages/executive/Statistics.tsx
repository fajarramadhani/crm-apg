import { useState } from 'react'
import { CHART_DATA, KPI_DATA, TICKETS, APPLICATIONS } from '../../data'
import { PageHeader, SectionCard, KPICard, Tabs } from '../../components/ui'
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
} from 'recharts'
import type { PieLabelRenderProps } from 'recharts'

export default function Statistics() {
  const [tab, setTab] = useState('Ringkasan')

  const appData = APPLICATIONS.slice(0, 6).map((app) => ({
    app: app.split(' ')[0],
    tickets: TICKETS.filter((t) => t.application === app).length,
    overSla: TICKETS.filter((t) => t.application === app && t.overSla).length,
  }))

  return (
    <div>
      <PageHeader title="Statistik & Analitik" subtitle="Data kinerja IT Service Management APG — periode Juli 2026" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total Tiket" value={KPI_DATA.totalTickets} subtitle="Bulan ini" color="blue" icon="📋" />
        <KPICard title="SLA Compliance" value={`${KPI_DATA.slaComplianceRate}%`} color="green" icon="📊" />
        <KPICard title="Avg Resolusi" value={`${KPI_DATA.avgResolutionHours}j`} color="amber" icon="⏱️" />
        <KPICard title="Over SLA" value={KPI_DATA.overSlaTickets} color="red" icon="🚨" />
      </div>

      <Tabs tabs={['Ringkasan', 'Aplikasi', 'Divisi & PIC', 'SLA Detail']} active={tab} onChange={setTab} />

      {tab === 'Ringkasan' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <SectionCard title="Tren Tiket Bulanan (6 Bulan)">
            <ResponsiveContainer width="100%" height={240}>
              <LineChart data={CHART_DATA.monthlyTrend}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Line type="monotone" dataKey="masuk" stroke="#1E3A8A" strokeWidth={2} name="Masuk" dot={{ r: 3 }} />
                <Line
                  type="monotone"
                  dataKey="selesai"
                  stroke="#10B981"
                  strokeWidth={2}
                  name="Selesai"
                  dot={{ r: 3 }}
                />
                <Line
                  type="monotone"
                  dataKey="overSla"
                  stroke="#EF4444"
                  strokeWidth={2}
                  name="Over SLA"
                  strokeDasharray="5 5"
                  dot={{ r: 3 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Distribusi Status Tiket">
            <ResponsiveContainer width="100%" height={200}>
              <PieChart>
                <Pie
                  data={CHART_DATA.byStatus}
                  cx="50%"
                  cy="50%"
                  outerRadius={75}
                  dataKey="value"
                  label={({ name, percent }: PieLabelRenderProps) =>
                    `${name ?? ''} ${((percent ?? 0) * 100).toFixed(0)}%`
                  }
                  fontSize={10}
                  labelLine={false}
                >
                  {CHART_DATA.byStatus.map((entry, i) => (
                    <Cell key={i} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              </PieChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Tiket Mingguan — Masuk vs Selesai">
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={CHART_DATA.weeklyTickets} barGap={4} barSize={20}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="day" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Bar dataKey="masuk" fill="#1E3A8A" radius={[4, 4, 0, 0]} name="Masuk" />
                <Bar dataKey="selesai" fill="#10B981" radius={[4, 4, 0, 0]} name="Selesai" />
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Distribusi Prioritas">
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={CHART_DATA.byPriority} layout="vertical" barSize={20}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#f0f0f0" />
                <XAxis type="number" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis
                  dataKey="name"
                  type="category"
                  tick={{ fontSize: 11 }}
                  axisLine={false}
                  tickLine={false}
                  width={60}
                />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Bar dataKey="value" radius={[0, 4, 4, 0]} name="Tiket">
                  {CHART_DATA.byPriority.map((entry, i) => (
                    <Cell key={i} fill={entry.color} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>
        </div>
      )}

      {tab === 'Aplikasi' && (
        <div className="space-y-6">
          <SectionCard title="Tiket per Aplikasi">
            <ResponsiveContainer width="100%" height={280}>
              <BarChart data={CHART_DATA.byApp} barSize={32}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="app" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Bar dataKey="tickets" fill="#1E3A8A" radius={[4, 4, 0, 0]} name="Total Tiket" />
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Detail per Aplikasi">
            <div className="space-y-3">
              {appData.map((app) => (
                <div key={app.app} className="flex items-center gap-4 p-3 bg-gray-50 rounded-xl">
                  <div className="w-10 h-10 bg-[#1E3A8A]/10 rounded-lg flex items-center justify-center shrink-0">
                    <span className="text-[#1E3A8A] font-bold text-sm">{app.app[0]}</span>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-gray-900">{app.app}</p>
                    <div className="flex gap-3 mt-1 text-xs text-gray-500">
                      <span>
                        Total: <strong>{app.tickets}</strong>
                      </span>
                      <span className={app.overSla > 0 ? 'text-red-600 font-semibold' : ''}>
                        Over SLA: <strong>{app.overSla}</strong>
                      </span>
                    </div>
                  </div>
                  <div className="w-24">
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-[#1E3A8A] rounded-full"
                        style={{ width: `${(app.tickets / 45) * 100}%` }}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </SectionCard>
        </div>
      )}

      {tab === 'Divisi & PIC' && (
        <div className="space-y-6">
          <SectionCard title="Performa SLA per Divisi IT">
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={CHART_DATA.divisionPerf} barSize={40}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                <XAxis dataKey="division" tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} formatter={(v) => `${v}%`} />
                <Bar dataKey="slaRate" radius={[4, 4, 0, 0]} name="SLA Rate">
                  {CHART_DATA.divisionPerf.map((entry, i) => (
                    <Cell
                      key={i}
                      fill={entry.slaRate >= 90 ? '#10B981' : entry.slaRate >= 75 ? '#F59E0B' : '#EF4444'}
                    />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </SectionCard>

          <SectionCard title="Beban Kerja PIC">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {[
                { name: 'Dian Kusuma', div: 'IT Development', active: 3, resolved: 12, slaRate: 85 },
                { name: 'Linda Susanti', div: 'IT Development', active: 2, resolved: 8, slaRate: 92 },
              ].map((pic) => (
                <div key={pic.name} className="border border-gray-200 rounded-xl p-4">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-9 h-9 rounded-full bg-[#1E3A8A] text-white flex items-center justify-center text-sm font-bold">
                      {pic.name
                        .split(' ')
                        .map((n) => n[0])
                        .join('')}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-gray-900">{pic.name}</p>
                      <p className="text-xs text-gray-500">{pic.div}</p>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div>
                      <p className="text-lg font-bold text-[#1E3A8A]">{pic.active}</p>
                      <p className="text-xs text-gray-500">Aktif</p>
                    </div>
                    <div>
                      <p className="text-lg font-bold text-emerald-600">{pic.resolved}</p>
                      <p className="text-xs text-gray-500">Selesai</p>
                    </div>
                    <div>
                      <p className={`text-lg font-bold ${pic.slaRate >= 90 ? 'text-emerald-600' : 'text-amber-600'}`}>
                        {pic.slaRate}%
                      </p>
                      <p className="text-xs text-gray-500">SLA Rate</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </SectionCard>
        </div>
      )}

      {tab === 'SLA Detail' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <SectionCard title="SLA Compliance Rate Trend">
            <div className="space-y-3">
              {CHART_DATA.monthlyTrend.map((m) => {
                const rate = Math.round((m.selesai / m.masuk) * 100)
                return (
                  <div key={m.month} className="flex items-center gap-3">
                    <span className="text-sm font-medium text-gray-600 w-8">{m.month}</span>
                    <div className="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                      <div
                        className={`h-full rounded-full ${rate >= 90 ? 'bg-emerald-500' : rate >= 75 ? 'bg-amber-500' : 'bg-red-500'}`}
                        style={{ width: `${rate}%` }}
                      />
                    </div>
                    <span
                      className={`text-sm font-bold w-10 text-right ${rate >= 90 ? 'text-emerald-600' : rate >= 75 ? 'text-amber-600' : 'text-red-600'}`}
                    >
                      {rate}%
                    </span>
                  </div>
                )
              })}
            </div>
          </SectionCard>

          <SectionCard title="Rangkuman SLA Bulan Ini">
            <div className="space-y-4">
              {[
                { label: 'Total tiket aktif', value: 28 },
                { label: 'Dalam SLA', value: 23, color: 'text-emerald-600' },
                { label: 'Over SLA', value: 5, color: 'text-red-600' },
                { label: 'Warning (< 8 jam)', value: 3, color: 'text-amber-600' },
                { label: 'SLA Compliance Rate', value: `${KPI_DATA.slaComplianceRate}%`, color: 'text-[#1E3A8A]' },
                { label: 'Target SLA', value: '90%', color: 'text-gray-500' },
              ].map((item) => (
                <div
                  key={item.label}
                  className="flex justify-between items-center py-2 border-b border-gray-50 last:border-0"
                >
                  <span className="text-sm text-gray-600">{item.label}</span>
                  <span className={`text-base font-bold ${item.color || 'text-gray-900'}`}>{item.value}</span>
                </div>
              ))}
            </div>
          </SectionCard>
        </div>
      )}
    </div>
  )
}
