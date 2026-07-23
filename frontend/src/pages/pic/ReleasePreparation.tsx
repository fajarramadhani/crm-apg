import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { Button, EmptyState, Input, PageHeader, SectionCard, Textarea, Toast } from '../../components/ui'
import { ticketService, type ReleasePreparationRecord, type TicketRecord } from '../../services/ticketService'

export default function ReleasePreparationPIC() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [detail, setDetail] = useState<ReleasePreparationRecord | null>(null)
  const [summary, setSummary] = useState('')
  const [technical, setTechnical] = useState('')
  const [component, setComponent] = useState('')
  const [rollbackTrigger, setRollbackTrigger] = useState('')
  const [rollbackSteps, setRollbackSteps] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    ticketService
      .picAssignments({ per_page: 50 })
      .then((response) => {
        const items = response.data.filter((item) => item.status === 'release_preparation')
        setTickets(items)
        setSelected(items[0] || null)
      })
      .catch((cause) => setError((cause as ApiRequestError).message))
  }, [])
  useEffect(() => {
    if (selected)
      ticketService
        .picReleasePreparation(selected.id)
        .then(setDetail)
        .catch((cause) => setError((cause as ApiRequestError).message))
  }, [selected])
  const act = (work: () => Promise<unknown>, message: string) => {
    setBusy(true)
    setError('')
    work()
      .then(() => setSuccess(message))
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
  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      {success && <Toast type="success" message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Release Preparation Workspace"
        subtitle="Lengkapi rencana teknis dan rollback untuk tiket assignment Anda"
      />
      {tickets.length === 0 ? (
        <EmptyState title="Belum ada assignment" message="Tiket pada tahap release preparation akan muncul di sini." />
      ) : (
        <div className="grid gap-5 lg:grid-cols-4">
          <div className="space-y-2">
            {tickets.map((ticket) => (
              <button
                key={ticket.id}
                className={`w-full rounded-xl border p-3 text-left ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'bg-white'}`}
                onClick={() => setSelected(ticket)}
              >
                <b className="font-mono text-xs">{ticket.ticket_number}</b>
                <p className="text-sm font-semibold">{ticket.title}</p>
              </button>
            ))}
          </div>
          {detail && (
            <div className="space-y-5 lg:col-span-3">
              <SectionCard title="Technical Release Plan">
                <div className="space-y-3">
                  <Input label="Change summary" value={summary} onChange={(e) => setSummary(e.target.value)} />
                  <Textarea
                    label="Technical summary"
                    rows={4}
                    value={technical}
                    onChange={(e) => setTechnical(e.target.value)}
                  />
                  <Input label="Affected component" value={component} onChange={(e) => setComponent(e.target.value)} />
                  <Button
                    loading={busy}
                    onClick={() =>
                      act(
                        () =>
                          ticketService.createPicReleasePlan(selected!.id, {
                            release_owner_id: selected!.assignee?.id,
                            release_type: 'normal',
                            target_environment: 'production',
                            change_summary: summary,
                            technical_summary: technical,
                            affected_components: [component],
                            dependencies: [],
                            database_changes: '',
                            data_migration_required: false,
                            downtime_required: false,
                            estimated_downtime_minutes: 0,
                            proposed_start_at: new Date(Date.now() + 86400000).toISOString(),
                            estimated_duration_minutes: 60,
                            validation_steps: ['Validate smoke test'],
                            monitoring_plan: ['Review health metrics'],
                            communication_notes: '',
                          }),
                        'Release plan tersimpan.',
                      )
                    }
                  >
                    Simpan Plan
                  </Button>
                </div>
              </SectionCard>
              <SectionCard title="Rollback Plan">
                <div className="space-y-3">
                  <Textarea
                    label="Rollback trigger"
                    rows={3}
                    value={rollbackTrigger}
                    onChange={(e) => setRollbackTrigger(e.target.value)}
                  />
                  <Textarea
                    label="Rollback steps"
                    rows={4}
                    value={rollbackSteps}
                    onChange={(e) => setRollbackSteps(e.target.value)}
                  />
                  <Button
                    loading={busy}
                    onClick={() =>
                      act(
                        () =>
                          ticketService.createPicRollbackPlan(selected!.id, {
                            rollback_trigger: rollbackTrigger,
                            rollback_steps: [rollbackSteps],
                            data_recovery_steps: [],
                            estimated_rollback_minutes: 30,
                            validation_after_rollback: ['Validate service health'],
                            responsible_user_id: selected!.assignee?.id,
                          }),
                        'Rollback plan tersimpan.',
                      )
                    }
                  >
                    Simpan Rollback
                  </Button>
                </div>
              </SectionCard>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
