import { FormEvent, useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  workflowService,
  type Workflow,
  type WorkflowApproval,
  type WorkflowApprovalPayload,
  type WorkflowFieldPayload,
  type WorkflowStage,
  type WorkflowStagePayload,
  type WorkflowTransition,
  type WorkflowTransitionNotification,
  type WorkflowTransitionPayload,
} from '../../../services/workflowService'
import { Button, Input, Modal, PageHeader, SectionCard, Select, Textarea, Toast } from '../../../components/ui'
import { ApiRequestError } from '../../../api/client'

const ROLE_OPTIONS = [
  { value: 'requester', label: 'Requester' },
  { value: 'supervisor_it', label: 'Supervisor IT' },
  { value: 'pic_it_support', label: 'PIC IT Support' },
  { value: 'pic_it_develop', label: 'PIC IT Development' },
  { value: 'manager', label: 'Manager' },
  { value: 'admin', label: 'Admin' },
]

const RECIPIENT_OPTIONS: Array<{ value: WorkflowTransitionNotification['recipient_type']; label: string }> = [
  { value: 'requester', label: 'Requester tiket' },
  { value: 'primary_pic', label: 'PIC utama' },
  { value: 'secondary_pics', label: 'Seluruh PIC sekunder' },
  { value: 'supervisor_it', label: 'Supervisor IT' },
]

const emptyStage: WorkflowStagePayload = {
  stage_key: '',
  name: '',
  description: null,
  order: 0,
  stage_type: 'normal',
  is_initial: false,
  is_terminal: false,
}

const emptyField: WorkflowFieldPayload = {
  field_name: '',
  is_required: false,
  is_readonly: false,
  is_hidden: false,
}

const emptyTransition: WorkflowTransitionPayload = {
  from_stage_id: 0,
  to_stage_id: 0,
  action_key: '',
  name: '',
  requires_notes: false,
  permissions: [{ role_key: 'supervisor_it' }],
  notifications: [],
}

function errorMessage(caught: unknown, fallback: string) {
  return caught instanceof ApiRequestError ? caught.message : fallback
}

export default function WorkflowDetail() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [workflow, setWorkflow] = useState<Workflow | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null)
  const [stageForm, setStageForm] = useState<WorkflowStagePayload | null>(null)
  const [editingStageId, setEditingStageId] = useState<number | null>(null)
  const [fieldStage, setFieldStage] = useState<WorkflowStage | null>(null)
  const [fieldForm, setFieldForm] = useState<WorkflowFieldPayload>(emptyField)
  const [transitionForm, setTransitionForm] = useState<WorkflowTransitionPayload | null>(null)
  const [editingTransitionId, setEditingTransitionId] = useState<number | null>(null)
  const [approval, setApproval] = useState<WorkflowApproval | null>(null)
  const [approvalForm, setApprovalForm] = useState<WorkflowApprovalPayload | null>(null)

  const load = useCallback(async () => {
    if (!id) return
    setLoading(true)
    setError('')
    try {
      const [workflowDetail, approvalConfig] = await Promise.all([
        workflowService.getWorkflow(id),
        workflowService.getApproval(id),
      ])
      setWorkflow(workflowDetail)
      setApproval(approvalConfig)
    } catch (caught) {
      setError(errorMessage(caught, 'Gagal memuat workflow detail.'))
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    void load()
  }, [load])

  const run = async (action: () => Promise<unknown>, success: string) => {
    setBusy(true)
    setError('')
    try {
      await action()
      setToast({ message: success, type: 'success' })
      await load()
      return true
    } catch (caught) {
      const message = errorMessage(caught, 'Aksi tidak dapat diproses.')
      setError(message)
      setToast({ message, type: 'error' })
      return false
    } finally {
      setBusy(false)
    }
  }

  const handleValidate = async () => {
    if (!id) return
    setBusy(true)
    setError('')
    try {
      const result = await workflowService.validateWorkflow(id)
      if (!result.valid) {
        setError(`Validasi gagal: ${result.errors.join(', ')}`)
        setToast({ message: 'Workflow belum valid.', type: 'error' })
        return
      }
      setToast({ message: 'Validasi sukses. Workflow ini valid.', type: 'success' })
    } catch (caught) {
      setError(errorMessage(caught, 'Gagal memvalidasi workflow.'))
    } finally {
      setBusy(false)
    }
  }

  const handleLifecycle = async (action: 'publish' | 'activate' | 'deactivate' | 'createVersion') => {
    if (!id) return
    if (action === 'deactivate' && !window.confirm('Nonaktifkan workflow ini untuk tiket baru?')) return
    setBusy(true)
    setError('')
    try {
      if (action === 'createVersion') {
        const created = await workflowService.createVersion(id)
        navigate(`/admin/workflows/${created.id}`)
        return
      }
      if (action === 'publish') await workflowService.publishWorkflow(id)
      if (action === 'activate') await workflowService.activateWorkflow(id)
      if (action === 'deactivate') await workflowService.deactivateWorkflow(id)
      setToast({
        message:
          action === 'publish'
            ? 'Workflow berhasil dipublikasikan.'
            : action === 'activate'
              ? 'Workflow berhasil diaktifkan.'
              : 'Workflow berhasil dinonaktifkan.',
        type: 'success',
      })
      await load()
    } catch (caught) {
      setError(errorMessage(caught, 'Lifecycle workflow tidak dapat diproses.'))
    } finally {
      setBusy(false)
    }
  }

  const openStage = (stage?: WorkflowStage) => {
    setEditingStageId(stage?.id ?? null)
    setStageForm(
      stage
        ? {
            stage_key: stage.stage_key,
            name: stage.name,
            description: stage.description,
            order: stage.order,
            stage_type: stage.stage_type,
            is_initial: stage.is_initial,
            is_terminal: stage.is_terminal,
          }
        : { ...emptyStage, order: (workflow?.stages?.length ?? 0) + 1 },
    )
  }

  const saveStage = async (event: FormEvent) => {
    event.preventDefault()
    if (!id || !stageForm) return
    const ok = await run(
      () =>
        editingStageId
          ? workflowService.updateStage(id, editingStageId, {
              name: stageForm.name,
              description: stageForm.description,
              order: stageForm.order,
              stage_type: stageForm.stage_type,
              is_initial: stageForm.is_initial,
              is_terminal: stageForm.is_terminal,
            })
          : workflowService.createStage(id, stageForm),
      editingStageId ? 'Stage berhasil diperbarui.' : 'Stage berhasil ditambahkan.',
    )
    if (ok) setStageForm(null)
  }

  const removeStage = async (stage: WorkflowStage) => {
    if (!id || !window.confirm(`Hapus stage "${stage.name}"? Transition dan konfigurasi terkait dapat ikut terhapus.`))
      return
    await run(() => workflowService.deleteStage(id, stage.id), 'Stage berhasil dihapus.')
  }

  const openField = (stage: WorkflowStage) => {
    setFieldStage(stage)
    setFieldForm(emptyField)
  }

  const saveField = async (event: FormEvent) => {
    event.preventDefault()
    if (!id || !fieldStage) return
    const ok = await run(
      () => workflowService.createField(id, fieldStage.id, fieldForm),
      'Internal field berhasil ditambahkan.',
    )
    if (ok) setFieldStage(null)
  }

  const removeField = async (stage: WorkflowStage, fieldId: number, fieldName: string) => {
    if (!id || !window.confirm(`Hapus internal field "${fieldName}"?`)) return
    await run(() => workflowService.deleteField(id, stage.id, fieldId), 'Internal field berhasil dihapus.')
  }

  const openTransition = (transition?: WorkflowTransition) => {
    if (transition?.notifications?.some((notification) => notification.recipient_type === 'specific_role')) {
      setError(
        'Transition ini menggunakan recipient specific_role yang belum didukung parameter role tujuan oleh backend, sehingga ditampilkan read-only untuk mencegah perubahan penerima yang tidak aman.',
      )
      return
    }
    setEditingTransitionId(transition?.id ?? null)
    setTransitionForm(
      transition
        ? {
            from_stage_id: transition.from_stage_id,
            to_stage_id: transition.to_stage_id,
            action_key: transition.action_key,
            name: transition.name,
            requires_notes: transition.requires_notes,
            permissions: (transition.permissions ?? [])
              .map((permission) => ({
                role_key: permission.role_key ?? '',
              }))
              .filter((permission) => permission.role_key),
            notifications: (transition.notifications ?? []).map((notification) => ({
              recipient_type: notification.recipient_type,
              channel: notification.channel,
              template_code: notification.template_code,
            })),
          }
        : {
            ...emptyTransition,
            from_stage_id: workflow?.stages?.[0]?.id ?? 0,
            to_stage_id: workflow?.stages?.[1]?.id ?? workflow?.stages?.[0]?.id ?? 0,
            permissions: [{ role_key: 'supervisor_it' }],
            notifications: [],
          },
    )
  }

  const saveTransition = async (event: FormEvent) => {
    event.preventDefault()
    if (!id || !transitionForm) return
    if (transitionForm.permissions.some((permission) => !permission.role_key)) {
      setError('Setiap permission transition harus memiliki role yang eksplisit.')
      return
    }
    const ok = await run(
      () =>
        editingTransitionId
          ? workflowService.updateTransition(id, editingTransitionId, {
              name: transitionForm.name,
              requires_notes: transitionForm.requires_notes,
              permissions: transitionForm.permissions,
              notifications: transitionForm.notifications,
            })
          : workflowService.createTransition(id, transitionForm),
      editingTransitionId ? 'Transition berhasil diperbarui.' : 'Transition berhasil ditambahkan.',
    )
    if (ok) setTransitionForm(null)
  }

  const removeTransition = async (transition: WorkflowTransition) => {
    if (!id || !window.confirm(`Hapus transition "${transition.name}"?`)) return
    await run(() => workflowService.deleteTransition(id, transition.id), 'Transition berhasil dihapus.')
  }

  const openApproval = () => {
    const approvalStage = workflow?.stages?.find((stage) => stage.stage_type === 'approval')
    if (!approvalStage) {
      setError('Tambahkan satu stage bertipe approval sebelum mengatur approval.')
      return
    }

    setApprovalForm({
      stage_id: approval?.stage_id ?? approvalStage.id,
      label: approval?.label ?? 'Supervisor IT Approval',
      notes_required: approval?.notes_required ?? false,
      is_active: approval?.is_active ?? true,
    })
  }

  const saveApproval = async (event: FormEvent) => {
    event.preventDefault()
    if (!id || !approvalForm) return
    const ok = await run(
      () => workflowService.updateApproval(id, approvalForm),
      'Konfigurasi approval berhasil disimpan.',
    )
    if (ok) setApprovalForm(null)
  }

  if (loading) return <div className="p-8 text-center text-gray-500">Memuat detail...</div>
  if (error && !workflow) return <div className="p-8 text-red-600 bg-red-50 rounded-lg">{error}</div>
  if (!workflow) return null

  const isDraft = workflow.config_status === 'draft'
  const stages = workflow.stages ?? []
  const transitions = workflow.transitions ?? []

  return (
    <div>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <Link to="/admin/workflows" className="mb-2 inline-block text-sm text-blue-600 hover:underline">
            &larr; Kembali ke Daftar Workflow
          </Link>
          <PageHeader
            title={`Workflow: ${workflow.name} (v${workflow.version})`}
            subtitle={`Kode: ${workflow.code} | Status: ${workflow.config_status}`}
          />
        </div>
        <div className="flex flex-wrap gap-2 lg:justify-end">
          <Button variant="secondary" onClick={handleValidate} disabled={busy}>
            Validasi
          </Button>
          {isDraft && (
            <Link to={`/admin/workflows/${workflow.id}/edit`}>
              <Button variant="primary">Edit Info</Button>
            </Link>
          )}
          {isDraft && (
            <Button variant="success" onClick={() => handleLifecycle('publish')} disabled={busy}>
              Publikasikan
            </Button>
          )}
          {(workflow.config_status === 'published' || workflow.config_status === 'inactive') && (
            <Button variant="primary" onClick={() => handleLifecycle('activate')} disabled={busy}>
              Aktifkan
            </Button>
          )}
          {workflow.config_status === 'active' && (
            <Button variant="danger" onClick={() => handleLifecycle('deactivate')} disabled={busy}>
              Nonaktifkan
            </Button>
          )}
          {!isDraft && (
            <Button variant="secondary" onClick={() => handleLifecycle('createVersion')} disabled={busy}>
              Buat Versi Baru
            </Button>
          )}
        </div>
      </div>

      {!isDraft && (
        <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-900">
          Perubahan hanya berlaku untuk tiket baru. Tiket yang sudah berjalan tetap menggunakan versi sebelumnya.
        </div>
      )}

      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6">
        <SectionCard title="Stages">
          <div className="mb-4 flex items-center justify-between gap-3">
            <p className="text-sm text-gray-500">
              Susun status tiket dan internal field yang tersedia pada setiap stage.
            </p>
            {isDraft && (
              <Button size="sm" onClick={() => openStage()}>
                Tambah Stage
              </Button>
            )}
          </div>
          <div className="space-y-3">
            {stages.map((stage) => (
              <div key={stage.id} className="rounded-xl border border-gray-200 bg-white p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-xs font-semibold text-gray-400">#{stage.order}</span>
                      <h3 className="font-semibold text-gray-900">{stage.name}</h3>
                      <span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-700">
                        {stage.stage_key}
                      </span>
                      <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">
                        {stage.stage_type}
                      </span>
                      {stage.is_initial && (
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Initial</span>
                      )}
                      {stage.is_terminal && (
                        <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Terminal</span>
                      )}
                    </div>
                    {stage.description && <p className="mt-1 text-sm text-gray-500">{stage.description}</p>}
                  </div>
                  {isDraft && (
                    <div className="flex shrink-0 flex-wrap gap-2">
                      <Button variant="secondary" size="sm" onClick={() => openField(stage)}>
                        Tambah Field
                      </Button>
                      <Button variant="secondary" size="sm" onClick={() => openStage(stage)}>
                        Edit
                      </Button>
                      <Button variant="danger" size="sm" onClick={() => removeStage(stage)} disabled={busy}>
                        Hapus
                      </Button>
                    </div>
                  )}
                </div>
                <div className="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                  {(stage.fields ?? []).map((field) => (
                    <span
                      key={field.id}
                      className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-700"
                    >
                      <span className="font-mono">{field.field_name}</span>
                      {field.is_required && <span className="text-red-600">required</span>}
                      {field.is_readonly && <span className="text-blue-600">readonly</span>}
                      {field.is_hidden && <span className="text-gray-500">hidden</span>}
                      {isDraft && (
                        <button
                          type="button"
                          className="ml-1 font-bold text-red-500 hover:text-red-700"
                          aria-label={`Hapus field ${field.field_name}`}
                          onClick={() => removeField(stage, field.id, field.field_name)}
                        >
                          &times;
                        </button>
                      )}
                    </span>
                  ))}
                  {!stage.fields?.length && <span className="text-xs text-gray-400">Belum ada internal field.</span>}
                </div>
              </div>
            ))}
            {!stages.length && <div className="py-8 text-center text-sm text-gray-400">Belum ada stage.</div>}
          </div>
        </SectionCard>

        <SectionCard title="Transitions">
          <div className="mb-4 flex items-center justify-between gap-3">
            <p className="text-sm text-gray-500">Setiap transition wajib memiliki role permission eksplisit.</p>
            {isDraft && (
              <Button size="sm" onClick={() => openTransition()} disabled={stages.length < 2}>
                Tambah Transition
              </Button>
            )}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-sm text-gray-500">
              <thead className="bg-gray-50 text-xs uppercase text-gray-700">
                <tr>
                  <th className="px-4 py-2">Action</th>
                  <th className="px-4 py-2">Alur</th>
                  <th className="px-4 py-2">Permissions</th>
                  <th className="px-4 py-2">Notifikasi</th>
                  <th className="px-4 py-2 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {transitions.map((transition) => {
                  const from =
                    stages.find((stage) => stage.id === transition.from_stage_id)?.name ?? transition.from_stage_id
                  const to = stages.find((stage) => stage.id === transition.to_stage_id)?.name ?? transition.to_stage_id
                  return (
                    <tr key={transition.id}>
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-900">{transition.name}</div>
                        <div className="font-mono text-xs">{transition.action_key}</div>
                        {transition.requires_notes && <div className="mt-1 text-xs text-amber-700">Catatan wajib</div>}
                      </td>
                      <td className="px-4 py-3 text-gray-700">
                        {from} &rarr; {to}
                      </td>
                      <td className="px-4 py-3">
                        {transition.permissions
                          ?.map((permission) => permission.role_key ?? permission.permission_code)
                          .filter(Boolean)
                          .join(', ') || <span className="text-red-600">Belum diatur</span>}
                      </td>
                      <td className="px-4 py-3">
                        {transition.notifications
                          ?.map((notification) => `${notification.recipient_type} (${notification.channel})`)
                          .join(', ') || '-'}
                      </td>
                      <td className="px-4 py-3 text-right">
                        {isDraft && (
                          <div className="flex justify-end gap-2">
                            <Button variant="secondary" size="sm" onClick={() => openTransition(transition)}>
                              Edit
                            </Button>
                            <Button
                              variant="danger"
                              size="sm"
                              onClick={() => removeTransition(transition)}
                              disabled={busy}
                            >
                              Hapus
                            </Button>
                          </div>
                        )}
                      </td>
                    </tr>
                  )
                })}
                {!transitions.length && (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                      Belum ada transition.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </SectionCard>

        <SectionCard title="Approval Configuration">
          <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-500">
              Satu approval step dengan approver tetap Supervisor IT. Konfigurasi published bersifat read-only.
            </p>
            {isDraft && (
              <Button size="sm" onClick={openApproval}>
                {approval ? 'Edit Approval' : 'Atur Approval'}
              </Button>
            )}
          </div>
          {approval ? (
            <div className="rounded-xl border border-gray-200 bg-white p-4">
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-semibold text-gray-900">{approval.label}</h3>
                <span
                  className={`rounded-full px-2 py-0.5 text-xs ${approval.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}
                >
                  {approval.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>
              <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                <div>
                  <dt className="text-gray-500">Stage</dt>
                  <dd className="font-medium text-gray-900">
                    {stages.find((stage) => stage.id === approval.stage_id)?.name ?? `#${approval.stage_id}`}
                  </dd>
                </div>
                <div>
                  <dt className="text-gray-500">Approver</dt>
                  <dd className="font-mono text-gray-900">{approval.approver_role_key}</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Catatan approve</dt>
                  <dd className="font-medium text-gray-900">{approval.notes_required ? 'Wajib' : 'Opsional'}</dd>
                </div>
              </dl>
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-400">
              Belum ada konfigurasi approval.
            </div>
          )}
        </SectionCard>
      </div>

      <Modal
        open={stageForm !== null}
        onClose={() => setStageForm(null)}
        title={editingStageId ? 'Edit Stage' : 'Tambah Stage'}
        size="lg"
      >
        {stageForm && (
          <form onSubmit={saveStage} className="space-y-4 p-6">
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label="Stage key"
                value={stageForm.stage_key}
                onChange={(event) => setStageForm({ ...stageForm, stage_key: event.target.value.toLowerCase() })}
                pattern="[a-z0-9_]+"
                disabled={editingStageId !== null}
                required
              />
              <Input
                label="Nama stage"
                value={stageForm.name}
                onChange={(event) => setStageForm({ ...stageForm, name: event.target.value })}
                required
              />
              <Input
                label="Urutan"
                type="number"
                min="0"
                value={stageForm.order}
                onChange={(event) => setStageForm({ ...stageForm, order: Number(event.target.value) })}
                required
              />
              <Select
                label="Tipe stage"
                value={stageForm.stage_type}
                onChange={(event) =>
                  setStageForm({ ...stageForm, stage_type: event.target.value as WorkflowStagePayload['stage_type'] })
                }
                options={[
                  { value: 'normal', label: 'Normal' },
                  { value: 'special', label: 'Special' },
                  { value: 'approval', label: 'Approval' },
                ]}
              />
            </div>
            <Textarea
              label="Deskripsi"
              rows={3}
              value={stageForm.description ?? ''}
              onChange={(event) => setStageForm({ ...stageForm, description: event.target.value || null })}
            />
            <div className="flex flex-wrap gap-5 text-sm text-gray-700">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={stageForm.is_initial}
                  onChange={(event) => setStageForm({ ...stageForm, is_initial: event.target.checked })}
                />{' '}
                Stage awal
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={stageForm.is_terminal}
                  onChange={(event) => setStageForm({ ...stageForm, is_terminal: event.target.checked })}
                />{' '}
                Stage terminal
              </label>
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setStageForm(null)}>
                Batal
              </Button>
              <Button type="submit" loading={busy}>
                Simpan Stage
              </Button>
            </div>
          </form>
        )}
      </Modal>

      <Modal
        open={fieldStage !== null}
        onClose={() => setFieldStage(null)}
        title={`Tambah Internal Field${fieldStage ? ` - ${fieldStage.name}` : ''}`}
      >
        <form onSubmit={saveField} className="space-y-4 p-6">
          <Input
            label="Field name"
            value={fieldForm.field_name}
            onChange={(event) => setFieldForm({ ...fieldForm, field_name: event.target.value.toLowerCase() })}
            pattern="[a-z0-9_]+"
            hint="Gunakan huruf kecil, angka, dan underscore."
            required
          />
          <div className="space-y-3 text-sm text-gray-700">
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={fieldForm.is_required}
                onChange={(event) => setFieldForm({ ...fieldForm, is_required: event.target.checked })}
              />{' '}
              Wajib diisi
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={fieldForm.is_readonly}
                onChange={(event) => setFieldForm({ ...fieldForm, is_readonly: event.target.checked })}
              />{' '}
              Read-only
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={fieldForm.is_hidden}
                onChange={(event) => setFieldForm({ ...fieldForm, is_hidden: event.target.checked })}
              />{' '}
              Disembunyikan
            </label>
          </div>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setFieldStage(null)}>
              Batal
            </Button>
            <Button type="submit" loading={busy}>
              Tambah Field
            </Button>
          </div>
        </form>
      </Modal>

      <Modal
        open={transitionForm !== null}
        onClose={() => setTransitionForm(null)}
        title={editingTransitionId ? 'Edit Transition' : 'Tambah Transition'}
        size="xl"
      >
        {transitionForm && (
          <form onSubmit={saveTransition} className="space-y-5 p-6">
            <div className="grid gap-4 sm:grid-cols-2">
              <Select
                label="Dari stage"
                value={String(transitionForm.from_stage_id)}
                disabled={editingTransitionId !== null}
                onChange={(event) =>
                  setTransitionForm({ ...transitionForm, from_stage_id: Number(event.target.value) })
                }
                options={stages.map((stage) => ({ value: String(stage.id), label: stage.name }))}
              />
              <Select
                label="Ke stage"
                value={String(transitionForm.to_stage_id)}
                disabled={editingTransitionId !== null}
                onChange={(event) => setTransitionForm({ ...transitionForm, to_stage_id: Number(event.target.value) })}
                options={stages.map((stage) => ({ value: String(stage.id), label: stage.name }))}
              />
              <Input
                label="Action key"
                value={transitionForm.action_key}
                disabled={editingTransitionId !== null}
                onChange={(event) =>
                  setTransitionForm({ ...transitionForm, action_key: event.target.value.toLowerCase() })
                }
                pattern="[a-z0-9_]+"
                required
              />
              <Input
                label="Nama aksi"
                value={transitionForm.name}
                onChange={(event) => setTransitionForm({ ...transitionForm, name: event.target.value })}
                required
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input
                type="checkbox"
                checked={transitionForm.requires_notes}
                onChange={(event) => setTransitionForm({ ...transitionForm, requires_notes: event.target.checked })}
              />{' '}
              Catatan wajib saat transition dijalankan
            </label>

            <div className="rounded-xl border border-gray-200 p-4">
              <div className="mb-3 flex items-center justify-between">
                <div>
                  <h3 className="font-semibold text-gray-900">Role Permissions</h3>
                  <p className="text-xs text-gray-500">Minimal satu role eksplisit wajib dipilih.</p>
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() =>
                    setTransitionForm({
                      ...transitionForm,
                      permissions: [...transitionForm.permissions, { role_key: 'supervisor_it' }],
                    })
                  }
                >
                  Tambah Role
                </Button>
              </div>
              <div className="space-y-2">
                {transitionForm.permissions.map((permission, index) => (
                  <div key={index} className="flex gap-2">
                    <Select
                      aria-label={`Role permission ${index + 1}`}
                      value={permission.role_key}
                      onChange={(event) =>
                        setTransitionForm({
                          ...transitionForm,
                          permissions: transitionForm.permissions.map((item, itemIndex) =>
                            itemIndex === index ? { role_key: event.target.value } : item,
                          ),
                        })
                      }
                      options={ROLE_OPTIONS}
                    />
                    <Button
                      type="button"
                      variant="danger"
                      size="sm"
                      disabled={transitionForm.permissions.length === 1}
                      onClick={() =>
                        setTransitionForm({
                          ...transitionForm,
                          permissions: transitionForm.permissions.filter((_, itemIndex) => itemIndex !== index),
                        })
                      }
                    >
                      Hapus
                    </Button>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-xl border border-gray-200 p-4">
              <div className="mb-3 flex items-center justify-between">
                <div>
                  <h3 className="font-semibold text-gray-900">Notification Recipients</h3>
                  <p className="text-xs text-gray-500">
                    Hanya penerima yang dapat ditentukan aman oleh backend yang tersedia.
                  </p>
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() =>
                    setTransitionForm({
                      ...transitionForm,
                      notifications: [
                        ...transitionForm.notifications,
                        { recipient_type: 'requester', channel: 'database', template_code: null },
                      ],
                    })
                  }
                >
                  Tambah Penerima
                </Button>
              </div>
              <div className="space-y-3">
                {transitionForm.notifications.map((notification, index) => (
                  <div key={index} className="grid gap-2 rounded-lg bg-gray-50 p-3 sm:grid-cols-[1fr_140px_1fr_auto]">
                    <Select
                      aria-label={`Penerima notifikasi ${index + 1}`}
                      value={notification.recipient_type}
                      onChange={(event) =>
                        setTransitionForm({
                          ...transitionForm,
                          notifications: transitionForm.notifications.map((item, itemIndex) =>
                            itemIndex === index
                              ? {
                                  ...item,
                                  recipient_type: event.target
                                    .value as WorkflowTransitionNotification['recipient_type'],
                                }
                              : item,
                          ),
                        })
                      }
                      options={RECIPIENT_OPTIONS}
                    />
                    <Select
                      aria-label={`Channel notifikasi ${index + 1}`}
                      value={notification.channel}
                      onChange={(event) =>
                        setTransitionForm({
                          ...transitionForm,
                          notifications: transitionForm.notifications.map((item, itemIndex) =>
                            itemIndex === index
                              ? { ...item, channel: event.target.value as 'database' | 'email' }
                              : item,
                          ),
                        })
                      }
                      options={[
                        { value: 'database', label: 'Database' },
                        { value: 'email', label: 'Email' },
                      ]}
                    />
                    <Input
                      aria-label={`Template code ${index + 1}`}
                      placeholder="Template code (opsional)"
                      value={notification.template_code ?? ''}
                      onChange={(event) =>
                        setTransitionForm({
                          ...transitionForm,
                          notifications: transitionForm.notifications.map((item, itemIndex) =>
                            itemIndex === index ? { ...item, template_code: event.target.value || null } : item,
                          ),
                        })
                      }
                    />
                    <Button
                      type="button"
                      variant="danger"
                      size="sm"
                      onClick={() =>
                        setTransitionForm({
                          ...transitionForm,
                          notifications: transitionForm.notifications.filter((_, itemIndex) => itemIndex !== index),
                        })
                      }
                    >
                      Hapus
                    </Button>
                  </div>
                ))}
                {!transitionForm.notifications.length && (
                  <p className="text-sm text-gray-400">Tidak ada notifikasi untuk transition ini.</p>
                )}
              </div>
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setTransitionForm(null)}>
                Batal
              </Button>
              <Button type="submit" loading={busy}>
                Simpan Transition
              </Button>
            </div>
          </form>
        )}
      </Modal>

      <Modal open={approvalForm !== null} onClose={() => setApprovalForm(null)} title="Approval Configuration">
        {approvalForm && (
          <form onSubmit={saveApproval} className="space-y-4 p-6">
            <Select
              label="Approval stage"
              value={String(approvalForm.stage_id)}
              onChange={(event) => setApprovalForm({ ...approvalForm, stage_id: Number(event.target.value) })}
              options={stages
                .filter((stage) => stage.stage_type === 'approval')
                .map((stage) => ({ value: String(stage.id), label: stage.name }))}
            />
            <Input
              label="Label"
              value={approvalForm.label}
              maxLength={150}
              onChange={(event) => setApprovalForm({ ...approvalForm, label: event.target.value })}
              required
            />
            <div className="rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900">
              Approver: <span className="font-mono font-semibold">supervisor_it</span> (tetap)
            </div>
            <div className="space-y-3 text-sm text-gray-700">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={approvalForm.notes_required}
                  onChange={(event) => setApprovalForm({ ...approvalForm, notes_required: event.target.checked })}
                />{' '}
                Catatan wajib saat approve
              </label>
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={approvalForm.is_active}
                  onChange={(event) => setApprovalForm({ ...approvalForm, is_active: event.target.checked })}
                />{' '}
                Konfigurasi aktif
              </label>
            </div>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setApprovalForm(null)}>
                Batal
              </Button>
              <Button type="submit" loading={busy}>
                Simpan Approval
              </Button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  )
}
