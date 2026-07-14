import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { TICKETS, ACTIVITY_LOGS, formatDate, formatDateTime } from '../../data'
import { PageHeader, Button, StatusBadge, PriorityBadge, CategoryBadge, SLAIndicator, ActivityTimeline, Modal, Textarea, Toast, SectionCard, Tabs, OverSLABanner } from '../../components/ui'

export default function TicketDetail() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const ticket = TICKETS.find(t => t.id === id) || TICKETS[0]
  const logs = ACTIVITY_LOGS.filter(l => l.ticketId === ticket.id)

  const [tab, setTab] = useState('Ringkasan')
  const [showComment, setShowComment] = useState(false)
  const [comment, setComment] = useState('')
  const [toast, setToast] = useState('')

  const handleAddComment = () => {
    setToast('Komentar berhasil ditambahkan')
    setComment('')
    setShowComment(false)
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title={ticket.id}
        subtitle={ticket.title}
        actions={
          <div className="flex gap-2">
            {ticket.status === 'uat' && (
              <Button variant="success" onClick={() => navigate('/user/uat')}>🧪 Mulai UAT</Button>
            )}
            <Button variant="ghost" onClick={() => navigate('/user/tickets')}>← Kembali</Button>
          </div>
        }
      />

      {ticket.overSla && <OverSLABanner ticketId={ticket.id} />}

      {/* Status Strip */}
      <div className="flex items-center gap-3 flex-wrap bg-white border border-gray-200 rounded-xl px-5 py-3 mb-6 shadow-sm">
        <StatusBadge status={ticket.status} />
        <PriorityBadge priority={ticket.priority} />
        <CategoryBadge category={ticket.category} />
        <div className="mx-2 h-5 w-px bg-gray-200" />
        <span className="text-xs text-gray-500">PIC: <span className="font-medium text-gray-700">{ticket.pic || '—'}</span></span>
        <span className="text-xs text-gray-500">Aplikasi: <span className="font-medium text-gray-700">{ticket.application}</span></span>
        <span className="text-xs text-gray-500">Dibuat: <span className="font-medium text-gray-700">{formatDate(ticket.createdAt)}</span></span>
        <div className="ml-auto w-48">
          <SLAIndicator slaRemaining={ticket.slaRemaining} overSla={ticket.overSla} slaHours={ticket.slaHours} />
        </div>
      </div>

      <Tabs tabs={['Ringkasan', 'Activity Timeline', 'Lampiran']} active={tab} onChange={setTab} />

      {tab === 'Ringkasan' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-4">
            <SectionCard title="Deskripsi Masalah">
              <p className="text-sm text-gray-700 leading-relaxed">{ticket.description}</p>
            </SectionCard>

            {ticket.tags.length > 0 && (
              <SectionCard title="Tags">
                <div className="flex flex-wrap gap-2">
                  {ticket.tags.map(tag => (
                    <span key={tag} className="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">#{tag}</span>
                  ))}
                </div>
              </SectionCard>
            )}
          </div>

          <div className="space-y-4">
            <SectionCard title="Informasi Tiket">
              <div className="space-y-3 text-sm">
                {[
                  { label: 'ID Tiket', value: ticket.id },
                  { label: 'Aplikasi', value: ticket.application },
                  { label: 'Divisi', value: ticket.division },
                  { label: 'Requester', value: ticket.requester },
                  { label: 'Supervisor', value: ticket.supervisor },
                  { label: 'PIC', value: ticket.pic || '—' },
                  { label: 'SLA Deadline', value: formatDateTime(ticket.slaDeadline) },
                  { label: 'Dibuat', value: formatDateTime(ticket.createdAt) },
                  { label: 'Diupdate', value: formatDateTime(ticket.updatedAt) },
                ].map(item => (
                  <div key={item.label} className="flex justify-between gap-2">
                    <span className="text-gray-500 shrink-0">{item.label}</span>
                    <span className="font-medium text-gray-900 text-right">{item.value}</span>
                  </div>
                ))}
              </div>
            </SectionCard>

            {/* User Actions */}
            <SectionCard title="Aksi">
              <div className="space-y-2">
                <button onClick={() => setShowComment(true)} className="w-full text-sm text-left px-3 py-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium transition-colors">
                  💬 Tambah Komentar
                </button>
                {ticket.status === 'uat' && (
                  <button onClick={() => navigate('/user/uat')} className="w-full text-sm text-left px-3 py-2.5 rounded-lg border border-cyan-200 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-medium transition-colors">
                    🧪 Lakukan UAT
                  </button>
                )}
              </div>
            </SectionCard>
          </div>
        </div>
      )}

      {tab === 'Activity Timeline' && (
        <SectionCard title={`Timeline Aktivitas (${logs.length} entri)`}>
          {logs.length > 0 ? (
            <ActivityTimeline logs={logs} />
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">Belum ada aktivitas</p>
          )}
        </SectionCard>
      )}

      {tab === 'Lampiran' && (
        <SectionCard title="Lampiran">
          {ticket.attachments.length > 0 ? (
            <div className="space-y-2">
              {ticket.attachments.map(att => (
                <div key={att} className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                  <span className="text-xl">📎</span>
                  <span className="text-sm text-gray-700 font-medium">{att}</span>
                  <span className="ml-auto text-xs text-[#1E3A8A] hover:underline">Download</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500 text-center py-8">Tidak ada lampiran</p>
          )}
        </SectionCard>
      )}

      {/* Comment Modal */}
      <Modal open={showComment} onClose={() => setShowComment(false)} title="Tambah Komentar">
        <div className="space-y-4">
          <Textarea
            label="Komentar"
            rows={4}
            placeholder="Tulis komentar atau pertanyaan..."
            value={comment}
            onChange={e => setComment(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowComment(false)}>Batal</Button>
            <Button variant="primary" onClick={handleAddComment} disabled={!comment}>Kirim</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
