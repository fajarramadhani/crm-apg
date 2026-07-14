import { useState } from 'react'
import { APPLICATIONS, DIVISIONS } from '../../data'
import { PageHeader, Button, SectionCard, Modal, Input, Toast, Tabs } from '../../components/ui'

export default function DivisionManagement() {
  const [tab, setTab] = useState('Divisi')
  const [toast, setToast] = useState('')
  const [showAddDiv, setShowAddDiv] = useState(false)
  const [showAddApp, setShowAddApp] = useState(false)
  const [newDiv, setNewDiv] = useState('')
  const [newApp, setNewApp] = useState({ name: '', code: '', owner: '', description: '' })

  const handleSaveDiv = () => {
    setToast(`Divisi "${newDiv}" berhasil ditambahkan`)
    setShowAddDiv(false)
    setNewDiv('')
    setTimeout(() => setToast(''), 3000)
  }

  const handleSaveApp = () => {
    setToast(`Aplikasi "${newApp.name}" berhasil ditambahkan`)
    setShowAddApp(false)
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader title="Manajemen Divisi & Aplikasi" subtitle="Kelola struktur organisasi dan daftar aplikasi" />

      <Tabs tabs={['Divisi', 'Aplikasi']} active={tab} onChange={setTab} />

      {tab === 'Divisi' && (
        <SectionCard
          title={`Daftar Divisi (${DIVISIONS.length})`}
          actions={<Button size="sm" variant="primary" onClick={() => setShowAddDiv(true)}>➕ Tambah Divisi</Button>}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {DIVISIONS.map(div => (
              <div key={div} className="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-xl group">
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 bg-[#1E3A8A]/10 rounded-lg flex items-center justify-center">
                    <span className="text-[#1E3A8A] font-bold text-sm">{div[0]}</span>
                  </div>
                  <p className="text-sm font-medium text-gray-800">{div}</p>
                </div>
                <div className="hidden group-hover:flex gap-1">
                  <button className="p-1.5 text-xs text-gray-500 hover:text-[#1E3A8A] hover:bg-blue-50 rounded">✏️</button>
                  <button className="p-1.5 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 rounded">🗑️</button>
                </div>
              </div>
            ))}
          </div>
        </SectionCard>
      )}

      {tab === 'Aplikasi' && (
        <SectionCard
          title={`Daftar Aplikasi (${APPLICATIONS.length})`}
          actions={<Button size="sm" variant="primary" onClick={() => setShowAddApp(true)}>➕ Tambah Aplikasi</Button>}
        >
          <div className="space-y-3">
            {APPLICATIONS.map((app, i) => (
              <div key={app} className="flex items-center gap-4 p-4 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors group">
                <div className="w-10 h-10 bg-[#1E3A8A] rounded-xl flex items-center justify-center shrink-0">
                  <span className="text-white font-bold text-sm">{app.split('(')[0].trim().split(' ').map(w => w[0]).join('').slice(0, 2)}</span>
                </div>
                <div className="flex-1">
                  <p className="text-sm font-semibold text-gray-900">{app}</p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <div className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    <span className="text-xs text-emerald-600">Aktif</span>
                  </div>
                </div>
                <div className="hidden group-hover:flex gap-2">
                  <button className="px-3 py-1 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-white">Edit</button>
                  <button className="px-3 py-1 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50">Nonaktifkan</button>
                </div>
              </div>
            ))}
          </div>
        </SectionCard>
      )}

      {/* Add Division Modal */}
      <Modal open={showAddDiv} onClose={() => setShowAddDiv(false)} title="Tambah Divisi">
        <div className="space-y-4">
          <Input label="Nama Divisi *" value={newDiv} onChange={e => setNewDiv(e.target.value)} placeholder="Nama divisi baru" />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowAddDiv(false)}>Batal</Button>
            <Button variant="primary" onClick={handleSaveDiv} disabled={!newDiv}>Simpan</Button>
          </div>
        </div>
      </Modal>

      {/* Add App Modal */}
      <Modal open={showAddApp} onClose={() => setShowAddApp(false)} title="Tambah Aplikasi">
        <div className="space-y-4">
          <Input label="Nama Aplikasi *" value={newApp.name} onChange={e => setNewApp(a => ({ ...a, name: e.target.value }))} placeholder="Contoh: Sistem Manajemen Klaim" />
          <Input label="Kode Singkat *" value={newApp.code} onChange={e => setNewApp(a => ({ ...a, code: e.target.value }))} placeholder="Contoh: SMK" />
          <Input label="Owner / PIC Aplikasi" value={newApp.owner} onChange={e => setNewApp(a => ({ ...a, owner: e.target.value }))} placeholder="Tim yang bertanggung jawab" />
          <Input label="Deskripsi" value={newApp.description} onChange={e => setNewApp(a => ({ ...a, description: e.target.value }))} placeholder="Deskripsi singkat aplikasi" />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowAddApp(false)}>Batal</Button>
            <Button variant="primary" onClick={handleSaveApp} disabled={!newApp.name || !newApp.code}>Simpan</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
