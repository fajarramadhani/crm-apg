import { useEffect, useState, FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { workflowService } from '../../../services/workflowService'
import { Button, Input, PageHeader, SectionCard, Toast } from '../../../components/ui'
import { ApiRequestError } from '../../../api/client'

export default function WorkflowForm() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [form, setForm] = useState({ code: '', name: '', description: '', version: 1 })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [validation, setValidation] = useState<Record<string, string[]>>({})
  const [toast, setToast] = useState('')

  useEffect(() => {
    if (id) {
      setLoading(true)
      workflowService
        .getWorkflow(id)
        .then((wf) => {
          if (wf.config_status !== 'draft') {
            navigate(`/admin/workflows/${wf.id}`, { replace: true })
            return
          }
          setForm({
            code: wf.code,
            name: wf.name,
            description: wf.description ?? '',
            version: wf.version,
          })
        })
        .catch((caught) => {
          setError(caught instanceof Error ? caught.message : 'Gagal memuat workflow.')
        })
        .finally(() => {
          setLoading(false)
        })
    }
  }, [id, navigate])

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    setValidation({})

    try {
      if (id) {
        await workflowService.updateWorkflow(id, {
          name: form.name,
          description: form.description,
        })
        setToast('Workflow info berhasil diperbarui.')
        setTimeout(() => navigate(`/admin/workflows/${id}`), 1000)
      } else {
        const wf = await workflowService.createWorkflow({
          code: form.code,
          name: form.name,
          description: form.description,
          version: form.version,
        })
        setToast('Workflow baru berhasil dibuat.')
        setTimeout(() => navigate(`/admin/workflows/${wf.id}`), 1000)
      }
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        setValidation(caught.errors ?? {})
        setError(caught.message)
      } else {
        setError('Gagal menyimpan workflow.')
      }
    } finally {
      setLoading(false)
    }
  }

  const fieldError = (field: string) => validation[field]?.[0]

  return (
    <div className="max-w-2xl mx-auto">
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title={id ? 'Edit Workflow' : 'Buat Workflow Baru'}
        subtitle={id ? 'Perbarui informasi dasar workflow draft' : 'Buat metadata workflow draft baru'}
      />

      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <SectionCard title="Metadata Informasi">
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">Code Workflow (Unique)</label>
            <Input
              name="code"
              value={form.code}
              onChange={(e) => setForm({ ...form, code: e.target.value })}
              disabled={!!id}
              placeholder="e.g. default_it_workflow"
              required
            />
            {fieldError('code') && <p className="mt-1 text-xs text-red-600">{fieldError('code')}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">Nama Workflow</label>
            <Input
              name="name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="e.g. IT support & Development Workflow"
              required
            />
            {fieldError('name') && <p className="mt-1 text-xs text-red-600">{fieldError('name')}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
            <textarea
              name="description"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              className="w-full rounded-lg border border-gray-300 p-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50"
              placeholder="Jelaskan tujuan workflow ini..."
              rows={4}
            />
            {fieldError('description') && <p className="mt-1 text-xs text-red-600">{fieldError('description')}</p>}
          </div>

          {!id && (
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">Versi Awal</label>
              <Input
                name="version"
                type="number"
                value={String(form.version)}
                onChange={(e) => setForm({ ...form, version: parseInt(e.target.value) || 1 })}
                min="1"
                required
              />
              {fieldError('version') && <p className="mt-1 text-xs text-red-600">{fieldError('version')}</p>}
            </div>
          )}

          <div className="flex justify-end gap-3 pt-4">
            <Button variant="secondary" onClick={() => navigate('/admin/workflows')} disabled={loading}>
              Batal
            </Button>
            <Button variant="primary" type="submit" disabled={loading}>
              {loading ? 'Menyimpan...' : 'Simpan Workflow'}
            </Button>
          </div>
        </form>
      </SectionCard>
    </div>
  )
}
