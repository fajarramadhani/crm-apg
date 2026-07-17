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
import {
  ticketService,
  type DevelopmentDetail,
  type TicketRecord,
  type QaUserWorkload,
} from '../../services/ticketService'

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
            { value: 'qa_assignment', label: 'QA Assignment' },
            { value: 'qa_in_progress', label: 'QA In Progress' },
            { value: 'qa_failed', label: 'QA Failed' },
            { value: 'qa_retest', label: 'QA Retest' },
            { value: 'ready_for_uat', label: 'Ready for UAT' },
            { value: 'uat_assignment', label: 'UAT Assignment' },
            { value: 'uat_in_progress', label: 'UAT In Progress' },
            { value: 'uat_failed', label: 'UAT Failed' },
            { value: 'uat_retest', label: 'UAT Retest' },
            { value: 'uat_approved', label: 'UAT Approved' },
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
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <SectionCard title="Approved Plan">
                <p className="text-sm whitespace-pre-wrap">{detail.solution_plan?.solution_summary || '—'}</p>
                <p className="text-xs text-gray-500 mt-2">
                  Estimate: {detail.solution_plan?.estimated_effort_minutes || 0} menit
                </p>
              </SectionCard>

              {detail.ticket.status === 'ready_for_qa' && (
                <QaAssignmentForm
                  ticketId={detail.ticket.id}
                  onAssigned={() => {
                    setDetail(null)
                    load()
                  }}
                />
              )}

              {detail.ticket.qa_assignee && (
                <SectionCard title="Informasi QA" className="bg-[#EEF2F6] border border-blue-100">
                  <div className="grid grid-cols-2 gap-2 text-sm font-medium">
                    <div>
                      <p className="text-xs text-gray-400">QA Assignee</p>
                      <p className="font-semibold text-gray-800">{detail.ticket.qa_assignee.name}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-400">QA Cycle & Run</p>
                      <p className="font-semibold text-gray-800">
                        Cycle {detail.ticket.qa_cycle_number || 1} · Run #{detail.ticket.qa_run_number || 0}
                      </p>
                    </div>
                    {detail.ticket.latest_qa_result && (
                      <div className="col-span-2 mt-1">
                        <p className="text-xs text-gray-400 font-semibold">Hasil QA Terakhir</p>
                        <span
                          className={`inline-block px-2 py-0.5 text-xs font-bold rounded ${detail.ticket.latest_qa_result === 'passed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}
                        >
                          {detail.ticket.latest_qa_result.toUpperCase()}
                        </span>
                      </div>
                    )}
                  </div>
                </SectionCard>
              )}
            </div>

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
                <b className="text-sm font-semibold">
                  Total {detail.worklogs.reduce((n, w) => n + w.minutes_spent, 0)} menit
                </b>
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

function QaAssignmentForm({ ticketId, onAssigned }: { ticketId: number; onAssigned: () => void }) {
  const [qas, setQas] = useState<QaUserWorkload[]>([])
  const [selectedQa, setSelectedQa] = useState<number | ''>('')
  const [notes, setNotes] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    ticketService
      .qaWorkloads()
      .then(setQas)
      .catch((err) => setError(err.message || 'Gagal memuat daftar QA'))
  }, [])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedQa) return
    setLoading(true)
    ticketService
      .assignQa(ticketId, { qa_user_id: Number(selectedQa), notes })
      .then(() => onAssigned())
      .catch((err) => setError(err.message || 'Gagal menugaskan QA'))
      .finally(() => setLoading(false))
  }

  return (
    <SectionCard title="Penugasan QA (IT Lead Only)" className="bg-indigo-50/50 border border-indigo-100">
      {error && <p className="text-xs text-red-600 mb-2 font-medium">{error}</p>}
      <form onSubmit={handleSubmit} className="space-y-3">
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Pilih QA Member</label>
          <select
            value={selectedQa}
            onChange={(e) => setSelectedQa(e.target.value === '' ? '' : Number(e.target.value))}
            className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-2"
            required
            disabled={loading}
          >
            <option value="">-- Pilih Anggota QA --</option>
            {qas.map((qa) => (
              <option key={qa.id} value={qa.id}>
                {qa.name} (Beban: {qa.active_tickets_count} Tiket Aktif)
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Catatan Instruksi QA (Opsional)</label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-1.5"
            rows={2}
            placeholder="Tambahkan arahan pengujian khusus..."
            disabled={loading}
          />
        </div>
        <div className="flex justify-end">
          <button
            type="submit"
            className="px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
            disabled={!selectedQa || loading}
          >
            {loading ? 'Menyimpan...' : 'Tugaskan QA'}
          </button>
        </div>
      </form>
    </SectionCard>
  )
}
