import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { Button, EmptyState, Input, Modal, PageHeader, SectionCard, Select, Table, TD, Toast, TR } from '../../components/ui'
import { officeService, type Office } from '../../services/officeService'

const emptyForm: { name: string; office_type: Office['office_type'] } = { name: '', office_type: 'cabang' }

export default function OfficeManagement() {
  const [offices, setOffices] = useState<Office[]>([])
  const [filter, setFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [validation, setValidation] = useState<Record<string, string[]>>({})
  const [toast, setToast] = useState('')
  const [editing, setEditing] = useState<Office | null>(null)
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState(emptyForm)

  const load = async () => {
    setLoading(true)
    setError('')
    try {
      setOffices(await officeService.list(filter))
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Daftar kantor tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
  }, [filter])

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setValidation({})
    setError('')
    setModalOpen(true)
  }

  const openEdit = (office: Office) => {
    setEditing(office)
    setForm({ name: office.name, office_type: office.office_type })
    setValidation({})
    setError('')
    setModalOpen(true)
  }

  const save = async () => {
    if (saving) return
    setSaving(true)
    setValidation({})
    setError('')
    try {
      if (editing) await officeService.update(editing.id, form)
      else await officeService.create(form)
      setModalOpen(false)
      setToast(editing ? 'Kantor berhasil diperbarui.' : 'Kantor berhasil ditambahkan.')
      await load()
    } catch (caught) {
      if (caught instanceof ApiRequestError) setValidation(caught.errors ?? {})
      setError(caught instanceof Error ? caught.message : 'Kantor tidak dapat disimpan.')
    } finally {
      setSaving(false)
    }
  }

  const remove = async (office: Office) => {
    if (!window.confirm(`Hapus kantor ${office.name}?`)) return
    try {
      await officeService.remove(office.id)
      setToast('Kantor berhasil dihapus.')
      await load()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Kantor tidak dapat dihapus.')
    }
  }

  return (
    <div>
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}
      <PageHeader
        title="Management Cabang"
        subtitle="Kelola Kantor Pusat dan Kantor Cabang yang dapat dipilih saat membuat akun Requester."
        actions={<Button onClick={openCreate}>+ Tambah Kantor</Button>}
      />
      <div className="mb-4 max-w-xs">
        <Select
          label="Filter kantor"
          value={filter}
          onChange={(event) => setFilter(event.target.value)}
          options={[
            { value: '', label: 'Semua' },
            { value: 'pusat', label: 'Kantor Pusat' },
            { value: 'cabang', label: 'Kantor Cabang' },
          ]}
        />
      </div>
      <SectionCard title={`Daftar kantor (${offices.length})`}>
        {error && !modalOpen && <div role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
        {loading ? (
          <div role="status" className="py-14 text-center text-sm text-gray-500">Memuat kantor...</div>
        ) : offices.length === 0 ? (
          <EmptyState title="Kantor tidak ditemukan" message="Tambahkan kantor atau ubah filter." icon="K" />
        ) : (
          <Table headers={['Nama kantor', 'Tipe kantor', 'Tanggal dibuat', 'Aksi']}>
            {offices.map((office) => (
              <TR key={office.id}>
                <TD><span className="font-semibold text-gray-900">{office.name}</span></TD>
                <TD>{office.office_type === 'pusat' ? 'Kantor Pusat' : 'Kantor Cabang'}</TD>
                <TD className="whitespace-nowrap">{office.created_at ? new Date(office.created_at).toLocaleDateString('id-ID') : '-'}</TD>
                <TD>
                  <div className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => openEdit(office)}>Edit</Button>
                    <Button size="sm" variant="danger" onClick={() => void remove(office)}>Hapus</Button>
                  </div>
                </TD>
              </TR>
            ))}
          </Table>
        )}
      </SectionCard>
      <Modal open={modalOpen} onClose={() => !saving && setModalOpen(false)} title={editing ? 'Edit Kantor' : 'Tambah Kantor'}>
        <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); void save() }}>
          {error && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</div>}
          <Input label="Nama Kantor *" value={form.name} error={validation.name?.[0]} onChange={(event) => setForm({ ...form, name: event.target.value })} />
          <Select
            label="Tipe Kantor *"
            value={form.office_type}
            error={validation.office_type?.[0]}
            onChange={(event) => setForm({ ...form, office_type: event.target.value as Office['office_type'] })}
            options={[{ value: 'pusat', label: 'Kantor Pusat' }, { value: 'cabang', label: 'Kantor Cabang' }]}
          />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" disabled={saving} onClick={() => setModalOpen(false)}>Batal</Button>
            <Button type="submit" loading={saving} disabled={!form.name.trim()}>Simpan Kantor</Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
