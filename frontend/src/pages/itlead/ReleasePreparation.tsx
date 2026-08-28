import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { useAuth } from '../../context/AuthContext'
import { Button, EmptyState, Input, PageHeader, SectionCard, StatusBadge, Textarea, Toast } from '../../components/ui'
import { Check } from 'lucide-react'
import {
  ticketService,
  type ApprovalRequestRecord,
  type ReleasePreparationRecord,
  type TicketRecord,
} from '../../services/ticketService'

const planDefaults = (ownerId: number) => ({
  release_owner_id: ownerId,
  release_type: 'normal',
  target_environment: 'production',
  change_summary: '',
  technical_summary: '',
  affected_components: [''],
  dependencies: [],
  database_changes: '',
  data_migration_required: false,
  downtime_required: false,
  estimated_downtime_minutes: 0,
  proposed_start_at: '',
  estimated_duration_minutes: 60,
  validation_steps: [''],
  monitoring_plan: [''],
  communication_notes: '',
})

export default function ReleasePreparation() {
  const { user } = useAuth()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [technical, setTechnical] = useState<ApprovalRequestRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [detail, setDetail] = useState<ReleasePreparationRecord | null>(null)
  const [plan, setPlan] = useState<Record<string, unknown>>(planDefaults(user?.id || 0))
  const [rollback, setRollback] = useState({
    rollback_trigger: '',
    rollback_steps: [''],
    data_recovery_steps: [''],
    estimated_rollback_minutes: 30,
    validation_after_rollback: [''],
    responsible_user_id: user?.id || 0,
  })
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const load = () => {
    setLoading(true)
    Promise.all([ticketService.releaseApprovalQueue({ per_page: 50 }), ticketService.technicalApprovalQueue()])
      .then(([queue, tech]) => {
        setTickets(queue.data)
        setTechnical(tech)
        setSelected((current) => current || queue.data[0] || null)
      })
      .catch((cause) => setError((cause as ApiRequestError).message))
      .finally(() => setLoading(false))
  }
  useEffect(load, [])
  useEffect(() => {
    if (selected && ['release_preparation', 'approval_pending'].includes(selected.status))
      ticketService
        .releasePreparation(selected.id)
        .then(setDetail)
        .catch((cause) => setError((cause as ApiRequestError).message))
  }, [selected])

  const act = (work: () => Promise<unknown>, message: string) => {
    setBusy(true)
    setError('')
    work()
      .then(() => {
        setSuccess(message)
        load()
      })
      .catch((cause) => {
        const apiErr = cause as ApiRequestError
        if (apiErr.errors) {
          const detailed = Object.values(apiErr.errors).flat().join(' | ')
          setError(`Validasi Gagal: ${detailed}`)
        } else {
          setError(apiErr.message || 'Gagal menyimpan data.')
        }
      })
      .finally(() => setBusy(false))
  }
  const requestApproval = () =>
    selected &&
    act(
      () =>
        ticketService.requestReleaseApproval(selected.id, {
          summary: 'Release plan telah disiapkan untuk persetujuan bisnis dan teknis.',
          release_risk_level: 'medium',
          proposed_release_at: new Date(Date.now() + 86400000).toISOString(),
        }),
      'Release approval diminta.',
    )
  const savePlan = () =>
    selected &&
    act(
      () =>
        ticketService.createReleasePlan(selected.id, {
          ...plan,
          affected_components: (plan.affected_components as string[]).filter(Boolean),
          validation_steps: (plan.validation_steps as string[]).filter(Boolean),
          monitoring_plan: (plan.monitoring_plan as string[]).filter(Boolean),
        }),
      'Release plan dibuat.',
    )
  const saveRollback = () =>
    selected &&
    act(
      () =>
        ticketService.createRollbackPlan(selected.id, {
          ...rollback,
          rollback_steps: rollback.rollback_steps.filter(Boolean),
          data_recovery_steps: rollback.data_recovery_steps.filter(Boolean),
          validation_after_rollback: rollback.validation_after_rollback.filter(Boolean),
        }),
      'Rollback plan dibuat.',
    )

  const confirmReady = () =>
    selected &&
    act(
      () => ticketService.confirmReleaseReady(selected.id),
      'Rilis berhasil dikonfirmasi SIAP (Release Ready). Tiket berpindah ke antrean Deployment.',
    )

  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      {success && <Toast type="success" message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Approval & Release Preparation"
        subtitle="Persetujuan bisnis, technical readiness, dan persiapan rilis tanpa deployment"
      />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat release queue...</p>
      ) : (
        <div className="space-y-5">
          <SectionCard title="Release Queue & Approval">
            <div className="grid gap-3 md:grid-cols-2">
              {tickets.length === 0 ? (
                <EmptyState title="Queue kosong" message="Belum ada tiket dalam proses rilis." />
              ) : (
                tickets.map((ticket) => (
                  <button
                    key={ticket.id}
                    className={`rounded-xl border p-3 text-left ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'bg-white'}`}
                    onClick={() => setSelected(ticket)}
                  >
                    <b className="font-mono text-xs">{ticket.ticket_number}</b>
                    <p className="text-sm font-semibold">{ticket.title}</p>
                    <StatusBadge status={ticket.status} />
                  </button>
                ))
              )}
            </div>
            {selected?.status === 'uat_approved' && (
              <Button className="mt-4" loading={busy} onClick={requestApproval}>
                Request Release Approval
              </Button>
            )}
          </SectionCard>
          <SectionCard title="Technical Approval Queue">
            <div className="space-y-2">
              {technical.map((item) => (
                <div
                  key={item.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 p-3 text-sm"
                >
                  <span>
                    <b>{item.ticket?.ticket_number}</b> · {item.summary}
                  </span>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="success"
                      onClick={() =>
                        item.ticket &&
                        act(
                          () =>
                            ticketService.technicalApproval(item.ticket!.id, 'approve', {
                              expected_version:
                                item.steps.find((step) => step.step_type === 'technical_readiness')?.version || 1,
                            }),
                          'Technical readiness approved.',
                        )
                      }
                    >
                      Approve
                    </Button>
                    <Button
                      size="sm"
                      variant="danger"
                      onClick={() =>
                        item.ticket &&
                        act(
                          () =>
                            ticketService.technicalApproval(item.ticket!.id, 'reject', {
                              notes: 'Technical plan requires revision.',
                              expected_version:
                                item.steps.find((step) => step.step_type === 'technical_readiness')?.version || 1,
                            }),
                          'Technical readiness rejected.',
                        )
                      }
                    >
                      Reject
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </SectionCard>
          {detail?.ticket.status === 'release_preparation' && (
            <>
              <div className="grid gap-5 lg:grid-cols-2">
                <SectionCard title="Release Plan">
                  <div className="space-y-3">
                    <Input
                      label="Change summary"
                      value={String(plan.change_summary)}
                      onChange={(e) => setPlan({ ...plan, change_summary: e.target.value })}
                    />
                    <Textarea
                      label="Technical summary"
                      rows={3}
                      value={String(plan.technical_summary)}
                      onChange={(e) => setPlan({ ...plan, technical_summary: e.target.value })}
                    />
                    <Input
                      label="Affected component"
                      value={String((plan.affected_components as string[])[0] || '')}
                      onChange={(e) => setPlan({ ...plan, affected_components: [e.target.value] })}
                    />
                    <Textarea
                      label="Validation step"
                      rows={2}
                      value={String((plan.validation_steps as string[])[0] || '')}
                      onChange={(e) => setPlan({ ...plan, validation_steps: [e.target.value] })}
                    />
                    <Textarea
                      label="Monitoring plan"
                      rows={2}
                      value={String((plan.monitoring_plan as string[])[0] || '')}
                      onChange={(e) => setPlan({ ...plan, monitoring_plan: [e.target.value] })}
                    />
                    <Button loading={busy} onClick={savePlan}>
                      Simpan Release Plan
                    </Button>
                  </div>
                </SectionCard>
                <SectionCard title="Rollback Plan">
                  <div className="space-y-3">
                    <Textarea
                      label="Rollback trigger"
                      rows={2}
                      value={rollback.rollback_trigger}
                      onChange={(e) => setRollback({ ...rollback, rollback_trigger: e.target.value })}
                    />
                    <Textarea
                      label="Rollback steps"
                      rows={3}
                      value={rollback.rollback_steps[0]}
                      onChange={(e) => setRollback({ ...rollback, rollback_steps: [e.target.value] })}
                    />
                    <Textarea
                      label="Validation after rollback"
                      rows={2}
                      value={rollback.validation_after_rollback[0]}
                      onChange={(e) => setRollback({ ...rollback, validation_after_rollback: [e.target.value] })}
                    />
                    <Button loading={busy} onClick={saveRollback}>
                      Simpan Rollback Plan
                    </Button>
                  </div>
                </SectionCard>
              </div>
              <div className="flex justify-end p-2 bg-blue-50 border border-blue-200 rounded-xl">
                <Button variant="success" size="lg" loading={busy} onClick={confirmReady}>
                  <Check className="w-5 h-5 shrink-0" /> Konfirmasi Rilis Siap (Confirm Release Ready)
                </Button>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  )
}
