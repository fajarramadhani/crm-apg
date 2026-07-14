import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS, ACTIVITY_LOGS, formatDate, formatDateTime } from '../../data'
import {
  PageHeader,
  Button,
  StatusBadge,
  PriorityBadge,
  SLAIndicator,
  SectionCard,
  Textarea,
  Toast,
  Modal,
  ActivityTimeline,
  Tabs,
} from '../../components/ui'

const myTickets = TICKETS.filter((t) => t.picId === 'u4' && !['closed', 'done'].includes(t.status))

export default function Workspace() {
  const navigate = useNavigate()
  const [selectedId, setSelectedId] = useState(myTickets[0]?.id || '')
  const ticket = myTickets.find((t) => t.id === selectedId) || myTickets[0]
  const logs = ACTIVITY_LOGS.filter((l) => l.ticketId === ticket?.id)
  const [tab, setTab] = useState('Progress')
  const [progress, setProgress] = useState('')
  const [showStatus, setShowStatus] = useState(false)
  const [nextStatus, setNextStatus] = useState('')
  const [toast, setToast] = useState('')

  const handleUpdate = () => {
    setToast('Progress berhasil diupdate')
    setProgress('')
    setTimeout(() => setToast(''), 3000)
  }

  const handleStatusChange = () => {
    setToast(`Status tiket diubah ke: ${nextStatus}`)
    setShowStatus(false)
    setTimeout(() => setToast(''), 3000)
  }

  if (!ticket) return <div className="p-6 text-gray-500">Tidak ada tiket aktif</div>

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Workspace Tiket"
        subtitle="Kelola dan kerjakan tiket yang di-assign kepada Anda"
        actions={
          <Button variant="ghost" onClick={() => navigate('/pic/dashboard')}>
            ← Dashboard
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Ticket List */}
        <div className="space-y-2">
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Tiket Saya</p>
          {myTickets.map((t) => (
            <div
              key={t.id}
              onClick={() => setSelectedId(t.id)}
              className={`border rounded-xl p-3 cursor-pointer transition-all ${selectedId === t.id ? 'border-[#1E3A8A] bg-blue-50' : `border-gray-200 hover:shadow-md ${t.overSla ? 'bg-red-50/30 border-red-200' : 'bg-white'}`}`}
            >
              <div className="flex justify-between items-start mb-1">
                <span className="font-mono text-xs text-gray-400">{t.id.slice(-8)}</span>
                {t.overSla && <span className="text-red-500 text-xs">🚨</span>}
              </div>
              <p className="text-xs font-medium text-gray-800 line-clamp-2">{t.title}</p>
              <div className="mt-2 flex items-center gap-1.5 flex-wrap">
                <PriorityBadge priority={t.priority} />
                <StatusBadge status={t.status} />
              </div>
            </div>
          ))}
        </div>

        {/* Main Workspace */}
        <div className="lg:col-span-3 space-y-4">
          {/* Header */}
          <SectionCard>
            <div className="flex items-start justify-between flex-wrap gap-3">
              <div>
                <div className="flex flex-wrap gap-2 mb-2">
                  <span className="font-mono text-sm text-gray-400">{ticket.id}</span>
                  <PriorityBadge priority={ticket.priority} />
                  <StatusBadge status={ticket.status} />
                  {ticket.overSla && (
                    <span className="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-bold animate-pulse">
                      🚨 OVER SLA
                    </span>
                  )}
                </div>
                <h2 className="text-base font-bold text-gray-900">{ticket.title}</h2>
                <p className="text-xs text-gray-500 mt-1">
                  {ticket.application} · Requester: {ticket.requester}
                </p>
              </div>
              <div className="w-48">
                <SLAIndicator slaRemaining={ticket.slaRemaining} overSla={ticket.overSla} slaHours={ticket.slaHours} />
              </div>
            </div>
          </SectionCard>

          <Tabs tabs={['Progress', 'Detail', 'Timeline']} active={tab} onChange={setTab} />

          {tab === 'Progress' && (
            <div className="space-y-4">
              <SectionCard title="Update Progress">
                <Textarea
                  label="Tulis update progress"
                  rows={4}
                  placeholder="Jelaskan apa yang sudah dikerjakan, temuan, dan langkah selanjutnya..."
                  value={progress}
                  onChange={(e) => setProgress(e.target.value)}
                />
                <div className="flex items-center justify-between mt-3">
                  <Button variant="secondary" size="sm" onClick={() => navigate('/pic/rca')}>
                    🔍 Buka RCA Form
                  </Button>
                  <div className="flex gap-2">
                    <Button variant="secondary" size="sm" onClick={() => setShowStatus(true)}>
                      Ubah Status
                    </Button>
                    <Button variant="primary" size="sm" onClick={handleUpdate} disabled={!progress}>
                      💾 Simpan Update
                    </Button>
                  </div>
                </div>
              </SectionCard>

              {/* Progress Checklist */}
              <SectionCard title="Checklist Pengerjaan">
                <div className="space-y-2">
                  {[
                    { done: true, label: 'Tiket diterima dan dipahami' },
                    { done: true, label: 'Investigasi awal selesai' },
                    { done: true, label: 'Root cause teridentifikasi' },
                    { done: false, label: 'Fix/solution dikembangkan' },
                    { done: false, label: 'Testing di lingkungan dev selesai' },
                    { done: false, label: 'Submit ke QA untuk internal testing' },
                  ].map((item, i) => (
                    <div
                      key={i}
                      className={`flex items-center gap-3 p-2.5 rounded-lg ${item.done ? 'bg-emerald-50' : 'bg-gray-50'}`}
                    >
                      <div
                        className={`w-5 h-5 rounded-full flex items-center justify-center text-xs ${item.done ? 'bg-emerald-500 text-white' : 'border-2 border-gray-300'}`}
                      >
                        {item.done ? '✓' : ''}
                      </div>
                      <span
                        className={`text-sm ${item.done ? 'text-gray-600 line-through' : 'text-gray-800 font-medium'}`}
                      >
                        {item.label}
                      </span>
                    </div>
                  ))}
                </div>
              </SectionCard>
            </div>
          )}

          {tab === 'Detail' && (
            <SectionCard title="Detail Tiket">
              <p className="text-sm text-gray-700 leading-relaxed mb-4">{ticket.description}</p>
              <div className="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 text-sm">
                {[
                  { label: 'Kategori', value: ticket.category },
                  { label: 'Aplikasi', value: ticket.application },
                  { label: 'Divisi', value: ticket.division },
                  { label: 'Requester', value: ticket.requester },
                  { label: 'Dibuat', value: formatDate(ticket.createdAt) },
                  { label: 'SLA Deadline', value: formatDateTime(ticket.slaDeadline) },
                ].map((item) => (
                  <div key={item.label}>
                    <p className="text-xs text-gray-500">{item.label}</p>
                    <p className="font-medium text-gray-900 capitalize">{item.value}</p>
                  </div>
                ))}
              </div>
            </SectionCard>
          )}

          {tab === 'Timeline' && (
            <SectionCard title="Activity Timeline">
              <ActivityTimeline logs={logs} />
            </SectionCard>
          )}
        </div>
      </div>

      {/* Change Status Modal */}
      <Modal open={showStatus} onClose={() => setShowStatus(false)} title="Ubah Status Tiket">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            {[
              { value: 'in_progress', label: '⚡ In Progress', desc: 'Sedang dikerjakan' },
              { value: 'internal_testing', label: '🔬 Internal Testing', desc: 'Submit ke QA untuk testing' },
              { value: 'pending_approval', label: '⏳ Pending Approval', desc: 'Menunggu Manager Approval' },
              { value: 'done', label: '✅ Done', desc: 'Pekerjaan selesai' },
            ].map((s) => (
              <button
                key={s.value}
                onClick={() => setNextStatus(s.value)}
                className={`p-3 text-left border rounded-xl text-sm transition-all ${nextStatus === s.value ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
              >
                <p className="font-semibold text-gray-900">{s.label}</p>
                <p className="text-xs text-gray-500 mt-0.5">{s.desc}</p>
              </button>
            ))}
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowStatus(false)}>
              Batal
            </Button>
            <Button variant="primary" onClick={handleStatusChange} disabled={!nextStatus}>
              Ubah Status
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
