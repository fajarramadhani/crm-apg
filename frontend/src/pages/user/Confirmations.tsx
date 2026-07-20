import { useState, useEffect } from 'react'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { PageHeader, SectionCard, Button, Toast, EmptyState, StatusBadge, Textarea } from '../../components/ui'

export default function Confirmations() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [rejectionReason, setRejectionReason] = useState('')

  const load = async () => {
    setLoading(true)
    try {
      const res = await ticketService.requesterConfirmationsQueue()
      if (res) setTickets(res)
      if (selected) {
        const detailRes = await ticketService.get(selected.id)
        if (detailRes) setSelected(detailRes)
      }
    } catch {
      setError('Gagal memuat daftar konfirmasi')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const act = async (fn: () => Promise<any>, msg: string) => {
    setBusy(true)
    try {
      await fn()
      setSuccess(msg)
      setSelected(null)
      await load()
    } catch (err: any) {
      setError(err.response?.data?.message || 'Aksi gagal')
    } finally {
      setBusy(false)
    }
  }

  const handleAccept = () =>
    selected &&
    act(
      () => ticketService.submitRequesterConfirmation(selected.id, { status: 'accepted' }),
      'Konfirmasi diterima. Tiket akan segera ditutup oleh IT Lead.',
    )

  const handleReject = () => {
    if (!rejectionReason) {
      setError('Alasan penolakan (rejection reason) wajib diisi.')
      return
    }
    selected &&
      act(
        () =>
          ticketService.submitRequesterConfirmation(selected.id, {
            status: 'rejected',
            rejection_reason: rejectionReason,
          }),
        'Konfirmasi ditolak. Tiket dikembalikan ke tim IT.',
      )
  }

  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      {success && <Toast type="success" message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Konfirmasi Penyelesaian"
        subtitle="Konfirmasi final bahwa tiket telah diselesaikan dengan baik"
      />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat...</p>
      ) : (
        <div className="grid gap-5 lg:grid-cols-3">
          <SectionCard title="Menunggu Konfirmasi" className="lg:col-span-1">
            <div className="space-y-3">
              {tickets.length === 0 ? (
                <EmptyState
                  title="Tidak ada konfirmasi"
                  message="Tidak ada tiket yang menunggu konfirmasi Anda saat ini."
                />
              ) : (
                tickets.map((ticket) => (
                  <button
                    key={ticket.id}
                    className={`w-full rounded-xl border p-3 text-left ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'bg-white'}`}
                    onClick={() => setSelected(ticket)}
                  >
                    <b className="font-mono text-xs">{ticket.ticket_number}</b>
                    <p className="text-sm font-semibold">{ticket.title}</p>
                    <StatusBadge status={ticket.status} />
                  </button>
                ))
              )}
            </div>
          </SectionCard>

          {selected && (
            <div className="space-y-5 lg:col-span-2">
              <SectionCard title="Detail Konfirmasi">
                <div className="space-y-4">
                  <div>
                    <h3 className="font-semibold text-lg">{selected.title}</h3>
                    <p className="text-sm text-gray-600 mt-1">{selected.description}</p>
                  </div>

                  <div className="rounded bg-blue-50 p-4 text-sm text-blue-900 border border-blue-200">
                    Tim IT telah menyelesaikan deployment dan masa monitoring untuk tiket ini. Silakan berikan
                    konfirmasi akhir apakah Anda menerima penyelesaian ini atau menolaknya karena masih ada isu (bug).
                  </div>

                  <div className="space-y-3 pt-4 border-t">
                    <p className="font-semibold text-sm">Jika Menolak (Reject):</p>
                    <Textarea
                      label="Alasan Penolakan (Wajib jika reject)"
                      rows={3}
                      value={rejectionReason}
                      onChange={(e: any) => setRejectionReason(e.target.value)}
                    />
                  </div>

                  <div className="flex gap-3 pt-4">
                    <Button variant="success" loading={busy} onClick={handleAccept}>
                      Terima Penyelesaian (Accept)
                    </Button>
                    <Button variant="danger" loading={busy} onClick={handleReject}>
                      Tolak & Kembalikan (Reject)
                    </Button>
                  </div>
                </div>
              </SectionCard>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
