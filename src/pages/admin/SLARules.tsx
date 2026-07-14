import { useState } from 'react'
import { SLA_RULES } from '../../data'
import { PageHeader, Button, SectionCard, Modal, Select, Input, Toast } from '../../components/ui'

const CUSTOM_RULES = [
  {
    id: 'cr1',
    name: 'Critical — Production Down',
    category: 'incident',
    priority: 'critical',
    hours: 4,
    notify: ['IT Lead', 'Manager', 'Direktur'],
    active: true,
  },
  {
    id: 'cr2',
    name: 'High — Sistem Klaim',
    category: 'incident',
    priority: 'high',
    app: 'SMK',
    hours: 24,
    notify: ['IT Lead', 'Manager'],
    active: true,
  },
  {
    id: 'cr3',
    name: 'Change Request Standard',
    category: 'change',
    priority: 'medium',
    hours: 240,
    notify: ['IT Lead'],
    active: true,
  },
  {
    id: 'cr4',
    name: 'Low Priority — Non-kritis',
    category: 'request',
    priority: 'low',
    hours: 480,
    notify: [],
    active: false,
  },
]

export default function SLARules() {
  const [showAdd, setShowAdd] = useState(false)
  const [toast, setToast] = useState('')
  const [editId, setEditId] = useState<string | null>(null)
  const [form, setForm] = useState({ name: '', category: 'incident', priority: 'high', hours: '48' })

  const handleSave = () => {
    setToast('Aturan SLA berhasil disimpan')
    setShowAdd(false)
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Aturan SLA"
        subtitle="Kelola aturan Service Level Agreement untuk setiap kategori dan prioritas"
        actions={
          <Button variant="primary" onClick={() => setShowAdd(true)}>
            ➕ Tambah Aturan SLA
          </Button>
        }
      />

      {/* Default SLA Rules */}
      <SectionCard title="SLA Default Berdasarkan Prioritas" className="mb-6">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {SLA_RULES.map((rule) => {
            const colors: Record<string, { bg: string; text: string; icon: string }> = {
              critical: { bg: 'bg-red-50 border-red-200', text: 'text-red-700', icon: '🔴' },
              high: { bg: 'bg-orange-50 border-orange-200', text: 'text-orange-700', icon: '🟠' },
              medium: { bg: 'bg-yellow-50 border-yellow-200', text: 'text-yellow-700', icon: '🟡' },
              low: { bg: 'bg-green-50 border-green-200', text: 'text-green-700', icon: '🟢' },
            }
            const c = colors[rule.priority]
            return (
              <div key={rule.priority} className={`border rounded-xl p-4 ${c.bg}`}>
                <div className="text-2xl mb-2">{c.icon}</div>
                <p className={`text-sm font-bold capitalize ${c.text}`}>{rule.priority}</p>
                <p className={`text-3xl font-black mt-1 ${c.text}`}>{rule.hours}j</p>
                <p className="text-xs text-gray-500 mt-1">SLA Response</p>
                <div className="mt-2 pt-2 border-t border-current/10">
                  <p className="text-xs text-gray-500">
                    {rule.priority === 'critical'
                      ? 'Eskalasi: IT Lead + Manager + Direktur'
                      : rule.priority === 'high'
                        ? 'Eskalasi: IT Lead + Manager'
                        : rule.priority === 'medium'
                          ? 'Eskalasi: IT Lead'
                          : 'Eskalasi: —'}
                  </p>
                </div>
              </div>
            )
          })}
        </div>
      </SectionCard>

      {/* Custom Rules */}
      <SectionCard title="Aturan SLA Khusus">
        <div className="space-y-3">
          {CUSTOM_RULES.map((rule) => (
            <div
              key={rule.id}
              className={`flex items-start gap-4 p-4 border rounded-xl transition-all ${rule.active ? 'border-gray-200 hover:border-[#1E3A8A]/30 hover:bg-gray-50' : 'border-gray-100 bg-gray-50 opacity-60'}`}
            >
              <div
                className={`w-2.5 h-2.5 rounded-full mt-1.5 shrink-0 ${rule.active ? 'bg-emerald-500' : 'bg-gray-300'}`}
              />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <p className="text-sm font-semibold text-gray-900">{rule.name}</p>
                  <span
                    className={`text-xs px-2 py-0.5 rounded-full font-medium ${rule.priority === 'critical' ? 'bg-red-100 text-red-700' : rule.priority === 'high' ? 'bg-orange-100 text-orange-700' : rule.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}
                  >
                    {rule.priority}
                  </span>
                  <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full">{rule.category}</span>
                  {!rule.active && (
                    <span className="text-xs px-2 py-0.5 bg-gray-200 text-gray-500 rounded-full">Nonaktif</span>
                  )}
                </div>
                <div className="flex items-center gap-4 mt-1 text-xs text-gray-500">
                  <span>
                    ⏱️ <strong>{rule.hours} jam</strong> SLA
                  </span>
                  {rule.notify.length > 0 && <span>🔔 Notifikasi: {rule.notify.join(', ')}</span>}
                </div>
              </div>
              <div className="flex gap-1 shrink-0">
                <button
                  onClick={() => setEditId(rule.id)}
                  className="p-1.5 text-xs text-gray-500 hover:text-[#1E3A8A] hover:bg-blue-50 rounded-lg transition-colors"
                >
                  ✏️ Edit
                </button>
                <button className="p-1.5 text-xs text-red-500 hover:bg-red-50 rounded-lg transition-colors">🗑️</button>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* Add/Edit Modal */}
      <Modal
        open={showAdd || editId !== null}
        onClose={() => {
          setShowAdd(false)
          setEditId(null)
        }}
        title={editId ? 'Edit Aturan SLA' : 'Tambah Aturan SLA'}
      >
        <div className="space-y-4">
          <Input
            label="Nama Aturan *"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            placeholder="Contoh: Critical — Production Down"
          />
          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Kategori"
              value={form.category}
              onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}
              options={[
                { value: 'incident', label: 'Incident' },
                { value: 'request', label: 'Request' },
                { value: 'change', label: 'Change' },
                { value: 'problem', label: 'Problem' },
              ]}
            />
            <Select
              label="Prioritas"
              value={form.priority}
              onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))}
              options={[
                { value: 'critical', label: '🔴 Critical' },
                { value: 'high', label: '🟠 High' },
                { value: 'medium', label: '🟡 Medium' },
                { value: 'low', label: '🟢 Low' },
              ]}
            />
          </div>
          <Input
            label="Batas SLA (jam) *"
            type="number"
            value={form.hours}
            onChange={(e) => setForm((f) => ({ ...f, hours: e.target.value }))}
          />
          <div className="flex justify-end gap-2">
            <Button
              variant="secondary"
              onClick={() => {
                setShowAdd(false)
                setEditId(null)
              }}
            >
              Batal
            </Button>
            <Button variant="primary" onClick={handleSave} disabled={!form.name}>
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
