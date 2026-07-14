import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import { PageHeader, Button, StatusBadge, PriorityBadge, SLAIndicator, SectionCard, Modal, Textarea, Table, TR, TD, Toast, KPICard } from '../../components/ui'

const pendingApproval = TICKETS.filter(t => t.status === 'pending_approval')
const allRelevant = TICKETS.filter(t => !['draft', 'pending_validation', 'rejected'].includes(t.status))

export default function ManagerApproval() {
  const navigate = useNavigate()
  const [selected, setSelected] = useState(pendingApproval[0] || null)
  const [action, setAction] = useState<'approve' | 'reject' | null>(null)
  const [comment, setComment] = useState('')
  const [toast, setToast] = useState('')

  const handleAction = () => {
    setToast(action === 'approve' ? `Tiket ${selected?.id} disetujui. Proses deployment dapat dimulai.` : `Tiket ${selected?.id} ditolak. Dikembalikan ke PIC.`)
    setAction(null)
    setComment('')
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type={action === 'approve' ? 'success' : 'error'} onClose={() => setToast('')} />}

      <PageHeader title="Manager Approval" subtitle="Tinjau dan berikan persetujuan untuk tiket yang memerlukan approval" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Menunggu Approval" value={pendingApproval.length} subtitle="Perlu keputusan Anda" color="amber" icon="⏳" />
        <KPICard title="Disetujui Bulan Ini" value={23} subtitle="Tiket" color="green" icon="✅" />
        <KPICard title="Ditolak Bulan Ini" value={4} subtitle="Tiket" color="red" icon="✕" />
        <KPICard title="Rata-rata Waktu Review" value="2.3j" subtitle="Jam per tiket" color="blue" icon="⏱️" />
      </div>

      {pendingApproval.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-center gap-3">
          <span className="text-amber-500 text-xl">⚠️</span>
          <p className="text-sm font-semibold text-amber-800">{pendingApproval.length} tiket menunggu persetujuan Anda. Silakan tinjau dan berikan keputusan.</p>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Pending List */}
        <div>
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Menunggu Approval</p>
          <div className="space-y-3">
            {pendingApproval.length === 0 ? (
              <div className="text-center py-8 text-gray-500 text-sm">Tidak ada tiket menunggu approval</div>
            ) : pendingApproval.map(t => (
              <div
                key={t.id}
                onClick={() => setSelected(t)}
                className={`border rounded-xl p-4 cursor-pointer transition-all ${selected?.id === t.id ? 'border-[#1E3A8A] bg-blue-50' : 'border-amber-200 bg-amber-50/30 hover:shadow-md'}`}
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="font-mono text-xs text-gray-400">{t.id}</span>
                  <PriorityBadge priority={t.priority} />
                </div>
                <p className="text-sm font-semibold text-gray-900">{t.title}</p>
                <p className="text-xs text-gray-500 mt-1">{t.application}</p>
                <p className="text-xs text-gray-400 mt-1">PIC: {t.pic} · {formatDate(t.updatedAt)}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Detail */}
        {selected && (
          <div className="lg:col-span-2 space-y-4">
            <SectionCard title={`Review — ${selected.id}`}>
              <div className="flex flex-wrap gap-2 mb-3">
                <StatusBadge status={selected.status} />
                <PriorityBadge priority={selected.priority} />
              </div>

              <h2 className="text-base font-bold text-gray-900 mb-2">{selected.title}</h2>
              <p className="text-sm text-gray-600 leading-relaxed mb-4">{selected.description}</p>

              <div className="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 text-sm mb-4">
                {[
                  { label: 'Requester', value: selected.requester },
                  { label: 'Divisi', value: selected.division },
                  { label: 'Aplikasi', value: selected.application },
                  { label: 'PIC', value: selected.pic },
                  { label: 'Dibuat', value: formatDate(selected.createdAt) },
                  { label: 'SLA Status', value: selected.overSla ? '⚠️ Over SLA' : '✅ Dalam SLA' },
                ].map(item => (
                  <div key={item.label}>
                    <p className="text-xs text-gray-500">{item.label}</p>
                    <p className="font-medium text-gray-900">{item.value}</p>
                  </div>
                ))}
              </div>

              {/* SLA Indicator */}
              <div className="mb-4">
                <SLAIndicator slaRemaining={selected.slaRemaining} overSla={selected.overSla} slaHours={selected.slaHours} />
              </div>

              <div className="border-t border-gray-100 pt-4">
                <p className="text-sm font-semibold text-gray-700 mb-3">Keputusan Manager</p>
                <div className="flex gap-3">
                  <Button variant="success" onClick={() => setAction('approve')}>✅ Setujui Deployment</Button>
                  <Button variant="danger" onClick={() => setAction('reject')}>✕ Tolak</Button>
                </div>
              </div>
            </SectionCard>
          </div>
        )}
      </div>

      {/* All Tickets Table */}
      <div className="mt-6">
        <SectionCard title="Semua Tiket dalam Scope Manager">
          <Table headers={['ID', 'Judul', 'Prioritas', 'Status', 'PIC', 'SLA', 'Dibuat']}>
            {allRelevant.slice(0, 10).map(t => (
              <TR key={t.id} highlight={t.overSla} onClick={() => navigate(`/user/tickets/${t.id}`)}>
                <TD><span className="font-mono text-xs">{t.id}</span></TD>
                <TD><p className="text-sm font-medium max-w-[200px] truncate">{t.title}</p></TD>
                <TD><PriorityBadge priority={t.priority} /></TD>
                <TD><StatusBadge status={t.status} /></TD>
                <TD><span className="text-xs">{t.pic || '—'}</span></TD>
                <TD><div className="w-28"><SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} /></div></TD>
                <TD><span className="text-xs text-gray-400 whitespace-nowrap">{formatDate(t.createdAt)}</span></TD>
              </TR>
            ))}
          </Table>
        </SectionCard>
      </div>

      {/* Action Modal */}
      <Modal open={action !== null} onClose={() => setAction(null)} title={action === 'approve' ? 'Setujui Deployment' : 'Tolak Tiket'}>
        <div className="space-y-4">
          <div className={`flex items-center gap-3 p-4 rounded-xl ${action === 'approve' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'}`}>
            <span className="text-2xl">{action === 'approve' ? '✅' : '❌'}</span>
            <div>
              <p className={`font-semibold text-sm ${action === 'approve' ? 'text-emerald-800' : 'text-red-800'}`}>
                {action === 'approve' ? 'Konfirmasi Persetujuan Deployment' : 'Konfirmasi Penolakan'}
              </p>
              <p className={`text-xs mt-0.5 ${action === 'approve' ? 'text-emerald-600' : 'text-red-600'}`}>{selected?.id} — {selected?.title}</p>
            </div>
          </div>
          <Textarea
            label={action === 'approve' ? 'Catatan Persetujuan (opsional)' : 'Alasan Penolakan *'}
            rows={4}
            placeholder={action === 'approve' ? 'Catatan untuk tim deployment...' : 'Jelaskan alasan penolakan...'}
            value={comment}
            onChange={e => setComment(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAction(null)}>Batal</Button>
            <Button variant={action === 'approve' ? 'success' : 'danger'} onClick={handleAction}>
              Konfirmasi
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
