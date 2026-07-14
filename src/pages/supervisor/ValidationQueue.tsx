import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import { PageHeader, Button, StatusBadge, PriorityBadge, CategoryBadge, SectionCard, Modal, Textarea, Select, Toast } from '../../components/ui'

const pending = TICKETS.filter(t => t.status === 'pending_validation')

export default function ValidationQueue() {
  const navigate = useNavigate()
  const [selected, setSelected] = useState(pending[0] || null)
  const [action, setAction] = useState<'approve' | 'reject' | 'revision' | null>(null)
  const [comment, setComment] = useState('')
  const [rejReason, setRejReason] = useState('')
  const [toast, setToast] = useState('')

  const handleAction = () => {
    const msgs: Record<string, string> = {
      approve: `Tiket ${selected?.id} divalidasi dan diteruskan ke IT Lead.`,
      reject: `Tiket ${selected?.id} ditolak.`,
      revision: `Tiket ${selected?.id} dikembalikan untuk revisi.`,
    }
    setToast(msgs[action!] || '')
    setAction(null)
    setComment('')
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Antrean Validasi"
        subtitle={`${pending.length} tiket menunggu validasi Supervisor`}
        actions={<Button variant="ghost" onClick={() => navigate('/supervisor/dashboard')}>← Dashboard</Button>}
      />

      {pending.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20">
          <div className="text-5xl mb-4">✅</div>
          <h3 className="text-base font-semibold text-gray-700">Tidak Ada Tiket Menunggu</h3>
          <p className="text-sm text-gray-500 mt-1">Semua tiket telah divalidasi</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* List */}
          <div className="space-y-3">
            {pending.map(t => (
              <div
                key={t.id}
                onClick={() => setSelected(t)}
                className={`border rounded-xl p-4 cursor-pointer transition-all ${selected?.id === t.id ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 bg-white hover:shadow-md'}`}
              >
                <div className="flex items-center justify-between mb-2">
                  <span className="font-mono text-xs text-gray-400">{t.id}</span>
                  <PriorityBadge priority={t.priority} />
                </div>
                <p className="text-sm font-semibold text-gray-900 mb-1">{t.title}</p>
                <p className="text-xs text-gray-500 mb-2">{t.requester} · {t.division}</p>
                <div className="flex gap-2">
                  <CategoryBadge category={t.category} />
                  <span className="text-xs text-gray-400">{formatDate(t.createdAt)}</span>
                </div>
              </div>
            ))}
          </div>

          {/* Detail */}
          {selected && (
            <div className="lg:col-span-2 space-y-4">
              <SectionCard title={`Detail Tiket — ${selected.id}`}>
                <div className="flex flex-wrap gap-2 mb-4">
                  <StatusBadge status={selected.status} />
                  <PriorityBadge priority={selected.priority} />
                  <CategoryBadge category={selected.category} />
                </div>

                <h2 className="text-base font-bold text-gray-900 mb-1">{selected.title}</h2>
                <p className="text-sm text-gray-600 leading-relaxed mb-4">{selected.description}</p>

                <div className="grid grid-cols-2 gap-3 text-sm bg-gray-50 rounded-xl p-4 mb-4">
                  {[
                    { label: 'Requester', value: selected.requester },
                    { label: 'Divisi', value: selected.division },
                    { label: 'Aplikasi', value: selected.application },
                    { label: 'Saran Prioritas', value: selected.priority },
                    { label: 'Dibuat', value: formatDate(selected.createdAt) },
                    { label: 'Lampiran', value: selected.attachments.length > 0 ? selected.attachments.join(', ') : 'Tidak ada' },
                  ].map(item => (
                    <div key={item.label}>
                      <p className="text-xs text-gray-500">{item.label}</p>
                      <p className="font-medium text-gray-900 capitalize">{item.value}</p>
                    </div>
                  ))}
                </div>

                {/* Validation Actions */}
                <div className="border-t border-gray-100 pt-4">
                  <p className="text-sm font-semibold text-gray-700 mb-3">Tindakan Validasi</p>
                  <div className="flex gap-3">
                    <Button variant="success" onClick={() => setAction('approve')}>✓ Validasi & Teruskan</Button>
                    <Button variant="warning" onClick={() => setAction('revision')}>✏️ Minta Revisi</Button>
                    <Button variant="danger" onClick={() => setAction('reject')}>✕ Tolak</Button>
                  </div>
                </div>
              </SectionCard>
            </div>
          )}
        </div>
      )}

      {/* Action Modal */}
      <Modal
        open={action !== null}
        onClose={() => setAction(null)}
        title={action === 'approve' ? 'Validasi Tiket' : action === 'reject' ? 'Tolak Tiket' : 'Minta Revisi'}
      >
        <div className="space-y-4">
          {action === 'reject' && (
            <Select
              label="Alasan Penolakan *"
              options={[
                { value: '', label: '— Pilih alasan —' },
                { value: 'duplicate', label: 'Tiket duplikat' },
                { value: 'incomplete', label: 'Informasi tidak lengkap' },
                { value: 'out_of_scope', label: 'Di luar scope IT' },
                { value: 'not_applicable', label: 'Tidak sesuai prosedur' },
                { value: 'other', label: 'Lainnya' },
              ]}
              value={rejReason}
              onChange={e => setRejReason(e.target.value)}
            />
          )}
          <Textarea
            label={action === 'approve' ? 'Catatan (opsional)' : 'Keterangan *'}
            rows={4}
            placeholder={
              action === 'approve' ? 'Tambahkan catatan untuk IT Lead...'
                : action === 'reject' ? 'Jelaskan alasan penolakan kepada requester...'
                  : 'Jelaskan apa yang perlu direvisi...'
            }
            value={comment}
            onChange={e => setComment(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAction(null)}>Batal</Button>
            <Button
              variant={action === 'approve' ? 'success' : action === 'reject' ? 'danger' : 'warning'}
              onClick={handleAction}
            >
              Konfirmasi
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
