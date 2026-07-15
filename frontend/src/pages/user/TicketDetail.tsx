import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  Input,
  Modal,
  PageHeader,
  PriorityBadge,
  SectionCard,
  StatusBadge,
  Textarea,
  Toast,
} from '../../components/ui'
import { ticketService, type TicketPayload, type TicketRecord } from '../../services/ticketService'
import type { Priority, TicketStatus } from '../../types'

export default function TicketDetail() {
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const [ticket, setTicket] = useState<TicketRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [editing, setEditing] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [businessImpact, setBusinessImpact] = useState('')
  const [action, setAction] = useState<'resubmit' | 'cancel' | null>(null)
  const [notes, setNotes] = useState('')
  const [busy, setBusy] = useState(false)

  const load = () => {
    setLoading(true)
    setError('')
    ticketService
      .get(id)
      .then((result) => {
        setTicket(result)
        setTitle(result.title)
        setDescription(result.description)
        setBusinessImpact(result.business_impact || '')
      })
      .catch(() => setError('Detail tiket tidak dapat dimuat atau Anda tidak memiliki akses.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [id])

  const payload = (): TicketPayload => ({
    ticket_category_id: ticket!.category.id,
    ...(ticket!.application && { application_id: ticket!.application.id }),
    ...(ticket!.application_module && { application_module_id: ticket!.application_module.id }),
    ...(ticket!.requested_priority && { requested_priority_id: ticket!.requested_priority.id }),
    title,
    description,
    business_impact: businessImpact || undefined,
    urgency: ticket!.urgency || undefined,
    expected_result: ticket!.expected_result || undefined,
    actual_result: ticket!.actual_result || undefined,
    reproduction_steps: ticket!.reproduction_steps || undefined,
    request_purpose: ticket!.request_purpose || undefined,
    change_reason: ticket!.change_reason || undefined,
    expected_impact: ticket!.expected_impact || undefined,
    recurring_indication: ticket!.recurring_indication || undefined,
  })
  const save = async () => {
    setBusy(true)
    setError('')
    try {
      const result = await ticketService.update(id, payload())
      setTicket(result)
      setEditing(false)
    } catch (cause) {
      const apiError = cause as ApiRequestError
      setError(apiError.errors ? Object.values(apiError.errors).flat()[0] : apiError.message)
    } finally {
      setBusy(false)
    }
  }
  const confirmAction = async () => {
    setBusy(true)
    setError('')
    try {
      if (action === 'resubmit') {
        if (editing) await ticketService.update(id, payload())
        await ticketService.resubmit(id, notes || undefined)
      } else await ticketService.cancel(id, notes || undefined)
      setAction(null)
      setNotes('')
      load()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
      setBusy(false)
    }
  }
  const upload = async (file?: File) => {
    if (!file) return
    setBusy(true)
    try {
      await ticketService.upload(id, file, file.type.startsWith('image/') ? 'screenshot' : 'evidence')
      load()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
      setBusy(false)
    }
  }

  if (loading)
    return (
      <div className="py-20 text-center text-sm text-gray-500" role="status">
        Memuat detail tiket...
      </div>
    )
  if (!ticket)
    return (
      <div className="py-20 text-center">
        <p className="text-red-700">{error}</p>
        <Button variant="secondary" onClick={() => navigate('/user/tickets')}>
          Kembali
        </Button>
      </div>
    )
  const revision = ticket.comments.filter((comment) => comment.type === 'revision_request').at(-1)
  const rejection = ticket.comments.filter((comment) => comment.type === 'rejection_reason').at(-1)

  return (
    <div className="max-w-5xl">
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      <PageHeader
        title={ticket.ticket_number}
        subtitle={ticket.title}
        actions={
          <div className="flex gap-2 flex-wrap">
            <Button variant="ghost" onClick={() => navigate('/user/tickets')}>
              ← Riwayat
            </Button>
            {ticket.allowed_actions.includes('update') && (
              <Button variant="secondary" onClick={() => setEditing((value) => !value)}>
                {editing ? 'Batal Edit' : 'Edit Tiket'}
              </Button>
            )}
            {ticket.allowed_actions.includes('cancel') && (
              <Button variant="danger" onClick={() => setAction('cancel')}>
                Batalkan
              </Button>
            )}
          </div>
        }
      />
      {(revision || rejection) && (
        <div
          className={`rounded-xl border p-4 mb-5 ${revision ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-red-50 border-red-200 text-red-900'}`}
        >
          <p className="font-semibold text-sm">{revision ? 'Revisi diminta oleh Supervisor' : 'Alasan penolakan'}</p>
          <p className="text-sm mt-1 whitespace-pre-wrap">{(revision || rejection)?.comment}</p>
        </div>
      )}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="lg:col-span-2 space-y-5">
          <SectionCard title="Informasi Tiket">
            <div className="flex gap-2 flex-wrap mb-5">
              <StatusBadge status={ticket.status as TicketStatus} />
              {ticket.requested_priority && <PriorityBadge priority={ticket.requested_priority.key as Priority} />}
              <span className="text-xs rounded-full bg-gray-100 px-3 py-1">{ticket.category.name}</span>
            </div>
            {editing ? (
              <div className="space-y-4">
                <Input label="Judul *" value={title} onChange={(event) => setTitle(event.target.value)} />
                <Textarea
                  label="Deskripsi *"
                  rows={6}
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                />
                <Textarea
                  label="Dampak Bisnis"
                  rows={3}
                  value={businessImpact}
                  onChange={(event) => setBusinessImpact(event.target.value)}
                />
                <div className="flex justify-end">
                  <Button
                    variant="primary"
                    disabled={busy || !title.trim() || !description.trim()}
                    onClick={() => void save()}
                  >
                    {busy ? 'Menyimpan...' : 'Simpan Perubahan'}
                  </Button>
                </div>
              </div>
            ) : (
              <div className="space-y-4 text-sm">
                <div>
                  <p className="text-xs text-gray-500 mb-1">Deskripsi</p>
                  <p className="whitespace-pre-wrap leading-relaxed">{ticket.description}</p>
                </div>
                {ticket.business_impact && (
                  <div>
                    <p className="text-xs text-gray-500 mb-1">Dampak Bisnis</p>
                    <p className="whitespace-pre-wrap">{ticket.business_impact}</p>
                  </div>
                )}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 rounded-xl p-4">
                  <Info label="Requester" value={ticket.requester.name} />
                  <Info label="Divisi" value={ticket.division.name} />
                  <Info label="Divisi Penanganan" value={ticket.current_division.name} />
                  <Info label="Aplikasi" value={ticket.application?.name || '—'} />
                  <Info label="Dibuat" value={new Date(ticket.created_at).toLocaleString('id-ID')} />
                  <Info
                    label="Dikirim"
                    value={ticket.submitted_at ? new Date(ticket.submitted_at).toLocaleString('id-ID') : '—'}
                  />
                </div>
              </div>
            )}
          </SectionCard>
          <SectionCard title="Riwayat Status">
            <div className="space-y-4">
              {ticket.history.map((history) => (
                <div key={history.id} className="border-l-2 border-blue-200 pl-4 pb-2">
                  <div className="flex justify-between gap-3">
                    <p className="text-sm font-medium capitalize">{history.action.replace(/_/g, ' ')}</p>
                    <time className="text-xs text-gray-400 whitespace-nowrap">
                      {new Date(history.created_at).toLocaleString('id-ID')}
                    </time>
                  </div>
                  <p className="text-xs text-gray-500">
                    {history.actor?.name} · {history.actor_role}
                  </p>
                  {history.notes && <p className="text-sm mt-1 whitespace-pre-wrap">{history.notes}</p>}
                </div>
              ))}
            </div>
          </SectionCard>
        </div>
        <div className="space-y-5">
          <SectionCard title={`Lampiran (${ticket.attachments.length})`}>
            <div className="space-y-3">
              {ticket.attachments.length === 0 && <p className="text-sm text-gray-500">Belum ada lampiran.</p>}
              {ticket.attachments.map((attachment) => (
                <div key={attachment.id} className="border rounded-lg p-3">
                  <button
                    className="text-sm font-medium text-blue-700 text-left break-all"
                    onClick={() => window.open(ticketService.downloadUrl(id, attachment.id), '_blank')}
                  >
                    {attachment.original_name}
                  </button>
                  <p className="text-xs text-gray-400 mt-1">
                    {Math.ceil(attachment.size / 1024)} KB · {attachment.category}
                  </p>
                  {ticket.allowed_actions.includes('manage_attachment') && (
                    <button
                      className="text-xs text-red-600 mt-2"
                      onClick={() => void ticketService.removeAttachment(id, attachment.id).then(load)}
                    >
                      Hapus
                    </button>
                  )}
                </div>
              ))}
              {ticket.allowed_actions.includes('manage_attachment') && (
                <label className="block text-center border border-dashed rounded-lg p-3 text-sm text-blue-700 cursor-pointer">
                  ＋ Tambah lampiran
                  <input
                    className="sr-only"
                    type="file"
                    accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                    onChange={(event) => void upload(event.target.files?.[0])}
                  />
                </label>
              )}
            </div>
          </SectionCard>
          {ticket.allowed_actions.includes('resubmit') && (
            <Button variant="primary" className="w-full" onClick={() => setAction('resubmit')}>
              Kirim Ulang ke Supervisor
            </Button>
          )}
        </div>
      </div>
      <Modal
        open={action !== null}
        onClose={() => setAction(null)}
        title={action === 'resubmit' ? 'Kirim Ulang Tiket' : 'Batalkan Tiket'}
      >
        <div className="space-y-4">
          <Textarea
            label="Catatan"
            rows={4}
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
            placeholder={action === 'resubmit' ? 'Jelaskan perbaikan yang telah dilakukan...' : 'Alasan pembatalan...'}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAction(null)}>
              Kembali
            </Button>
            <Button
              variant={action === 'cancel' ? 'danger' : 'primary'}
              disabled={busy}
              onClick={() => void confirmAction()}
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
      <p className="font-medium text-gray-900">{value}</p>
    </div>
  )
}
