import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import {
  Button,
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
import { masterDataService, type Division } from '../../services/masterDataService'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import type { Priority, TicketStatus } from '../../types'
import { TicketDescriptionContent } from '../../components/TicketDescriptionContent'

type Action = 'validate' | 'request-revision' | 'reject' | 'transfer'

export default function ValidationQueue() {
  const navigate = useNavigate()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [divisions, setDivisions] = useState<Division[]>([])
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pages, setPages] = useState(1)
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [action, setAction] = useState<Action | null>(null)
  const [notes, setNotes] = useState('')
  const [targetDivision, setTargetDivision] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const load = () => {
    setLoading(true)
    ticketService
      .supervisorQueue({ search, page, per_page: 15 })
      .then((response) => {
        setTickets(response.data)
        setTotal(response.meta.pagination.total)
        setPages(response.meta.pagination.last_page)
        const stillSelected = response.data.find((item) => item.id === selected?.id)
        setSelected(stillSelected || response.data[0] || null)
      })
      .catch(() => setError('Antrean validasi tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }
  useEffect(() => {
    const timeout = window.setTimeout(load, 250)
    return () => window.clearTimeout(timeout)
  }, [search, page])
  useEffect(() => {
    masterDataService
      .getDivisions()
      .then(setDivisions)
      .catch(() => setDivisions([]))
  }, [])
  const choose = (ticket: TicketRecord) => {
    setSelected(ticket)
    ticketService
      .supervisorGet(ticket.id)
      .then(setSelected)
      .catch(() => setError('Detail tiket tidak dapat dimuat.'))
  }

  const confirm = async () => {
    if (!selected || !action) return
    if (action !== 'validate' && !notes.trim()) {
      setError('Alasan wajib diisi untuk tindakan ini.')
      return
    }
    if (action === 'transfer' && !targetDivision) {
      setError('Pilih divisi tujuan.')
      return
    }
    setBusy(true)
    setError('')
    try {
      await ticketService.supervisorAction(selected.id, action, {
        notes: notes || undefined,
        ...(action === 'transfer' && { target_division_id: Number(targetDivision) }),
      })
      setSuccess(
        action === 'validate'
          ? 'Tiket berhasil divalidasi.'
          : action === 'request-revision'
            ? 'Permintaan revisi dikirim.'
            : action === 'reject'
              ? 'Tiket ditolak.'
              : 'Tiket dipindahkan ke antrean divisi tujuan.',
      )
      setAction(null)
      setNotes('')
      setTargetDivision('')
      load()
    } catch (cause) {
      const apiError = cause as ApiRequestError
      setError(apiError.errors ? Object.values(apiError.errors).flat()[0] : apiError.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      {success && <Toast message={success} type="success" onClose={() => setSuccess('')} />}
      <PageHeader
        title="Antrean Validasi"
        subtitle={`${total} tiket menunggu validasi Supervisor`}
        actions={
          <Button variant="ghost" onClick={() => navigate('/supervisor/dashboard')}>
            ← Dashboard
          </Button>
        }
      />
      <div className="mb-4 max-w-sm">
        <Input
          value={search}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(1)
          }}
          placeholder="Cari nomor atau judul tiket..."
        />
      </div>
      {loading ? (
        <div className="py-20 text-center text-sm text-gray-500" role="status">
          Memuat antrean...
        </div>
      ) : tickets.length === 0 ? (
        <div className="py-20 text-center">
          <div className="text-4xl mb-3">✓</div>
          <h3 className="font-semibold text-gray-700">Tidak ada tiket menunggu</h3>
          <p className="text-sm text-gray-500 mt-1">Semua tiket dalam scope divisi Anda telah diproses.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="space-y-3">
            {tickets.map((ticket) => (
              <button
                type="button"
                key={ticket.id}
                onClick={() => choose(ticket)}
                className={`w-full text-left border rounded-xl p-4 transition-all ${selected?.id === ticket.id ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 bg-white hover:shadow-md'}`}
              >
                <div className="flex justify-between gap-2 mb-2">
                  <span className="font-mono text-xs text-gray-500">{ticket.ticket_number}</span>
                  {ticket.requested_priority && <PriorityBadge priority={ticket.requested_priority.key as Priority} />}
                </div>
                <p className="text-sm font-semibold text-gray-900">{ticket.title}</p>
                <p className="text-xs text-gray-500 mt-1">
                  {ticket.requester.name} · {ticket.office?.name || ticket.division?.name || 'Tanpa lokasi'}
                </p>
                <p className="text-xs text-gray-400 mt-2">
                  {ticket.category?.name || 'Belum dikategorikan'} ·{' '}
                  {new Date(ticket.submitted_at || ticket.created_at).toLocaleDateString('id-ID')}
                </p>
              </button>
            ))}
          </div>
          {selected && (
            <div className="lg:col-span-2">
              <SectionCard title={`Detail Tiket — ${selected.ticket_number}`}>
                <div className="flex flex-wrap gap-2 mb-4">
                  <StatusBadge status={selected.status as TicketStatus} />
                  {selected.requested_priority && (
                    <PriorityBadge priority={selected.requested_priority.key as Priority} />
                  )}
                  <span className="text-xs px-3 py-1 bg-gray-100 rounded-full">{selected.category?.name || 'Belum dikategorikan'}</span>
                </div>
                <h2 className="font-bold mb-2">{selected.title}</h2>
                <TicketDescriptionContent html={selected.description} className="mb-4 text-gray-600" />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 text-sm">
                  <Info label="Requester" value={selected.requester.name} />
                  <Info label="Lokasi Pelapor" value={selected.office?.name || selected.division?.name || '—'} />
                  <Info label="Aplikasi" value={selected.application?.name || '—'} />
                  <Info label="Prioritas Usulan" value={selected.requested_priority?.name || '—'} />
                  <Info label="Dampak Bisnis" value={selected.business_impact || '—'} />
                  <Info label="Lampiran" value={`${selected.attachments.length} file`} />
                </div>
                {selected.attachments.length > 0 && (
                  <div className="mt-4 space-y-2">
                    {selected.attachments.map((attachment) => (
                      <button
                        key={attachment.id}
                        className="block text-sm text-blue-700 underline"
                        onClick={() => window.open(ticketService.downloadUrl(selected.id, attachment.id), '_blank')}
                      >
                        {attachment.original_name}
                      </button>
                    ))}
                  </div>
                )}
                <div className="border-t mt-5 pt-4">
                  <p className="text-sm font-semibold text-gray-700 mb-3">Tindakan Validasi</p>
                  <div className="flex flex-wrap gap-2">
                    <Button variant="success" onClick={() => setAction('validate')}>
                      ✓ Validasi
                    </Button>
                    <Button variant="warning" onClick={() => setAction('request-revision')}>
                      Minta Revisi
                    </Button>
                    <Button variant="danger" onClick={() => setAction('reject')}>
                      Tolak
                    </Button>
                    <Button variant="secondary" onClick={() => setAction('transfer')}>
                      Transfer
                    </Button>
                  </div>
                </div>
              </SectionCard>
            </div>
          )}
        </div>
      )}
      {pages > 1 && (
        <div className="flex justify-between items-center mt-4">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
            ← Sebelumnya
          </Button>
          <span className="text-xs text-gray-500">
            Halaman {page} dari {pages}
          </span>
          <Button variant="secondary" disabled={page >= pages} onClick={() => setPage((value) => value + 1)}>
            Berikutnya →
          </Button>
        </div>
      )}
      <Modal
        open={action !== null}
        onClose={() => setAction(null)}
        title={
          action === 'validate'
            ? 'Validasi Tiket'
            : action === 'request-revision'
              ? 'Minta Revisi'
              : action === 'reject'
                ? 'Tolak Tiket'
                : 'Transfer Tiket'
        }
      >
        <div className="space-y-4">
          {action === 'transfer' && (
            <Select
              label="Divisi Tujuan *"
              value={targetDivision}
              onChange={(event) => setTargetDivision(event.target.value)}
              options={[
                { value: '', label: '— Pilih divisi —' },
                ...divisions
                  .filter((division) => division.id !== selected?.current_division?.id)
                  .map((division) => ({ value: String(division.id), label: `${division.code} — ${division.name}` })),
              ]}
            />
          )}
          <Textarea
            label={action === 'validate' ? 'Catatan (opsional)' : 'Alasan *'}
            rows={4}
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAction(null)}>
              Batal
            </Button>
            <Button
              variant={action === 'validate' ? 'success' : action === 'reject' ? 'danger' : 'warning'}
              disabled={busy || (action !== 'validate' && !notes.trim())}
              onClick={() => void confirm()}
            >
              {busy ? 'Memproses...' : 'Konfirmasi'}
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
      <p className="font-medium text-gray-900 whitespace-pre-wrap">{value}</p>
    </div>
  )
}
