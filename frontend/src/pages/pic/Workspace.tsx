import { useEffect, useState } from 'react'
import { Button, EmptyState, PageHeader, PriorityBadge, SectionCard, StatusBadge, Toast } from '../../components/ui'
import { ticketService, type TicketRecord } from '../../services/ticketService'

export default function Workspace() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  useEffect(() => {
    ticketService
      .picAssignments({ per_page: 50 })
      .then(async (response) => {
        setTickets(response.data)
        if (response.data[0]) setSelected(await ticketService.picGet(response.data[0].id))
      })
      .catch(() => setError('Assignment PIC tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])
  const open = async (id: number) => {
    try {
      setSelected(await ticketService.picGet(id))
    } catch {
      setError('Detail assignment tidak dapat dibuka.')
    }
  }
  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      <PageHeader title="Workspace Tiket" subtitle="Assignment aktif milik Anda (read-only pada Phase 6)" />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500" role="status">
          Memuat assignment...
        </p>
      ) : tickets.length === 0 ? (
        <EmptyState title="Belum ada assignment" message="Tiket yang ditugaskan kepada Anda akan muncul di sini." />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-5">
          <div className="space-y-2">
            {tickets.map((t) => (
              <button
                key={t.id}
                onClick={() => void open(t.id)}
                className={`w-full text-left border rounded-xl p-3 ${selected?.id === t.id ? 'border-blue-700 bg-blue-50' : 'bg-white border-gray-200'}`}
              >
                <span className="font-mono text-xs text-gray-500">{t.ticket_number}</span>
                <p className="font-medium text-sm mt-1">{t.title}</p>
                <div className="flex gap-2 mt-2">
                  <StatusBadge status={t.status} />
                  {t.final_priority && <PriorityBadge priority={t.final_priority.key} />}
                </div>
              </button>
            ))}
          </div>
          <div className="lg:col-span-3">
            {selected && (
              <SectionCard title={selected.ticket_number}>
                <div className="flex flex-wrap gap-2 mb-4">
                  <StatusBadge status={selected.status} />
                  {selected.final_priority && <PriorityBadge priority={selected.final_priority.key} />}
                </div>
                <h2 className="font-bold text-lg">{selected.title}</h2>
                <p className="text-sm text-gray-600 whitespace-pre-wrap mt-2">{selected.description}</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 rounded-xl p-4 mt-5">
                  <Info label="Requester" value={selected.requester.name} />
                  <Info label="Aplikasi" value={selected.application?.name || '—'} />
                  <Info label="Kategori" value={selected.category.name} />
                  <Info
                    label="Assigned"
                    value={selected.assigned_at ? new Date(selected.assigned_at).toLocaleString('id-ID') : '—'}
                  />
                  <Info
                    label="Resolution deadline"
                    value={
                      selected.resolution_due_at ? new Date(selected.resolution_due_at).toLocaleString('id-ID') : '—'
                    }
                  />
                  <Info label="Sisa waktu" value={remaining(selected.resolution_due_at)} />
                </div>
                <SectionCard title="Lampiran" className="mt-5">
                  {selected.attachments.length === 0 ? (
                    <p className="text-sm text-gray-500">Tidak ada lampiran.</p>
                  ) : (
                    selected.attachments.map((a) => (
                      <Button
                        key={a.id}
                        variant="ghost"
                        onClick={() => window.open(ticketService.downloadUrl(selected.id, a.id), '_blank')}
                      >
                        {a.original_name}
                      </Button>
                    ))
                  )}
                </SectionCard>
                <SectionCard title="Riwayat" className="mt-5">
                  <div className="space-y-3">
                    {selected.history.map((h) => (
                      <div key={h.id} className="border-l-2 border-blue-200 pl-3">
                        <p className="text-sm font-medium capitalize">{h.action.replace(/_/g, ' ')}</p>
                        <p className="text-xs text-gray-500">
                          {h.actor?.name} · {new Date(h.created_at).toLocaleString('id-ID')}
                        </p>
                        {h.notes && <p className="text-sm mt-1">{h.notes}</p>}
                      </div>
                    ))}
                  </div>
                </SectionCard>
              </SectionCard>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="font-medium text-sm">{value}</p>
    </div>
  )
}
function remaining(deadline: string | null) {
  if (!deadline) return '—'
  const ms = new Date(deadline).getTime() - Date.now()
  const hours = Math.ceil(Math.abs(ms) / 3600000)
  return ms < 0 ? `${hours} jam lewat` : `${hours} jam tersisa`
}
