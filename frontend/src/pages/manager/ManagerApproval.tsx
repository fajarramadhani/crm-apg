import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { Button, EmptyState, PageHeader, SectionCard, StatusBadge, Textarea, Toast } from '../../components/ui'
import { ticketService, type ApprovalRequestRecord } from '../../services/ticketService'

export default function ManagerApproval() {
  const [items, setItems] = useState<ApprovalRequestRecord[]>([])
  const [selected, setSelected] = useState<ApprovalRequestRecord | null>(null)
  const [decision, setDecision] = useState<'approve' | 'reject' | null>(null)
  const [notes, setNotes] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const load = () => {
    setLoading(true)
    ticketService
      .businessApprovalQueue()
      .then((response) => {
        setItems(response)
        setSelected((current) =>
          current && response.some((item) => item.id === current.id) ? current : response[0] || null,
        )
      })
      .catch((cause) => setError((cause as ApiRequestError).message))
      .finally(() => setLoading(false))
  }
  useEffect(load, [])

  const submit = () => {
    if (!selected || !decision || (decision === 'reject' && !notes.trim())) return
    setBusy(true)
    ticketService
      .businessApproval(selected.ticket?.id || 0, decision, {
        notes: notes || undefined,
        expected_version: selected.steps.find((step) => step.step_type === 'business_approval')?.version || 1,
      })
      .then(() => {
        setSuccess(
          decision === 'approve' ? 'Business approval berhasil.' : 'Approval ditolak dan dikembalikan untuk revisi.',
        )
        setDecision(null)
        setNotes('')
        load()
      })
      .catch((cause) => setError((cause as ApiRequestError).message))
      .finally(() => setBusy(false))
  }

  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      {success && <Toast type="success" message={success} onClose={() => setSuccess('')} />}
      <PageHeader title="Business Approval" subtitle="Tinjau kebutuhan bisnis dan risiko sebelum persiapan rilis" />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat approval queue...</p>
      ) : items.length === 0 ? (
        <EmptyState title="Tidak ada approval" message="Belum ada tiket yang ditugaskan kepada Anda." />
      ) : (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
          <div className="space-y-2">
            {items.map((item) => (
              <button
                key={item.id}
                className={`w-full rounded-xl border p-4 text-left ${selected?.id === item.id ? 'border-blue-700 bg-blue-50' : 'border-gray-200 bg-white'}`}
                onClick={() => setSelected(item)}
              >
                <span className="font-mono text-xs text-gray-500">{item.ticket?.ticket_number}</span>
                <p className="mt-1 text-sm font-semibold text-gray-900">{item.ticket?.title}</p>
                <div className="mt-2 flex gap-2">
                  <StatusBadge status={item.ticket?.status || item.status} />
                  <span className="text-xs font-semibold uppercase text-amber-700">Risk {item.release_risk_level}</span>
                </div>
              </button>
            ))}
          </div>
          {selected && (
            <div className="space-y-4 lg:col-span-2">
              <SectionCard title={`Review ${selected.ticket?.ticket_number || ''}`}>
                <div className="space-y-4">
                  <div>
                    <h2 className="text-lg font-bold text-gray-900">{selected.ticket?.title}</h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-gray-600">{selected.summary}</p>
                  </div>
                  <div className="grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-sm">
                    <div>
                      <span className="text-xs text-gray-500">Business impact</span>
                      <p>{selected.business_impact || '—'}</p>
                    </div>
                    <div>
                      <span className="text-xs text-gray-500">Proposed release</span>
                      <p>
                        {selected.proposed_release_at
                          ? new Date(selected.proposed_release_at).toLocaleString('id-ID')
                          : '—'}
                      </p>
                    </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button variant="success" onClick={() => setDecision('approve')}>
                      Setujui
                    </Button>
                    <Button variant="danger" onClick={() => setDecision('reject')}>
                      Tolak
                    </Button>
                  </div>
                </div>
              </SectionCard>
              {decision && (
                <SectionCard title={decision === 'approve' ? 'Konfirmasi Approval' : 'Alasan Penolakan'}>
                  <div className="space-y-3">
                    <Textarea
                      label={decision === 'reject' ? 'Alasan wajib' : 'Catatan (opsional)'}
                      rows={4}
                      value={notes}
                      onChange={(event) => setNotes(event.target.value)}
                    />
                    <div className="flex justify-end gap-2">
                      <Button variant="secondary" onClick={() => setDecision(null)}>
                        Batal
                      </Button>
                      <Button
                        variant={decision === 'approve' ? 'success' : 'danger'}
                        loading={busy}
                        disabled={decision === 'reject' && !notes.trim()}
                        onClick={submit}
                      >
                        Konfirmasi
                      </Button>
                    </div>
                  </div>
                </SectionCard>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
