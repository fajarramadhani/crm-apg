import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  EmptyState,
  Input,
  Modal,
  PageHeader,
  PriorityBadge,
  SectionCard,
  StatusBadge,
  Textarea,
  Toast,
} from '../../components/ui'
import { ticketService, type PlanReviewDetail, type TicketRecord } from '../../services/ticketService'

export default function PlanReview() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [detail, setDetail] = useState<PlanReviewDetail | null>(null)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pages, setPages] = useState(1)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [action, setAction] = useState<'approve' | 'request-revision' | null>(null)
  const [notes, setNotes] = useState('')

  const load = async (nextPage = page) => {
    setLoading(true)
    try {
      const response = await ticketService.planReviewQueue({ search, page: nextPage, per_page: 10 })
      setTickets(response.data)
      setPages(response.meta.pagination.last_page)
      setPage(nextPage)
      if (detail && !response.data.some((item) => item.id === detail.ticket.id)) setDetail(null)
      if (!detail && response.data[0]) setDetail(await ticketService.planReviewDetail(response.data[0].id))
    } catch {
      setError('Antrean plan review tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => {
    void load(1)
  }, [])
  const open = async (id: number) => {
    setBusy(true)
    setError('')
    try {
      setDetail(await ticketService.planReviewDetail(id))
    } catch {
      setError('Detail plan review tidak dapat dibuka.')
    } finally {
      setBusy(false)
    }
  }
  const review = async () => {
    if (!detail || !action) return
    setBusy(true)
    setError('')
    try {
      await ticketService.reviewPlan(detail.ticket.id, detail.solution_plan.id, action, notes || undefined)
      setAction(null)
      setNotes('')
      setDetail(null)
      setSuccess(action === 'approve' ? 'Solution plan disetujui.' : 'Permintaan revisi dikirim kepada PIC.')
      await load(1)
    } catch (cause) {
      const api = cause as ApiRequestError
      setError(api.errors ? Object.values(api.errors).flat()[0] : api.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      {success && <Toast message={success} onClose={() => setSuccess('')} />}
      <PageHeader title="Plan Review Queue" subtitle="Tinjau RCA dan solution plan yang disubmit PIC" />
      <div className="flex gap-2 mb-4">
        <Input
          aria-label="Cari plan review"
          placeholder="Cari nomor atau judul tiket..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && void load(1)}
        />
        <Button onClick={() => void load(1)}>Cari</Button>
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <SectionCard title={`Menunggu Review (${tickets.length})`} className="lg:col-span-1">
          {loading ? (
            <p className="py-10 text-center text-sm text-gray-500">Memuat antrean...</p>
          ) : tickets.length === 0 ? (
            <EmptyState title="Antrean kosong" message="Tidak ada solution plan yang menunggu review." />
          ) : (
            <div className="space-y-2">
              {tickets.map((ticket) => (
                <button
                  key={ticket.id}
                  onClick={() => void open(ticket.id)}
                  className={`w-full rounded-xl border p-3 text-left ${detail?.ticket.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'}`}
                >
                  <div className="flex justify-between gap-2">
                    <span className="font-mono text-xs text-gray-500">{ticket.ticket_number}</span>
                    <StatusBadge status={ticket.status} />
                  </div>
                  <p className="text-sm font-semibold mt-2">{ticket.title}</p>
                  <p className="text-xs text-gray-500 mt-1">
                    {ticket.requester.name} · {ticket.application?.name || 'Tanpa aplikasi'}
                  </p>
                  <div className="flex gap-2 mt-2 flex-wrap">
                    {ticket.final_priority && <PriorityBadge priority={ticket.final_priority.key} />}
                    <span className="text-xs rounded-full bg-gray-100 px-2 py-1">{ticket.assignee?.name}</span>
                    {ticket.solution_plan_preview && (
                      <>
                        <span className="text-xs rounded-full bg-purple-50 text-purple-700 px-2 py-1">
                          {effort(ticket.solution_plan_preview.estimated_effort_minutes)}
                        </span>
                        <span className="text-xs rounded-full bg-amber-50 text-amber-700 px-2 py-1">
                          Risk {ticket.solution_plan_preview.risk_level}
                        </span>
                      </>
                    )}
                  </div>
                  <p className="text-xs text-gray-500 mt-2">
                    Submit:{' '}
                    {ticket.plan_submitted_at ? new Date(ticket.plan_submitted_at).toLocaleString('id-ID') : '—'}
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    SLA:{' '}
                    {ticket.resolution_due_at
                      ? new Date(ticket.resolution_due_at).toLocaleString('id-ID')
                      : 'Belum ditentukan'}
                  </p>
                </button>
              ))}
            </div>
          )}
          <div className="flex justify-between mt-4">
            <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => void load(page - 1)}>
              Sebelumnya
            </Button>
            <span className="text-xs text-gray-500 self-center">
              {page} / {pages}
            </span>
            <Button size="sm" variant="secondary" disabled={page >= pages} onClick={() => void load(page + 1)}>
              Berikutnya
            </Button>
          </div>
        </SectionCard>
        <div className="lg:col-span-2 space-y-5">
          {busy && !detail ? (
            <p className="py-16 text-center text-sm text-gray-500">Memuat detail...</p>
          ) : !detail ? (
            <SectionCard>
              <EmptyState
                title="Pilih solution plan"
                message="Pilih tiket dari antrean untuk melihat RCA dan rencana lengkap."
              />
            </SectionCard>
          ) : (
            <>
              <SectionCard title={`${detail.ticket.ticket_number} · Informasi Tiket`}>
                <div className="flex flex-wrap gap-2 mb-3">
                  <StatusBadge status={detail.ticket.status} />
                  {detail.ticket.final_priority && <PriorityBadge priority={detail.ticket.final_priority.key} />}
                </div>
                <h2 className="text-lg font-bold">{detail.ticket.title}</h2>
                <p className="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{detail.ticket.description}</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 rounded-lg p-4 mt-4">
                  <Info label="Requester" value={detail.ticket.requester.name} />
                  <Info label="PIC" value={detail.ticket.assignee?.name || '—'} />
                  <Info label="Aplikasi" value={detail.ticket.application?.name || '—'} />
                  <Info
                    label="SLA Deadline"
                    value={
                      detail.ticket.resolution_due_at
                        ? new Date(detail.ticket.resolution_due_at).toLocaleString('id-ID')
                        : '—'
                    }
                  />
                </div>
              </SectionCard>
              <SectionCard title={`Analysis & RCA · v${detail.analysis.version}`}>
                <Detail label="Problem Summary" value={detail.analysis.problem_summary} />
                <Detail label="Root Cause" value={detail.analysis.root_cause} />
                <Detail label="Technical Impact" value={detail.analysis.technical_impact} />
                <Detail label="Business Impact" value={detail.analysis.business_impact} />
                <Detail label="Evidence" value={detail.analysis.evidence} />
              </SectionCard>
              <SectionCard title={`Solution Plan · v${detail.solution_plan.version}`}>
                <Detail label="Solution Summary" value={detail.solution_plan.solution_summary} />
                <p className="text-xs text-gray-500 mb-2">Implementation Steps</p>
                <ol className="list-decimal pl-5 space-y-2 text-sm mb-4">
                  {detail.solution_plan.implementation_steps.map((step) => (
                    <li key={step.order}>{step.description}</li>
                  ))}
                </ol>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Info label="Dependencies" value={detail.solution_plan.dependencies.join(', ') || '—'} />
                  <Info label="Effort" value={effort(detail.solution_plan.estimated_effort_minutes)} />
                  <Info label="Risk" value={detail.solution_plan.risk_level.toUpperCase()} />
                  <Info
                    label="Affected Components"
                    value={detail.solution_plan.affected_components.join(', ') || '—'}
                  />
                </div>
                <div className="mt-4">
                  <Detail label="Risk Description" value={detail.solution_plan.risk_description} />
                  <Detail label="Rollback Plan" value={detail.solution_plan.rollback_plan} />
                  <Detail label="Testing Plan" value={detail.solution_plan.testing_plan} />
                  <Detail label="Deployment Consideration" value={detail.solution_plan.deployment_consideration} />
                </div>
                <div className="flex justify-end gap-2 mt-5">
                  <Button variant="warning" disabled={busy} onClick={() => setAction('request-revision')}>
                    Request Revision
                  </Button>
                  <Button variant="success" disabled={busy} onClick={() => setAction('approve')}>
                    Approve
                  </Button>
                </div>
              </SectionCard>
              <SectionCard title="Lampiran & History">
                <p className="text-xs text-gray-500 mb-2">Lampiran</p>
                {detail.ticket.attachments.length ? (
                  detail.ticket.attachments.map((item) => (
                    <p key={item.id} className="text-sm">
                      {item.original_name} · {Math.ceil(item.size / 1024)} KB
                    </p>
                  ))
                ) : (
                  <p className="text-sm text-gray-500">Tidak ada lampiran.</p>
                )}
                <div className="border-t mt-4 pt-4 space-y-3">
                  {detail.ticket.history.map((item) => (
                    <div key={item.id} className="border-l-2 border-blue-200 pl-3">
                      <p className="text-sm font-medium capitalize">{item.action.replace(/_/g, ' ')}</p>
                      <p className="text-xs text-gray-500">
                        {item.actor?.name} · {new Date(item.created_at).toLocaleString('id-ID')}
                      </p>
                    </div>
                  ))}
                </div>
              </SectionCard>
            </>
          )}
        </div>
      </div>
      <Modal
        open={action !== null}
        onClose={() => !busy && setAction(null)}
        title={action === 'approve' ? 'Approve Solution Plan' : 'Request Revision'}
      >
        {action === 'request-revision' && (
          <Textarea
            label="Alasan revisi *"
            rows={4}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Jelaskan perubahan yang diperlukan..."
          />
        )}
        <p className="text-sm text-gray-600 mt-3">
          {action === 'approve'
            ? 'Tiket akan menjadi Ready for Development.'
            : 'Tiket akan kembali ke Solution Planning dan PIC harus membuat versi baru.'}
        </p>
        <div className="flex justify-end gap-2 mt-5">
          <Button variant="secondary" disabled={busy} onClick={() => setAction(null)}>
            Batal
          </Button>
          <Button
            loading={busy}
            disabled={action === 'request-revision' && !notes.trim()}
            variant={action === 'approve' ? 'success' : 'warning'}
            onClick={() => void review()}
          >
            Konfirmasi
          </Button>
        </div>
      </Modal>
    </div>
  )
}

const effort = (minutes: number) => `${(minutes / 60).toFixed(1)} jam (${(minutes / 480).toFixed(1)} hari kerja)`
function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="text-sm font-medium">{value}</p>
    </div>
  )
}
function Detail({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="mb-4">
      <p className="text-xs text-gray-500 mb-1">{label}</p>
      <p className="text-sm whitespace-pre-wrap">{value || '—'}</p>
    </div>
  )
}
