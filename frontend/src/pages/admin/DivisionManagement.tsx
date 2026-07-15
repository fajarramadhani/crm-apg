import { useCallback, useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { Button, EmptyState, Input, Modal, PageHeader, SectionCard, Select, Tabs, Toast } from '../../components/ui'
import {
  adminMasterDataService,
  type Application,
  type Division,
  type TicketCategory,
} from '../../services/masterDataService'

type Tab = 'Divisi' | 'Aplikasi' | 'Kategori Tiket'

export default function DivisionManagement() {
  const [tab, setTab] = useState<Tab>('Divisi')
  const [divisions, setDivisions] = useState<Division[]>([])
  const [applications, setApplications] = useState<Application[]>([])
  const [categories, setCategories] = useState<TicketCategory[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [toast, setToast] = useState('')
  const [modal, setModal] = useState<'division' | 'application' | 'category' | 'module' | null>(null)
  const [editingApplication, setEditingApplication] = useState<Application | null>(null)
  const [selectedApplication, setSelectedApplication] = useState<Application | null>(null)
  const [validation, setValidation] = useState<Record<string, string[]>>({})
  const [divisionForm, setDivisionForm] = useState({ code: '', name: '', description: '' })
  const [applicationForm, setApplicationForm] = useState({ code: '', name: '', description: '', owner_division_id: '' })
  const [categoryForm, setCategoryForm] = useState({ code: '', name: '', type: 'incident' })
  const [moduleForm, setModuleForm] = useState({ code: '', name: '', description: '' })

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const [divisionResponse, applicationResponse, categoryResponse] = await Promise.all([
        adminMasterDataService.getDivisions(),
        adminMasterDataService.getApplications(),
        adminMasterDataService.getCategories(),
      ])
      setDivisions(divisionResponse.data)
      setApplications(applicationResponse.data)
      setCategories(categoryResponse.data)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Master data tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const runMutation = async (operation: () => Promise<unknown>, message: string) => {
    setValidation({})
    try {
      await operation()
      setModal(null)
      setEditingApplication(null)
      setToast(message)
      await load()
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        setValidation(caught.errors ?? {})
        setError(`${caught.message}${caught.requestId ? ` (Request ID: ${caught.requestId})` : ''}`)
      } else setError('Perubahan tidak dapat disimpan.')
    }
  }

  const deactivate = async (label: string, operation: () => Promise<unknown>) => {
    if (!window.confirm(`Nonaktifkan ${label}? Data tetap disimpan untuk referensi historis.`)) return
    await runMutation(operation, `${label} dinonaktifkan`)
  }

  const fieldError = (field: string) => validation[field]?.[0]

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}
      <PageHeader
        title="Master Data Organisasi & Tiket"
        subtitle="Kelola divisi, aplikasi, modul, dan kategori dari API Tic Hub"
      />
      <Tabs tabs={['Divisi', 'Aplikasi', 'Kategori Tiket']} active={tab} onChange={(value) => setTab(value as Tab)} />
      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}{' '}
          <button className="font-semibold underline" onClick={() => void load()}>
            Coba lagi
          </button>
        </div>
      )}
      {loading ? (
        <div role="status" className="py-16 text-center text-sm text-gray-500">
          Memuat master data...
        </div>
      ) : (
        <>
          {tab === 'Divisi' && (
            <SectionCard
              title={`Daftar Divisi (${divisions.length})`}
              actions={
                <Button size="sm" variant="primary" onClick={() => setModal('division')}>
                  + Tambah Divisi
                </Button>
              }
            >
              {divisions.length === 0 ? (
                <EmptyState title="Belum ada divisi" message="Tambahkan divisi pertama untuk memulai." />
              ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {divisions.map((item) => (
                    <div
                      key={item.id}
                      className={`rounded-xl border p-3 ${item.is_active ? 'border-gray-200 bg-gray-50' : 'border-gray-100 bg-gray-50 opacity-60'}`}
                    >
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <p className="text-xs font-bold text-[#1E3A8A]">{item.code}</p>
                          <p className="text-sm font-semibold text-gray-900">{item.name}</p>
                          <p className="text-xs text-gray-500">{item.is_active ? 'Aktif' : 'Nonaktif'}</p>
                        </div>
                        {item.is_active && (
                          <button
                            className="text-xs text-red-600"
                            onClick={() =>
                              void deactivate(item.name, () => adminMasterDataService.deactivateDivision(item.id))
                            }
                          >
                            Nonaktifkan
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </SectionCard>
          )}
          {tab === 'Aplikasi' && (
            <SectionCard
              title={`Daftar Aplikasi (${applications.length})`}
              actions={
                <Button
                  size="sm"
                  variant="primary"
                  onClick={() => {
                    setEditingApplication(null)
                    setApplicationForm({ code: '', name: '', description: '', owner_division_id: '' })
                    setModal('application')
                  }}
                >
                  + Tambah Aplikasi
                </Button>
              }
            >
              {applications.length === 0 ? (
                <EmptyState title="Belum ada aplikasi" message="Tambahkan aplikasi pertama untuk memulai." />
              ) : (
                <div className="space-y-3">
                  {applications.map((item) => (
                    <div
                      key={item.id}
                      className={`rounded-xl border p-4 ${item.is_active ? 'border-gray-200' : 'border-gray-100 bg-gray-50 opacity-60'}`}
                    >
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="flex-1">
                          <p className="text-sm font-semibold text-gray-900">
                            {item.name} <span className="text-xs text-gray-400">({item.code})</span>
                          </p>
                          <p className="text-xs text-gray-500">
                            Owner: {item.owner_division?.name ?? 'Belum ditetapkan'} · {item.modules?.length ?? 0} modul
                          </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                          {item.is_active && (
                            <>
                              <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => {
                                  setSelectedApplication(item)
                                  setModuleForm({ code: '', name: '', description: '' })
                                  setModal('module')
                                }}
                              >
                                Tambah Modul
                              </Button>
                              <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => {
                                  setEditingApplication(item)
                                  setApplicationForm({
                                    code: item.code,
                                    name: item.name,
                                    description: item.description ?? '',
                                    owner_division_id: item.owner_division_id?.toString() ?? '',
                                  })
                                  setModal('application')
                                }}
                              >
                                Edit
                              </Button>
                              <button
                                className="text-xs text-red-600"
                                onClick={() =>
                                  void deactivate(item.name, () =>
                                    adminMasterDataService.deactivateApplication(item.id),
                                  )
                                }
                              >
                                Nonaktifkan
                              </button>
                            </>
                          )}
                        </div>
                      </div>
                      {item.modules && item.modules.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2">
                          {item.modules.map((module) => (
                            <span key={module.id} className="rounded-md bg-blue-50 px-2 py-1 text-xs text-blue-700">
                              {module.code} · {module.name}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </SectionCard>
          )}
          {tab === 'Kategori Tiket' && (
            <SectionCard
              title={`Kategori Tiket (${categories.length})`}
              actions={
                <Button size="sm" variant="primary" onClick={() => setModal('category')}>
                  + Tambah Kategori
                </Button>
              }
            >
              {categories.length === 0 ? (
                <EmptyState title="Belum ada kategori" message="Tambahkan kategori tiket pertama." />
              ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {categories.map((item) => (
                    <div
                      key={item.id}
                      className={`flex items-center justify-between rounded-xl border p-3 ${item.is_active ? 'border-gray-200' : 'opacity-60'}`}
                    >
                      <div>
                        <p className="text-sm font-semibold">{item.name}</p>
                        <p className="text-xs text-gray-500">
                          {item.code} · {item.type}
                        </p>
                      </div>
                      {item.is_active && (
                        <button
                          className="text-xs text-red-600"
                          onClick={() =>
                            void deactivate(item.name, () => adminMasterDataService.deactivateCategory(item.id))
                          }
                        >
                          Nonaktifkan
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </SectionCard>
          )}
        </>
      )}

      <Modal open={modal === 'division'} onClose={() => setModal(null)} title="Tambah Divisi">
        <div className="space-y-4">
          <Input
            label="Kode *"
            value={divisionForm.code}
            onChange={(e) => setDivisionForm({ ...divisionForm, code: e.target.value })}
          />
          <Input
            label="Nama *"
            value={divisionForm.name}
            onChange={(e) => setDivisionForm({ ...divisionForm, name: e.target.value })}
          />
          <Input
            label="Deskripsi"
            value={divisionForm.description}
            onChange={(e) => setDivisionForm({ ...divisionForm, description: e.target.value })}
          />
          {(fieldError('code') || fieldError('name')) && (
            <p className="text-xs text-red-600">{fieldError('code') || fieldError('name')}</p>
          )}
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModal(null)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={!divisionForm.code || !divisionForm.name}
              onClick={() =>
                void runMutation(
                  () => adminMasterDataService.createDivision({ ...divisionForm, is_active: true }),
                  'Divisi ditambahkan',
                )
              }
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
      <Modal
        open={modal === 'application'}
        onClose={() => setModal(null)}
        title={editingApplication ? 'Edit Aplikasi' : 'Tambah Aplikasi'}
      >
        <div className="space-y-4">
          <Input
            label="Kode *"
            value={applicationForm.code}
            onChange={(e) => setApplicationForm({ ...applicationForm, code: e.target.value })}
          />
          <Input
            label="Nama *"
            value={applicationForm.name}
            onChange={(e) => setApplicationForm({ ...applicationForm, name: e.target.value })}
          />
          <Select
            label="Owner Divisi"
            value={applicationForm.owner_division_id}
            onChange={(e) => setApplicationForm({ ...applicationForm, owner_division_id: e.target.value })}
            options={[
              { value: '', label: 'Belum ditetapkan' },
              ...divisions.filter((d) => d.is_active).map((d) => ({ value: String(d.id), label: d.name })),
            ]}
          />
          <Input
            label="Deskripsi"
            value={applicationForm.description}
            onChange={(e) => setApplicationForm({ ...applicationForm, description: e.target.value })}
          />
          {(fieldError('code') || fieldError('name')) && (
            <p className="text-xs text-red-600">{fieldError('code') || fieldError('name')}</p>
          )}
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModal(null)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={!applicationForm.code || !applicationForm.name}
              onClick={() =>
                void runMutation(
                  () => {
                    const payload = {
                      ...applicationForm,
                      owner_division_id: applicationForm.owner_division_id
                        ? Number(applicationForm.owner_division_id)
                        : null,
                      is_active: true,
                    }
                    return editingApplication
                      ? adminMasterDataService.updateApplication(editingApplication.id, payload)
                      : adminMasterDataService.createApplication(payload)
                  },
                  editingApplication ? 'Aplikasi diperbarui' : 'Aplikasi ditambahkan',
                )
              }
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
      <Modal open={modal === 'category'} onClose={() => setModal(null)} title="Tambah Kategori Tiket">
        <div className="space-y-4">
          <Input
            label="Kode *"
            value={categoryForm.code}
            onChange={(e) => setCategoryForm({ ...categoryForm, code: e.target.value })}
          />
          <Input
            label="Nama *"
            value={categoryForm.name}
            onChange={(e) => setCategoryForm({ ...categoryForm, name: e.target.value })}
          />
          <Select
            label="Jenis *"
            value={categoryForm.type}
            onChange={(e) => setCategoryForm({ ...categoryForm, type: e.target.value })}
            options={['incident', 'request', 'change', 'problem'].map((value) => ({ value, label: value }))}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModal(null)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={!categoryForm.code || !categoryForm.name}
              onClick={() =>
                void runMutation(
                  () =>
                    adminMasterDataService.createCategory({
                      ...categoryForm,
                      type: categoryForm.type as TicketCategory['type'],
                      is_active: true,
                    }),
                  'Kategori ditambahkan',
                )
              }
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
      <Modal
        open={modal === 'module'}
        onClose={() => setModal(null)}
        title={`Tambah Modul · ${selectedApplication?.name ?? ''}`}
      >
        <div className="space-y-4">
          <Input
            label="Kode *"
            value={moduleForm.code}
            onChange={(e) => setModuleForm({ ...moduleForm, code: e.target.value })}
          />
          <Input
            label="Nama *"
            value={moduleForm.name}
            onChange={(e) => setModuleForm({ ...moduleForm, name: e.target.value })}
          />
          <Input
            label="Deskripsi"
            value={moduleForm.description}
            onChange={(e) => setModuleForm({ ...moduleForm, description: e.target.value })}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setModal(null)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={!moduleForm.code || !moduleForm.name || !selectedApplication}
              onClick={() =>
                selectedApplication &&
                void runMutation(
                  () => adminMasterDataService.createModule(selectedApplication.id, { ...moduleForm, is_active: true }),
                  'Modul ditambahkan',
                )
              }
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
