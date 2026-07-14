import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import { PageHeader, Button, CategoryBadge, SectionCard, Modal, Textarea, Select, Toast } from '../../components/ui'

const queue = TICKETS.filter((t) => ['validated', 'triage', 'pending_validation'].includes(t.status))

export default function TriageQueue() {
  const navigate = useNavigate()
  const [selected, setSelected] = useState(queue[0] || null)
  const [showAssign, setShowAssign] = useState(false)
  const [form, setForm] = useState({ priority: 'high', pic: 'u4', sla: '48', note: '' })
  const [toast, setToast] = useState('')

  const handleAssign = () => {
    setToast(`Tiket ${selected?.id} berhasil di-assign ke PIC. SLA ${form.sla} jam ditetapkan.`)
    setShowAssign(false)
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Antrean Triage"
        subtitle={`${queue.length} tiket menunggu penentuan prioritas`}
        actions={
          <Button variant="ghost" onClick={() => navigate('/itlead/dashboard')}>
            ← Dashboard
          </Button>
        }
      />

      {queue.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20">
          <div className="text-5xl mb-4">🎯</div>
          <h3 className="text-base font-semibold text-gray-700">Triage Queue Kosong</h3>
          <p className="text-sm text-gray-500 mt-1">Semua tiket telah ditangani</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Queue List */}
          <div className="space-y-3">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1">Tiket Menunggu</p>
            {queue.map((t) => (
              <div
                key={t.id}
                onClick={() => setSelected(t)}
                className={`border rounded-xl p-4 cursor-pointer transition-all ${selected?.id === t.id ? 'border-[#1E3A8A] bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:shadow-md'}`}
              >
                <div className="flex items-center justify-between mb-1">
                  <span className="font-mono text-xs text-gray-400">{t.id}</span>
                  <CategoryBadge category={t.category} />
                </div>
                <p className="text-sm font-semibold text-gray-900 line-clamp-2">{t.title}</p>
                <p className="text-xs text-gray-500 mt-1">
                  {t.requester} · {t.division}
                </p>
                <p className="text-xs text-gray-400 mt-1">{formatDate(t.createdAt)}</p>
              </div>
            ))}
          </div>

          {/* Detail Panel */}
          {selected && (
            <div className="lg:col-span-2 space-y-4">
              <SectionCard title={`Detail — ${selected.id}`}>
                <div className="flex flex-wrap gap-2 mb-3">
                  <CategoryBadge category={selected.category} />
                  <span className="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full font-medium">
                    Menunggu Triage
                  </span>
                </div>

                <h2 className="text-base font-bold text-gray-900 mb-2">{selected.title}</h2>
                <p className="text-sm text-gray-600 leading-relaxed mb-4">{selected.description}</p>

                <div className="grid grid-cols-2 gap-3 bg-gray-50 rounded-xl p-4 text-sm mb-4">
                  {[
                    { label: 'Requester', value: selected.requester },
                    { label: 'Divisi', value: selected.division },
                    { label: 'Aplikasi', value: selected.application },
                    { label: 'Saran Prioritas User', value: selected.priority },
                    { label: 'Dibuat', value: formatDate(selected.createdAt) },
                    { label: 'Supervisor', value: selected.supervisor },
                  ].map((item) => (
                    <div key={item.label}>
                      <p className="text-xs text-gray-500">{item.label}</p>
                      <p className="font-medium text-gray-900 capitalize">{item.value}</p>
                    </div>
                  ))}
                </div>

                {/* Triage Criteria */}
                <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                  <p className="text-xs font-semibold text-blue-800 mb-2">Kriteria Penetapan Prioritas</p>
                  <div className="grid grid-cols-2 gap-2 text-xs text-blue-700">
                    <div>
                      🔴 <strong>Critical:</strong> Sistem down, dampak &gt;50% user
                    </div>
                    <div>
                      🟠 <strong>High:</strong> Fungsi utama terganggu
                    </div>
                    <div>
                      🟡 <strong>Medium:</strong> Fungsi non-kritis terdampak
                    </div>
                    <div>
                      🟢 <strong>Low:</strong> Minor, ada workaround
                    </div>
                  </div>
                </div>

                <Button variant="primary" onClick={() => setShowAssign(true)} className="w-full justify-center">
                  🎯 Tetapkan Prioritas, SLA & PIC
                </Button>
              </SectionCard>
            </div>
          )}
        </div>
      )}

      {/* Assignment Modal */}
      <Modal open={showAssign} onClose={() => setShowAssign(false)} title={`Penugasan — ${selected?.id}`} size="lg">
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Prioritas *"
              value={form.priority}
              onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))}
              options={[
                { value: 'critical', label: '🔴 Critical' },
                { value: 'high', label: '🟠 High' },
                { value: 'medium', label: '🟡 Medium' },
                { value: 'low', label: '🟢 Low' },
              ]}
            />
            <Select
              label="SLA (jam) *"
              value={form.sla}
              onChange={(e) => setForm((f) => ({ ...f, sla: e.target.value }))}
              options={[
                { value: '24', label: '24 jam (Critical)' },
                { value: '48', label: '48 jam (High)' },
                { value: '168', label: '168 jam / 7 hari (Medium)' },
                { value: '336', label: '336 jam / 14 hari (Low)' },
              ]}
            />
          </div>
          <Select
            label="Assign PIC *"
            value={form.pic}
            onChange={(e) => setForm((f) => ({ ...f, pic: e.target.value }))}
            options={[
              { value: 'u4', label: 'Dian Kusuma — IT Development' },
              { value: 'u10', label: 'Linda Susanti — IT Development' },
            ]}
          />
          <Textarea
            label="Catatan untuk PIC"
            rows={3}
            placeholder="Instruksi khusus, prioritas penyelesaian, atau catatan penting..."
            value={form.note}
            onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))}
          />
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-800">
            <strong>Efek:</strong> Tiket akan berstatus "Assigned", PIC akan mendapat notifikasi, timer SLA akan mulai
            berjalan.
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowAssign(false)}>
              Batal
            </Button>
            <Button variant="primary" onClick={handleAssign}>
              ✓ Konfirmasi Penugasan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
