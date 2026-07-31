import { useEffect, useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  EmptyState,
  FilterBar,
  Input,
  Modal,
  PageHeader,
  SectionCard,
  Select,
  Table,
  TD,
  Toast,
  TR,
} from '../../components/ui'
import { useAuth } from '../../context/AuthContext'
import { userService, type AccountOptions, type AccountPayload, type AdminAccount } from '../../services/userService'

const emptyForm = {
  name: '',
  email: '',
  role_id: '',
  division_id: '',
  branch_id: '',
  password: '',
  password_confirmation: '',
  is_active: true,
}

const emptyCreateForm = {
  office_mode: 'pusat' as 'pusat' | 'cabang',
  office_id: '',
  role: 'supervisor_it' as 'supervisor_it' | 'pic_it_develop' | 'pic_it_support',
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
}

export default function UserManagement() {
  const { user: currentUser } = useAuth()
  const [accounts, setAccounts] = useState<AdminAccount[]>([])
  const [options, setOptions] = useState<AccountOptions | null>(null)
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('')
  const [active, setActive] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [validation, setValidation] = useState<Record<string, string[]>>({})
  const [toast, setToast] = useState('')
  const [editing, setEditing] = useState<AdminAccount | null>(null)
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [createForm, setCreateForm] = useState(emptyCreateForm)
  const [createTab, setCreateTab] = useState<'requester' | 'it'>('requester')
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmation, setShowConfirmation] = useState(false)

  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')
    Promise.all([
      userService.list({ search, role, isActive: active, page }),
      options ? Promise.resolve(options) : userService.options(),
    ])
      .then(([response, loadedOptions]) => {
        if (ignore) return
        setAccounts(response.data)
        setTotal(response.meta.pagination.total)
        setLastPage(response.meta.pagination.last_page)
        setOptions(loadedOptions)
      })
      .catch((caught) => {
        if (!ignore) setError(caught instanceof Error ? caught.message : 'Daftar akun tidak dapat dimuat.')
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })
    return () => {
      ignore = true
    }
  }, [active, options, page, role, search])

  const openCreate = () => {
    setEditing(null)
    setValidation({})
    setError('')
    setCreateForm(emptyCreateForm)
    setCreateTab('requester')
    setShowPassword(false)
    setShowConfirmation(false)
    setModalOpen(true)
  }

  const openEdit = (account: AdminAccount) => {
    setEditing(account)
    setValidation({})
    setForm({
      name: account.name,
      email: account.email,
      role_id: String(account.role.id),
      division_id: account.division ? String(account.division.id) : '',
      branch_id: account.branch ? String(account.branch.id) : '',
      password: '',
      password_confirmation: '',
      is_active: account.is_active,
    })
    setModalOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError('')
    setValidation({})
    const payload: AccountPayload = {
      name: form.name,
      email: form.email,
      role_id: Number(form.role_id),
      division_id: form.division_id ? Number(form.division_id) : null,
      branch_id: form.branch_id ? Number(form.branch_id) : null,
      is_active: form.is_active,
      ...(form.password ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
    }
    try {
      if (!editing) return
      await userService.update(editing.id, payload)
      setOptions(await userService.options())
      setModalOpen(false)
      setToast(editing ? 'Akun berhasil diperbarui.' : 'Akun berhasil dibuat.')
      const response = await userService.list({ search, role, isActive: active, page })
      setAccounts(response.data)
      setTotal(response.meta.pagination.total)
      setLastPage(response.meta.pagination.last_page)
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        setValidation(caught.errors ?? {})
        setError(`${caught.message}${caught.requestId ? ` (Request ID: ${caught.requestId})` : ''}`)
      } else setError('Akun tidak dapat disimpan.')
    } finally {
      setSaving(false)
    }
  }

  const saveCreate = async () => {
    if (saving) return
    setSaving(true)
    setError('')
    setValidation({})
    try {
      const common = {
        name: createForm.name,
        email: createForm.email,
        phone: createForm.phone,
        password: createForm.password,
        password_confirmation: createForm.password_confirmation,
      }
      if (createTab === 'requester') {
        await userService.createRequester({
          ...common,
          office_mode: createForm.office_mode,
          office_id: createForm.office_id ? Number(createForm.office_id) : null,
        })
      } else {
        await userService.createIt({ ...common, role: createForm.role })
      }
      setCreateForm(emptyCreateForm)
      setModalOpen(false)
      setToast(createTab === 'requester' ? 'Akun Requester berhasil dibuat.' : 'Akun IT berhasil dibuat.')
      const [response, loadedOptions] = await Promise.all([
        userService.list({ search, role, isActive: active, page }),
        userService.options(),
      ])
      setAccounts(response.data)
      setTotal(response.meta.pagination.total)
      setLastPage(response.meta.pagination.last_page)
      setOptions(loadedOptions)
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        setValidation(caught.errors ?? {})
        setError(`${caught.message}${caught.requestId ? ` (Request ID: ${caught.requestId})` : ''}`)
      } else setError('Akun tidak dapat disimpan.')
    } finally {
      setSaving(false)
    }
  }

  const changeCreateTab = (tab: 'requester' | 'it') => {
    setCreateTab(tab)
    setValidation({})
    setError('')
    setCreateForm(emptyCreateForm)
    setShowPassword(false)
    setShowConfirmation(false)
  }

  const toggleActive = async (account: AdminAccount) => {
    const action = account.is_active ? 'menonaktifkan' : 'mengaktifkan'
    if (!window.confirm(`Yakin ingin ${action} akun ${account.name}?`)) return
    try {
      await userService.update(account.id, {
        name: account.name,
        email: account.email,
        role_id: account.role.id,
        division_id: account.division?.id ?? null,
        branch_id: account.branch?.id ?? null,
        is_active: !account.is_active,
      })
      setOptions(await userService.options())
      setToast(`Akun berhasil ${account.is_active ? 'dinonaktifkan' : 'diaktifkan'}.`)
      setAccounts((items) =>
        items.map((item) => (item.id === account.id ? { ...item, is_active: !item.is_active } : item)),
      )
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Status akun tidak dapat diubah.')
    }
  }

  const removeAccount = async (account: AdminAccount) => {
    if (account.id === currentUser?.id) return
    if (!window.confirm(`Hapus akun ${account.name} (${account.email})? Histori CRM akun tetap disimpan.`)) return
    try {
      await userService.remove(account.id)
      setToast('Akun berhasil dihapus.')
      const response = await userService.list({ search, role, isActive: active, page })
      setAccounts(response.data)
      setTotal(response.meta.pagination.total)
      setLastPage(response.meta.pagination.last_page)
      setOptions(await userService.options())
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Akun tidak dapat dihapus.')
    }
  }

  const removeRole = async (roleId: number, roleName: string) => {
    if (!window.confirm(`Hapus role ${roleName}?`)) return
    try {
      await userService.removeRole(roleId)
      setOptions(await userService.options())
      setToast('Role berhasil dihapus.')
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Role tidak dapat dihapus.')
    }
  }

  const fieldError = (field: string) => validation[field]?.[0]
  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}
      <PageHeader
        title="Akun & Role Workflow"
        subtitle="Kelola akses pengguna dan pastikan setiap tahap tiket memiliki penanggung jawab aktif."
        actions={<Button onClick={openCreate}>+ Tambah Akun</Button>}
      />

      {options && (
        <SectionCard title="Workflow tiket default">
          <p className="mb-4 text-sm text-gray-500">Alur minimum dari pengajuan sampai selesai.</p>
          <div className="grid gap-3 lg:grid-cols-5">
            {options.workflow.map((step, index) => (
              <div
                key={step.stage}
                className="relative rounded-xl border border-blue-100 bg-gradient-to-b from-blue-50 to-white p-4"
              >
                <div className="mb-3 flex items-center justify-between">
                  <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[#1E3A8A] text-xs font-bold text-white">
                    {index + 1}
                  </span>
                  {index < options.workflow.length - 1 && <span className="hidden text-blue-300 lg:block">-&gt;</span>}
                </div>
                <p className="text-sm font-bold text-gray-900">{step.stage}</p>
                <p className="mt-1 text-xs font-semibold text-blue-800">{step.owner_role}</p>
                <p className="mt-2 text-xs leading-relaxed text-gray-500">{step.description}</p>
              </div>
            ))}
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            {options.workflow_role_keys.map((key) => {
              const roleOption = options.roles.find((item) => item.key === key)
              const available = (roleOption?.active_user_count ?? 0) > 0
              return (
                <span
                  key={key}
                  className={`rounded-full px-3 py-1 text-xs font-semibold ${available ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}
                >
                  {roleOption?.name ?? key}: {available ? 'akun aktif tersedia' : 'belum ada akun aktif'}
                </span>
              )
            })}
          </div>
        </SectionCard>
      )}

      {options && (
        <div className="mt-6">
          <SectionCard title="Management Role">
            <p className="mb-4 text-sm text-gray-500">Role Super Admin dilindungi. Role lain hanya dapat dihapus jika tidak digunakan akun atau workflow.</p>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {options.roles.map((roleOption) => (
                <div key={roleOption.id} className="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-gray-900">{roleOption.name}</p>
                    <p className="text-xs text-gray-500">{roleOption.users_count} akun</p>
                  </div>
                  <Button
                    size="sm"
                    variant="danger"
                    disabled={roleOption.key === 'superadmin' || roleOption.users_count > 0}
                    title={roleOption.key === 'superadmin' ? 'Role Super Admin dilindungi' : roleOption.users_count > 0 ? 'Role masih digunakan akun' : 'Hapus role'}
                    onClick={() => void removeRole(roleOption.id, roleOption.name)}
                  >
                    Hapus
                  </Button>
                </div>
              ))}
            </div>
          </SectionCard>
        </div>
      )}

      <div className="mt-6">
        <FilterBar>
          <div className="min-w-52 flex-1">
            <Input
              aria-label="Cari akun"
              placeholder="Cari nama atau email..."
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
            />
          </div>
          <Select
            aria-label="Filter role"
            className="min-w-44"
            value={role}
            onChange={(event) => {
              setRole(event.target.value)
              setPage(1)
            }}
            options={[
              { value: '', label: 'Semua role' },
              ...(options?.roles.map((item) => ({ value: item.key, label: item.name })) ?? []),
            ]}
          />
          <Select
            aria-label="Filter status"
            value={active}
            onChange={(event) => {
              setActive(event.target.value)
              setPage(1)
            }}
            options={[
              { value: '', label: 'Semua status' },
              { value: '1', label: 'Aktif' },
              { value: '0', label: 'Nonaktif' },
            ]}
          />
        </FilterBar>

        <SectionCard title={`Daftar akun (${total})`}>
          {error && (
            <div role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
              {error}
            </div>
          )}
          {loading ? (
            <div className="py-14 text-center text-sm text-gray-500">Memuat akun...</div>
          ) : accounts.length === 0 ? (
            <EmptyState title="Akun tidak ditemukan" message="Ubah filter atau buat akun baru." icon="A" />
          ) : (
            <>
              <Table headers={['Pengguna', 'Role', 'Organisasi', 'Status', 'Login terakhir', 'Aksi']}>
                {accounts.map((account) => (
                  <TR key={account.id}>
                    <TD>
                      <p className="font-semibold text-gray-900">{account.name}</p>
                      <p className="text-xs text-gray-500">{account.email}</p>
                    </TD>
                    <TD>
                      <span
                        className={`rounded-full px-2 py-1 text-xs font-semibold ${options?.workflow_role_keys.includes(account.role.key) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'}`}
                      >
                        {account.role.name}
                      </span>
                    </TD>
                    <TD>
                      <p className="text-xs">{account.division?.name ?? 'Tanpa divisi'}</p>
                      <p className="text-xs text-gray-400">{account.branch?.name ?? 'Tanpa cabang'}</p>
                    </TD>
                    <TD>
                      <span
                        className={`text-xs font-semibold ${account.is_active ? 'text-emerald-700' : 'text-gray-400'}`}
                      >
                        {account.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </TD>
                    <TD className="whitespace-nowrap text-xs">
                      {account.last_login_at ? new Date(account.last_login_at).toLocaleString('id-ID') : 'Belum pernah'}
                    </TD>
                    <TD>
                      <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" onClick={() => openEdit(account)}>
                          Edit
                        </Button>
                        <Button
                          size="sm"
                          variant="danger"
                          disabled={account.id === currentUser?.id}
                          title={account.id === currentUser?.id ? 'Akun sendiri tidak dapat dihapus' : 'Hapus akun'}
                          onClick={() => void removeAccount(account)}
                        >
                          Hapus
                        </Button>
                        <Button
                          size="sm"
                          variant={account.is_active ? 'danger' : 'success'}
                          disabled={account.id === currentUser?.id}
                          onClick={() => void toggleActive(account)}
                        >
                          {account.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </Button>
                      </div>
                    </TD>
                  </TR>
                ))}
              </Table>
              {lastPage > 1 && (
                <div className="mt-4 flex items-center justify-end gap-3">
                  <Button
                    size="sm"
                    variant="secondary"
                    disabled={page <= 1}
                    onClick={() => setPage((value) => value - 1)}
                  >
                    Sebelumnya
                  </Button>
                  <span className="text-xs text-gray-500">
                    Halaman {page} dari {lastPage}
                  </span>
                  <Button
                    size="sm"
                    variant="secondary"
                    disabled={page >= lastPage}
                    onClick={() => setPage((value) => value + 1)}
                  >
                    Berikutnya
                  </Button>
                </div>
              )}
            </>
          )}
        </SectionCard>
      </div>

      <Modal open={modalOpen} onClose={() => !saving && setModalOpen(false)} title={editing ? 'Edit Akun' : 'Buat Akun'}>
        {editing ? <div className="space-y-4">
          {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
          <Input
            label="Nama *"
            value={form.name}
            error={fieldError('name')}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
          />
          <Input
            label="Email *"
            type="email"
            value={form.email}
            error={fieldError('email')}
            onChange={(event) => setForm({ ...form, email: event.target.value })}
          />
          <Select
            label="Role *"
            value={form.role_id}
            error={fieldError('role_id')}
            onChange={(event) => setForm({ ...form, role_id: event.target.value })}
            options={[
              { value: '', label: 'Pilih role' },
              ...(options?.roles.map((item) => ({
                value: String(item.id),
                label: `${item.name}${options.workflow_role_keys.includes(item.key) ? ' - workflow utama' : ''}`,
              })) ?? []),
            ]}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Select
              label="Divisi"
              value={form.division_id}
              error={fieldError('division_id')}
              onChange={(event) => setForm({ ...form, division_id: event.target.value })}
              options={[
                { value: '', label: 'Tanpa divisi' },
                ...(options?.divisions.map((item) => ({
                  value: String(item.id),
                  label: `${item.code} - ${item.name}`,
                })) ?? []),
              ]}
            />
            <Select
              label="Cabang"
              value={form.branch_id}
              error={fieldError('branch_id')}
              onChange={(event) => setForm({ ...form, branch_id: event.target.value })}
              options={[
                { value: '', label: 'Tanpa cabang' },
                ...(options?.branches.map((item) => ({
                  value: String(item.id),
                  label: `${item.code} - ${item.name}`,
                })) ?? []),
              ]}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label={editing ? 'Password baru' : 'Password *'}
              type="password"
              minLength={12}
              hint={editing ? 'Kosongkan jika tidak diubah. Minimal 12 karakter.' : 'Minimal 12 karakter.'}
              error={fieldError('password')}
              value={form.password}
              onChange={(event) => setForm({ ...form, password: event.target.value })}
            />
            <Input
              label={editing ? 'Konfirmasi password baru' : 'Konfirmasi password *'}
              type="password"
              value={form.password_confirmation}
              onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
            />
          </div>
          {editing && (
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={form.is_active}
                disabled={editing.id === currentUser?.id}
                onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
              />{' '}
              Akun aktif
            </label>
          )}
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Batal
            </Button>
            <Button
              loading={saving}
              disabled={
                !form.name ||
                !form.email ||
                !form.role_id ||
                (!editing && (!form.password || form.password !== form.password_confirmation))
              }
              onClick={() => void save()}
            >
              Simpan Akun
            </Button>
          </div>
        </div> : (
          <div>
            <div role="tablist" aria-label="Jenis akun" className="mb-6 grid grid-cols-2 rounded-xl bg-gray-100 p-1">
              {([
                ['requester', 'Buat Akun Requester'],
                ['it', 'Buat Akun IT'],
              ] as const).map(([value, label]) => (
                <button
                  key={value}
                  id={`account-tab-${value}`}
                  type="button"
                  role="tab"
                  aria-selected={createTab === value}
                  aria-controls={`account-panel-${value}`}
                  tabIndex={createTab === value ? 0 : -1}
                  className={`rounded-lg px-2 py-2.5 text-xs font-semibold transition sm:text-sm ${createTab === value ? 'bg-white text-blue-900 shadow-sm ring-1 ring-gray-200' : 'text-gray-600 hover:text-gray-900'}`}
                  onClick={() => changeCreateTab(value)}
                  onKeyDown={(event) => {
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') changeCreateTab(value === 'requester' ? 'it' : 'requester')
                  }}
                >
                  {label}
                </button>
              ))}
            </div>
            <form
              id={`account-panel-${createTab}`}
              role="tabpanel"
              aria-labelledby={`account-tab-${createTab}`}
              className="space-y-4"
              onSubmit={(event) => { event.preventDefault(); void saveCreate() }}
            >
              {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
              {createTab === 'requester' ? (
                <fieldset>
                  <legend className="mb-2 text-sm font-medium text-gray-700">Pilih Kantor Pusat atau Kantor Cabang?</legend>
                  <div className="grid grid-cols-2 gap-2">
                    {(['pusat', 'cabang'] as const).map((mode) => (
                      <label key={mode} className={`cursor-pointer rounded-lg border p-3 text-center text-sm font-semibold ${createForm.office_mode === mode ? 'border-blue-800 bg-blue-50 text-blue-900' : 'border-gray-300 text-gray-600'}`}>
                        <input
                          className="sr-only"
                          type="radio"
                          name="office_mode"
                          value={mode}
                          checked={createForm.office_mode === mode}
                          onChange={() => setCreateForm({ ...createForm, office_mode: mode, office_id: '' })}
                        />
                        {mode === 'pusat' ? 'Kantor Pusat' : 'Kantor Cabang'}
                      </label>
                    ))}
                  </div>
                  {validation.office_mode?.[0] && <p className="mt-1 text-xs text-red-600">{validation.office_mode[0]}</p>}
                </fieldset>
              ) : (
                <Select
                  label="Role *"
                  value={createForm.role}
                  error={fieldError('role')}
                  onChange={(event) => setCreateForm({ ...createForm, role: event.target.value as typeof createForm.role })}
                  options={[
                    { value: 'supervisor_it', label: 'Supervisor' },
                    { value: 'pic_it_develop', label: 'IT Developer' },
                    { value: 'pic_it_support', label: 'IT Support' },
                  ]}
                />
              )}
              {createTab === 'requester' && createForm.office_mode === 'cabang' && (
                <Select
                  label="Pilih Cabang *"
                  value={createForm.office_id}
                  disabled={!options}
                  error={fieldError('office_id')}
                  onChange={(event) => setCreateForm({ ...createForm, office_id: event.target.value })}
                  options={[
                    { value: '', label: options ? (options.offices.filter((office) => office.office_type === 'cabang').length ? 'Pilih cabang' : 'Belum ada Kantor Cabang') : 'Memuat cabang...' },
                    ...(options?.offices.filter((office) => office.office_type === 'cabang').map((office) => ({ value: String(office.id), label: office.name })) ?? []),
                  ]}
                />
              )}
              <Input label="Nama *" value={createForm.name} error={fieldError('name')} onChange={(event) => setCreateForm({ ...createForm, name: event.target.value })} />
              <Input label="Email *" type="email" value={createForm.email} error={fieldError('email')} onChange={(event) => setCreateForm({ ...createForm, email: event.target.value })} />
              <Input label="No. Telp *" type="tel" value={createForm.phone} error={fieldError('phone')} onChange={(event) => setCreateForm({ ...createForm, phone: event.target.value })} />
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="relative">
                  <Input className="pr-11" label="Password *" type={showPassword ? 'text' : 'password'} minLength={12} value={createForm.password} error={fieldError('password')} onChange={(event) => setCreateForm({ ...createForm, password: event.target.value })} />
                  <button type="button" aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'} aria-pressed={showPassword} className="absolute right-2 top-7 flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-800 focus-visible:ring-offset-1" onClick={() => setShowPassword((value) => !value)}>
                    {showPassword ? <EyeOff aria-hidden="true" className="h-5 w-5" /> : <Eye aria-hidden="true" className="h-5 w-5" />}
                  </button>
                </div>
                <div className="relative">
                  <Input className="pr-11" label="Konfirmasi Password *" type={showConfirmation ? 'text' : 'password'} value={createForm.password_confirmation} error={fieldError('password_confirmation')} onChange={(event) => setCreateForm({ ...createForm, password_confirmation: event.target.value })} />
                  <button type="button" aria-label={showConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'} aria-pressed={showConfirmation} className="absolute right-2 top-7 flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-800 focus-visible:ring-offset-1" onClick={() => setShowConfirmation((value) => !value)}>
                    {showConfirmation ? <EyeOff aria-hidden="true" className="h-5 w-5" /> : <Eye aria-hidden="true" className="h-5 w-5" />}
                  </button>
                </div>
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="secondary" disabled={saving} onClick={() => setModalOpen(false)}>Batal</Button>
                <Button
                  type="submit"
                  loading={saving}
                  disabled={!createForm.name.trim() || !createForm.email.trim() || !createForm.phone.trim() || !createForm.password || createForm.password !== createForm.password_confirmation || (createTab === 'requester' && createForm.office_mode === 'cabang' && !createForm.office_id)}
                >
                  {createTab === 'requester' ? 'Buat Akun Requester' : 'Buat Akun IT'}
                </Button>
              </div>
            </form>
          </div>
        )}
      </Modal>
    </div>
  )
}
