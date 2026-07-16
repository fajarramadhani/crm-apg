import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import {
  EmptyState,
  FilterBar,
  PageHeader,
  PriorityBadge,
  SectionCard,
  Select,
  StatusBadge,
  Toast,
} from '../../components/ui'
import { ticketService, type DevelopmentDetail, type TicketRecord } from '../../services/ticketService'

export default function DevelopmentMonitoring() {
  const [tickets, setTickets] = useState<TicketRecord[]>([]),
    [detail, setDetail] = useState<DevelopmentDetail | null>(null),
    [status, setStatus] = useState(''),
    [search, setSearch] = useState(''),
    [loading, setLoading] = useState(true),
    [error, setError] = useState('')
  const load = () => {
    setLoading(true)
    ticketService
      .developmentQueue({ search, status, per_page: 20 })
      .then((r) => setTickets(r.data))
      .catch((c) => setError((c as ApiRequestError).message))
      .finally(() => setLoading(false))
  }
  useEffect(load, [status, search])
  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      <PageHeader
        title="Development Monitoring"
        subtitle="Monitoring read-only progress, effort, blocker, evidence, dan internal testing"
      />
      <FilterBar>
        <input
          aria-label="Cari tiket"
          className="min-w-56 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm"
          placeholder="Cari nomor atau judul..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          options={[
            { value: '', label: 'Semua status' },
            { value: 'ready_for_development', label: 'Ready for Development' },
            { value: 'development_in_progress', label: 'Development In Progress' },
            { value: 'internal_testing', label: 'Internal Testing' },
            { value: 'ready_for_qa', label: 'Ready for QA' },
          ]}
        />
      </FilterBar>
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat development queue...</p>
      ) : tickets.length === 0 ? (
        <EmptyState title="Queue kosong" message="Belum ada tiket dalam tahap development." />
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white">
          <table className="min-w-[1000px] w-full text-sm">
            <thead className="bg-gray-50 text-left text-xs text-gray-500">
              <tr>
                {[
                  'Ticket',
                  'PIC / Aplikasi',
                  'Priority',
                  'Status',
                  'Progress',
                  'Estimate / Actual',
                  'SLA',
                  'Blocker',
                ].map((h) => (
                  <th key={h} className="px-4 py-3">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {tickets.map((t) => (
                <tr
                  key={t.id}
                  className="border-t hover:bg-blue-50 cursor-pointer"
                  onClick={() =>
                    void ticketService
                      .developmentDetail(t.id)
                      .then(setDetail)
                      .catch((c) => setError((c as ApiRequestError).message))
                  }
                >
                  <td className="px-4 py-3">
                    <b className="font-mono text-xs">{t.ticket_number}</b>
                    <p>{t.title}</p>
                  </td>
                  <td className="px-4 py-3">
                    {t.assignee?.name || '—'}
                    <p className="text-xs text-gray-500">{t.application?.name || '—'}</p>
                  </td>
                  <td className="px-4 py-3">{t.final_priority && <PriorityBadge priority={t.final_priority.key} />}</td>
                  <td className="px-4 py-3">
                    <StatusBadge status={t.status} />
                  </td>
                  <td className="px-4 py-3">
                    <b>{t.progress_percentage}%</b>
                    <div className="h-1.5 w-24 bg-gray-200 rounded-full mt-1">
                      <div className="h-full bg-blue-700 rounded-full" style={{ width: `${t.progress_percentage}%` }} />
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    {t.solution_plan_preview?.estimated_effort_minutes || 0} / {t.actual_work_minutes || 0} min
                  </td>
                  <td className="px-4 py-3 whitespace-nowrap">
                    {t.resolution_due_at ? new Date(t.resolution_due_at).toLocaleString('id-ID') : '—'}
                  </td>
                  <td className="px-4 py-3 max-w-52 text-amber-700">
                    {t.latest_development_update?.blockers.join(', ') || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {detail && (
        <div className="fixed inset-0 z-50 bg-black/30 p-3 sm:p-8 overflow-y-auto" onClick={() => setDetail(null)}>
          <div
            className="max-w-5xl mx-auto bg-[#F8FAFC] rounded-2xl p-5 space-y-4"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex justify-between">
              <div>
                <b>{detail.ticket.ticket_number}</b>
                <h2 className="text-lg font-bold">{detail.ticket.title}</h2>
              </div>
              <button onClick={() => setDetail(null)} aria-label="Tutup">
                ✕
              </button>
            </div>
            <SectionCard title="Approved Plan">
              <p className="text-sm whitespace-pre-wrap">{detail.solution_plan?.solution_summary || '—'}</p>
              <p className="text-xs text-gray-500 mt-2">
                Estimate: {detail.solution_plan?.estimated_effort_minutes || 0} menit
              </p>
            </SectionCard>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <SectionCard title="Progress Updates">
                {detail.updates.map((u) => (
                  <div key={u.id} className="border-b py-2 text-sm">
                    <b>{u.progress_percentage}%</b> · {u.summary}
                    {u.blockers.length > 0 && <p className="text-amber-700">Blocker: {u.blockers.join(', ')}</p>}
                  </div>
                ))}
              </SectionCard>
              <SectionCard title="Worklogs">
                <b className="text-sm">Total {detail.worklogs.reduce((n, w) => n + w.minutes_spent, 0)} menit</b>
                {detail.worklogs.map((w) => (
                  <div key={w.id} className="border-b py-2 text-sm">
                    {w.work_date} · {w.minutes_spent} min · {w.description}
                  </div>
                ))}
              </SectionCard>
              <SectionCard title="Test Cases">
                {detail.test_cases.map((c) => (
                  <div key={c.id} className="border-b py-2 text-sm">
                    <b>{c.case_number}</b> · {c.title}
                  </div>
                ))}
              </SectionCard>
              <SectionCard title="Test Runs">
                {detail.test_runs.map((r) => (
                  <div key={r.id} className="border-b py-2 text-sm">
                    <b>
                      Run #{r.run_number} · {r.status}
                    </b>
                    <p className="text-gray-500">
                      {r.environment}
                      {r.build_reference ? ` · ${r.build_reference}` : ''}
                    </p>
                    <p>
                      {r.results.filter((x) => x.status === 'passed').length} passed ·{' '}
                      {r.results.filter((x) => ['failed', 'blocked'].includes(x.status)).length} failed/blocked
                    </p>
                  </div>
                ))}
              </SectionCard>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
