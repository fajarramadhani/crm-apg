import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate, SLA_RULES } from '../../data'
import {
  PageHeader,
  StatusBadge,
  PriorityBadge,
  SLAIndicator,
  Table,
  TR,
  TD,
  FilterBar,
  Select,
  Input,
  KPICard,
  SectionCard,
} from '../../components/ui'

export default function SLAMonitoring() {
  const navigate = useNavigate()
  const [priorityFilter, setPriorityFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [search, setSearch] = useState('')

  const activeTickets = TICKETS.filter((t) => !['closed', 'done', 'draft'].includes(t.status))

  const filtered = activeTickets.filter((t) => {
    const matchPriority = !priorityFilter || t.priority === priorityFilter
    const matchStatus = !statusFilter || t.status === statusFilter
    const matchSearch =
      !search ||
      t.title.toLowerCase().includes(search.toLowerCase()) ||
      t.id.toLowerCase().includes(search.toLowerCase())
    return matchPriority && matchStatus && matchSearch
  })

  const overSla = activeTickets.filter((t) => t.overSla)
  const warning = activeTickets.filter((t) => !t.overSla && t.slaRemaining < 8)
  const onTrack = activeTickets.filter((t) => !t.overSla && t.slaRemaining >= 8)

  return (
    <div>
      <PageHeader title="Monitoring SLA" subtitle="Pantau status SLA seluruh tiket aktif secara real-time" />

      {/* Alert Banner */}
      {overSla.length > 0 && (
        <div className="bg-red-50 border border-red-300 rounded-xl p-4 mb-6">
          <div className="flex items-center gap-2 mb-2">
            <span className="text-red-500">🚨</span>
            <p className="text-sm font-bold text-red-800">
              ALERT: {overSla.length} Tiket Over SLA — Memerlukan Eskalasi Segera
            </p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {overSla.map((t) => (
              <div
                key={t.id}
                onClick={() => navigate(`/user/tickets/${t.id}`)}
                className="bg-white border border-red-200 rounded-lg p-3 cursor-pointer hover:shadow-md transition-all"
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="font-mono text-xs text-red-600 font-bold">{t.id}</span>
                  <PriorityBadge priority={t.priority} />
                </div>
                <p className="text-xs font-medium text-gray-800 truncate">{t.title}</p>
                <p className="text-xs text-red-600 mt-1 font-semibold">
                  ⏰ Terlambat {Math.abs(t.slaRemaining)} jam · PIC: {t.pic || '—'}
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* KPI */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Total Aktif" value={activeTickets.length} color="blue" icon="📋" />
        <KPICard title="Over SLA 🚨" value={overSla.length} color="red" icon="⚠️" />
        <KPICard title="Warning ⚡" value={warning.length} subtitle="< 8 jam tersisa" color="amber" icon="⏱️" />
        <KPICard title="On Track ✅" value={onTrack.length} color="green" icon="✓" />
      </div>

      {/* SLA Rules */}
      <SectionCard title="Aturan SLA Berlaku" className="mb-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          {SLA_RULES.map((rule) => (
            <div key={rule.priority} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center text-sm shrink-0 ${rule.priority === 'critical' ? 'bg-red-100' : rule.priority === 'high' ? 'bg-orange-100' : rule.priority === 'medium' ? 'bg-yellow-100' : 'bg-green-100'}`}
              >
                {rule.priority === 'critical'
                  ? '🔴'
                  : rule.priority === 'high'
                    ? '🟠'
                    : rule.priority === 'medium'
                      ? '🟡'
                      : '🟢'}
              </div>
              <div>
                <p className="text-xs font-semibold text-gray-700 capitalize">{rule.priority}</p>
                <p className="text-lg font-bold text-gray-900">{rule.hours}j</p>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* Filter */}
      <FilterBar>
        <Input
          placeholder="🔍 Cari tiket..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-48"
        />
        <Select
          options={[
            { value: '', label: 'Semua Prioritas' },
            { value: 'critical', label: '🔴 Critical' },
            { value: 'high', label: '🟠 High' },
            { value: 'medium', label: '🟡 Medium' },
            { value: 'low', label: '🟢 Low' },
          ]}
          value={priorityFilter}
          onChange={(e) => setPriorityFilter(e.target.value)}
          className="w-36"
        />
        <Select
          options={[
            { value: '', label: 'Semua Status' },
            { value: 'over_sla', label: '🚨 Over SLA' },
            { value: 'in_progress', label: 'In Progress' },
            { value: 'uat', label: 'UAT' },
            { value: 'internal_testing', label: 'Internal Testing' },
          ]}
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="w-44"
        />
        <span className="ml-auto text-xs text-gray-400">{filtered.length} tiket</span>
      </FilterBar>

      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <Table headers={['ID', 'Judul', 'Prioritas', 'Status', 'PIC', 'SLA Progress', 'Waktu Tersisa', 'Deadline']}>
          {filtered.map((t) => (
            <TR key={t.id} highlight={t.overSla} onClick={() => navigate(`/user/tickets/${t.id}`)}>
              <TD>
                <span className="font-mono text-xs">{t.id}</span>
                {t.overSla && <span className="ml-1 text-red-500 animate-pulse">🚨</span>}
              </TD>
              <TD>
                <p className="text-sm font-medium max-w-[200px] truncate">{t.title}</p>
              </TD>
              <TD>
                <PriorityBadge priority={t.priority} />
              </TD>
              <TD>
                <StatusBadge status={t.status} />
              </TD>
              <TD>
                <span className="text-xs">{t.pic || '—'}</span>
              </TD>
              <TD>
                <div className="w-32">
                  <SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} />
                </div>
              </TD>
              <TD>
                <span
                  className={`text-xs font-semibold ${t.overSla ? 'text-red-600' : t.slaRemaining < 8 ? 'text-amber-600' : 'text-emerald-600'}`}
                >
                  {t.overSla ? `⏰ -${Math.abs(t.slaRemaining)}j` : `${t.slaRemaining}j`}
                </span>
              </TD>
              <TD>
                <span className="text-xs text-gray-400 whitespace-nowrap">{formatDate(t.slaDeadline)}</span>
              </TD>
            </TR>
          ))}
        </Table>
        {filtered.length === 0 && (
          <div className="text-center py-10 text-gray-500 text-sm">Tidak ada tiket yang sesuai filter</div>
        )}
      </div>
    </div>
  )
}
