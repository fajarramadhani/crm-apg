import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { workflowService, type Workflow } from '../../../services/workflowService'
import { Button, PageHeader, SectionCard, Toast } from '../../../components/ui'
import { ApiRequestError } from '../../../api/client'

export default function WorkflowList() {
  const [workflows, setWorkflows] = useState<Workflow[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const response = await workflowService.getWorkflows()
      setWorkflows(response.workflows)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Daftar workflow tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const handleAction = async (
    id: number,
    action: 'publish' | 'activate' | 'deactivate' | 'createVersion' | 'delete',
  ) => {
    if (action === 'delete' && !window.confirm('Apakah Anda yakin ingin menghapus workflow ini?')) {
      return
    }

    try {
      let msg = ''
      if (action === 'publish') {
        await workflowService.publishWorkflow(id)
        msg = 'Workflow berhasil dipublikasikan.'
      } else if (action === 'activate') {
        await workflowService.activateWorkflow(id)
        msg = 'Workflow berhasil diaktifkan.'
      } else if (action === 'deactivate') {
        await workflowService.deactivateWorkflow(id)
        msg = 'Workflow berhasil dinonaktifkan.'
      } else if (action === 'createVersion') {
        await workflowService.createVersion(id)
        msg = 'Versi baru berhasil dibuat sebagai draft.'
      } else if (action === 'delete') {
        await workflowService.deleteWorkflow(id)
        msg = 'Workflow berhasil dihapus.'
      }

      setToast({ message: msg, type: 'success' })
      void load()
    } catch (caught) {
      const errMsg = caught instanceof ApiRequestError ? caught.message : 'Gagal memproses aksi.'
      setToast({ message: errMsg, type: 'error' })
    }
  }

  return (
    <div>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="flex justify-between items-center mb-6">
        <PageHeader
          title="Workflow Configuration"
          subtitle="Kelola lifecycle, stages, dan transitions dynamic workflow engine"
        />
        <Link to="/admin/workflows/new">
          <Button variant="primary">➕ Buat Workflow Baru</Button>
        </Link>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}{' '}
          <button className="font-semibold underline" onClick={() => void load()}>
            Coba lagi
          </button>
        </div>
      )}

      <SectionCard title="Daftar Workflow Master">
        {loading ? (
          <div className="py-12 text-center text-gray-500">Memuat workflow...</div>
        ) : workflows.length === 0 ? (
          <div className="py-12 text-center text-gray-500">Belum ada workflow yang dibuat.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left text-gray-500">
              <thead className="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                  <th className="px-6 py-3">Code</th>
                  <th className="px-6 py-3">Nama</th>
                  <th className="px-6 py-3">Versi</th>
                  <th className="px-6 py-3">Status</th>
                  <th className="px-6 py-3">Stages</th>
                  <th className="px-6 py-3">Transitions</th>
                  <th className="px-6 py-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {workflows.map((wf) => (
                  <tr key={wf.id} className="bg-white hover:bg-gray-50">
                    <td className="px-6 py-4 font-mono font-semibold text-gray-900">{wf.code}</td>
                    <td className="px-6 py-4">{wf.name}</td>
                    <td className="px-6 py-4">v{wf.version}</td>
                    <td className="px-6 py-4">
                      <span
                        className={`px-2.5 py-1 rounded-full text-xs font-semibold ${
                          wf.config_status === 'active'
                            ? 'bg-green-100 text-green-800'
                            : wf.config_status === 'published'
                              ? 'bg-blue-100 text-blue-800'
                              : wf.config_status === 'draft'
                                ? 'bg-yellow-100 text-yellow-800'
                                : 'bg-gray-100 text-gray-800'
                        }`}
                      >
                        {wf.config_status.toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4">{wf.stages_count ?? 0}</td>
                    <td className="px-6 py-4">{wf.transitions_count ?? 0}</td>
                    <td className="px-6 py-4 text-right space-x-2">
                      <Link to={`/admin/workflows/${wf.id}`}>
                        <Button variant="secondary" size="sm">
                          Detail & Edit
                        </Button>
                      </Link>

                      {wf.config_status === 'draft' && (
                        <>
                          <Button variant="success" size="sm" onClick={() => handleAction(wf.id, 'publish')}>
                            Publish
                          </Button>
                          <Button variant="danger" size="sm" onClick={() => handleAction(wf.id, 'delete')}>
                            Hapus
                          </Button>
                        </>
                      )}

                      {wf.config_status === 'published' && (
                        <>
                          <Button variant="primary" size="sm" onClick={() => handleAction(wf.id, 'activate')}>
                            Aktifkan
                          </Button>
                          <Button variant="secondary" size="sm" onClick={() => handleAction(wf.id, 'createVersion')}>
                            Buat Versi Baru
                          </Button>
                        </>
                      )}

                      {wf.config_status === 'active' && (
                        <>
                          <Button variant="danger" size="sm" onClick={() => handleAction(wf.id, 'deactivate')}>
                            Nonaktifkan
                          </Button>
                          <Button variant="secondary" size="sm" onClick={() => handleAction(wf.id, 'createVersion')}>
                            Buat Versi Baru
                          </Button>
                        </>
                      )}

                      {wf.config_status === 'inactive' && (
                        <>
                          <Button variant="primary" size="sm" onClick={() => handleAction(wf.id, 'activate')}>
                            Aktifkan
                          </Button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </SectionCard>
    </div>
  )
}
