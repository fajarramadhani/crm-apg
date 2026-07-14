import { useState } from 'react'
import { USERS, ROLE_LABELS, DIVISIONS } from '../../data'
import { PageHeader, Button, Table, TR, TD, Avatar, Modal, Input, Select, Toast } from '../../components/ui'

export default function UserManagement() {
  const [showAdd, setShowAdd] = useState(false)
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [toast, setToast] = useState('')
  const [form, setForm] = useState({ name: '', email: '', role: 'user', division: 'IT' })

  const update = (f: string, v: string) => setForm((prev) => ({ ...prev, [f]: v }))

  const filtered = USERS.filter((u) => {
    const matchSearch =
      !search ||
      u.name.toLowerCase().includes(search.toLowerCase()) ||
      u.email.toLowerCase().includes(search.toLowerCase())
    const matchRole = !roleFilter || u.role === roleFilter
    return matchSearch && matchRole
  })

  const handleSave = () => {
    setToast('User baru berhasil ditambahkan')
    setShowAdd(false)
    setTimeout(() => setToast(''), 3000)
  }

  const roleColors: Record<string, string> = {
    user: 'bg-gray-100 text-gray-600',
    supervisor: 'bg-purple-100 text-purple-700',
    itlead: 'bg-blue-100 text-blue-700',
    pic: 'bg-teal-100 text-teal-700',
    qa: 'bg-cyan-100 text-cyan-700',
    manager: 'bg-amber-100 text-amber-700',
    executive: 'bg-indigo-100 text-indigo-700',
    admin: 'bg-red-100 text-red-700',
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Manajemen User & Role"
        subtitle={`${USERS.length} user terdaftar`}
        actions={
          <Button variant="primary" onClick={() => setShowAdd(true)}>
            ➕ Tambah User
          </Button>
        }
      />

      {/* Filter */}
      <div className="flex gap-3 mb-4 flex-wrap">
        <input
          placeholder="🔍 Cari nama atau email..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="px-3 py-2 text-sm border border-gray-300 rounded-lg w-56 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
        />
        <select
          value={roleFilter}
          onChange={(e) => setRoleFilter(e.target.value)}
          className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
        >
          <option value="">Semua Role</option>
          {Object.entries(ROLE_LABELS).map(([v, l]) => (
            <option key={v} value={v}>
              {l}
            </option>
          ))}
        </select>
        <span className="ml-auto text-xs text-gray-400 self-center">{filtered.length} user</span>
      </div>

      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <Table headers={['User', 'Email', 'Role', 'Divisi', 'Status', 'Aksi']}>
          {filtered.map((u) => (
            <TR key={u.id}>
              <TD>
                <div className="flex items-center gap-2.5">
                  <Avatar initials={u.avatar} size="sm" />
                  <span className="font-medium text-sm text-gray-900">{u.name}</span>
                </div>
              </TD>
              <TD>
                <span className="text-xs text-gray-500">{u.email}</span>
              </TD>
              <TD>
                <span
                  className={`px-2 py-0.5 rounded-full text-xs font-medium ${roleColors[u.role] || 'bg-gray-100 text-gray-600'}`}
                >
                  {ROLE_LABELS[u.role]}
                </span>
              </TD>
              <TD>
                <span className="text-xs text-gray-600">{u.division}</span>
              </TD>
              <TD>
                <div className="flex items-center gap-1.5">
                  <div className="w-2 h-2 bg-emerald-500 rounded-full" />
                  <span className="text-xs text-emerald-600">Aktif</span>
                </div>
              </TD>
              <TD>
                <div className="flex gap-1">
                  <button className="px-2.5 py-1 text-xs text-[#1E3A8A] border border-[#1E3A8A]/30 rounded-lg hover:bg-blue-50 transition-colors">
                    Edit
                  </button>
                  <button className="px-2.5 py-1 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                    Nonaktifkan
                  </button>
                </div>
              </TD>
            </TR>
          ))}
        </Table>
      </div>

      {/* Add Modal */}
      <Modal open={showAdd} onClose={() => setShowAdd(false)} title="Tambah User Baru">
        <div className="space-y-4">
          <Input
            label="Nama Lengkap *"
            value={form.name}
            onChange={(e) => update('name', e.target.value)}
            placeholder="Nama lengkap user"
          />
          <Input
            label="Email *"
            type="email"
            value={form.email}
            onChange={(e) => update('email', e.target.value)}
            placeholder="nama@apg.co.id"
          />
          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Role *"
              value={form.role}
              onChange={(e) => update('role', e.target.value)}
              options={Object.entries(ROLE_LABELS).map(([v, l]) => ({ value: v, label: l }))}
            />
            <Select
              label="Divisi *"
              value={form.division}
              onChange={(e) => update('division', e.target.value)}
              options={DIVISIONS.map((d) => ({ value: d, label: d }))}
            />
          </div>
          <Input label="Password Sementara" type="password" placeholder="Min. 8 karakter" />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowAdd(false)}>
              Batal
            </Button>
            <Button variant="primary" onClick={handleSave} disabled={!form.name || !form.email}>
              Simpan User
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
