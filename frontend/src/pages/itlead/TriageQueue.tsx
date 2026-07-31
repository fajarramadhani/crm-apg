import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  EmptyState,
  Input,
  Modal,
  PageHeader,
  PriorityBadge,
  SectionCard,
  Select,
  StatusBadge,
  Textarea,
  Toast,
} from '../../components/ui'
import { masterDataService, type SlaPolicy, type TicketPriority } from '../../services/masterDataService'
import { ticketService, type PicOption, type TicketRecord } from '../../services/ticketService'
import { TicketDescriptionContent } from '../../components/TicketDescriptionContent'

export default function TriageQueue() {
  const navigate = useNavigate()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [priorities, setPriorities] = useState<TicketPriority[]>([])
  const [policies, setPolicies] = useState<SlaPolicy[]>([])
  const [pics, setPics] = useState<PicOption[]>([])
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showAssign, setShowAssign] = useState(false)
  const [priorityId, setPriorityId] = useState('')
  const [picId, setPicId] = useState('')
  const [notes, setNotes] = useState('')

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      const response = await ticketService.triageQueue({ search, status, page, per_page: 10 })
      setTickets(response.data)
      setLastPage(response.meta.pagination.last_page)
      if (selected) setSelected(response.data.find((item) => item.id === selected.id) ?? null)
    } catch {
      setError('Antrean triage tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => {
    void load()
  }, [page, search, status])
  useEffect(() => {
    Promise.all([
      masterDataService.getTicketPriorities(),
      masterDataService.getSlaPolicies(),
      ticketService.picOptions(),
    ])
      .then(([p, s, users]) => {
        setPriorities(p)
        setPolicies(s)
        setPics(users)
      })
      .catch(() => setError('Opsi penugasan tidak dapat dimuat.'))
  }, [])

  const detail = async (ticket: TicketRecord) => {
    setBusy(true)
    try {
      setSelected(await ticketService.itLeadGet(ticket.id))
    } catch {
      setError('Detail triage tidak dapat dimuat.')
    } finally {
      setBusy(false)
    }
  }
  const start = async () => {
    if (!selected) return
    setBusy(true)
    try {
      setSelected(await ticketService.startTriage(selected.id))
      setSuccess('Triage dimulai.')
      await load()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }
  const assign = async () => {
    if (!selected || !priorityId || !picId) return
    setBusy(true)
    try {
      await ticketService.assign(selected.id, {
        final_priority_id: Number(priorityId),
        pic_user_id: Number(picId),
        notes: notes || undefined,
      })
      setSuccess('Tiket berhasil ditugaskan ke PIC.')
      setShowAssign(false)
      setSelected(null)
      setNotes('')
      await load()
    } catch (cause) {
      const e = cause as ApiRequestError
      setError(e.errors ? Object.values(e.errors).flat()[0] : e.message)
    } finally {
      setBusy(false)
    }
  }
  const policy = useMemo(() => policies.find((item) => item.priority_id === Number(priorityId)), [policies, priorityId])
  const chosenPic = pics.find((item) => item.id === Number(picId))

  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      {success && <Toast message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Antrean Triage"
        subtitle="Tiket tervalidasi yang menunggu triage dan penugasan"
        actions={
          <Button variant="ghost" onClick={() => navigate('/itlead/dashboard')}>
            ← Dashboard
          </Button>
        }
      />
      <div className="flex flex-col sm:flex-row gap-3 mb-4">
        <Input
          aria-label="Cari tiket"
          placeholder="Cari nomor atau judul..."
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
        />
        <Select
          aria-label="Filter status"
          value={status}
          onChange={(e) => {
            setStatus(e.target.value)
            setPage(1)
          }}
          options={[
            { value: '', label: 'Semua status' },
            { value: 'validated', label: 'Validated' },
            { value: 'triage', label: 'Triage' },
          ]}
        />
      </div>
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500" role="status">
          Memuat antrean...
        </p>
      ) : tickets.length === 0 ? (
        <EmptyState title="Antrean triage kosong" message="Tidak ada tiket Validated atau Triage pada filter ini." />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div className="space-y-3">
            {tickets.map((ticket) => (
              <button
                key={ticket.id}
                onClick={() => void detail(ticket)}
                className={`w-full text-left border rounded-xl p-4 bg-white hover:shadow-md ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'border-gray-200'}`}
              >
                <div className="flex justify-between gap-2">
                  <span className="font-mono text-xs text-gray-500">{ticket.ticket_number}</span>
                  <StatusBadge status={ticket.status} />
                </div>
                <p className="font-semibold text-sm mt-2">{ticket.title}</p>
                <p className="text-xs text-gray-500 mt-1">
                  {ticket.requester.name} · {ticket.application?.name || 'Tanpa aplikasi'}
                </p>
                {ticket.requested_priority && (
                  <div className="mt-2">
                    <PriorityBadge priority={ticket.requested_priority.key} />
                  </div>
                )}
              </button>
            ))}
          </div>
          <div className="lg:col-span-2">
            {selected ? (
              <SectionCard title={`Detail — ${selected.ticket_number}`}>
                <div className="flex gap-2 mb-4">
                  <StatusBadge status={selected.status} />
                  {selected.requested_priority && <PriorityBadge priority={selected.requested_priority.key} />}
                </div>
                <h2 className="font-bold">{selected.title}</h2>
                <TicketDescriptionContent html={selected.description} className="mt-2 text-gray-600" />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 mt-4 text-sm">
                  <Info label="Requester" value={selected.requester.name} />
                  <Info label="Kategori" value={selected.category?.name || 'Belum ditentukan'} />
                  <Info
                    label="Aplikasi / Modul"
                    value={`${selected.application?.name || '—'} / ${selected.application_module?.name || '—'}`}
                  />
                  <Info label="Urgency" value={selected.urgency || '—'} />
                  <Info label="Dampak bisnis" value={selected.business_impact || '—'} />
                  <Info
                    label="Divalidasi"
                    value={selected.validated_at ? new Date(selected.validated_at).toLocaleString('id-ID') : '—'}
                  />
                </div>
                <div className="mt-5 flex justify-end">
                  {selected.status === 'validated' ? (
                    <Button loading={busy} onClick={() => void start()}>
                      Mulai Triage
                    </Button>
                  ) : (
                    <Button onClick={() => setShowAssign(true)}>Tetapkan Priority, SLA & PIC</Button>
                  )}
                </div>
              </SectionCard>
            ) : (
              <EmptyState title="Pilih tiket" message="Pilih tiket di sebelah untuk melihat detail triage." />
            )}
          </div>
        </div>
      )}
      <div className="flex justify-center gap-2 mt-5">
        <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>
          Sebelumnya
        </Button>
        <span className="px-3 py-2 text-sm">
          {page} / {lastPage}
        </span>
        <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage(page + 1)}>
          Berikutnya
        </Button>
      </div>
      <Modal open={showAssign} onClose={() => setShowAssign(false)} title="Assign PIC dan inisialisasi SLA" size="lg">
        <div className="space-y-4">
          <Select
            label="Final priority *"
            value={priorityId}
            onChange={(e) => setPriorityId(e.target.value)}
            options={[
              { value: '', label: 'Pilih final priority' },
              ...priorities.map((p) => ({ value: String(p.id), label: p.name })),
            ]}
          />
          <Select
            label="PIC aktif *"
            value={picId}
            onChange={(e) => setPicId(e.target.value)}
            options={[
              { value: '', label: 'Pilih PIC' },
              ...pics.map((p) => ({
                value: String(p.id),
                label: `${p.name} — ${p.active_assignment_count} tiket (${p.workload_indicator})`,
              })),
            ]}
          />
          {chosenPic && (
            <div className="text-xs bg-blue-50 border border-blue-200 rounded-lg p-3">
              Workload: {chosenPic.active_assignment_count} aktif · {chosenPic.critical_count} critical ·{' '}
              {chosenPic.high_count} high
            </div>
          )}
          {policy && (
            <div className="text-xs bg-amber-50 border border-amber-200 rounded-lg p-3">
              SLA backend: response {policy.response_minutes ?? 'belum ditetapkan'} menit, resolution{' '}
              {policy.resolution_minutes} menit kerja. Deadline final dihitung backend saat submit.
            </div>
          )}
          <Textarea
            label="Catatan internal"
            rows={3}
            maxLength={2000}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowAssign(false)}>
              Batal
            </Button>
            <Button loading={busy} disabled={!priorityId || !picId} onClick={() => void assign()}>
              Konfirmasi Penugasan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="font-medium whitespace-pre-wrap">{value}</p>
    </div>
  )
}
