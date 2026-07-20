import { useState, useEffect } from 'react'
import { ticketService, type TicketRecord, type TicketDeploymentStepRecord } from '../../services/ticketService'
import { PageHeader, SectionCard, Button, Toast, EmptyState, StatusBadge, Input } from '../../components/ui'

export default function DeploymentQueue() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [scheduleData, setScheduleData] = useState({ scheduled_start_at: '' })

  const load = async () => {
    setLoading(true)
    try {
      // Deployment queue expects release_ready, deployment_scheduled, deployment_in_progress, deployed, monitoring, post_release_issue, awaiting_requester_confirmation
      const res = await ticketService.deploymentQueue()
      if (res.data) setTickets(res.data)
      if (selected) {
        const detailRes = await ticketService.itLeadGet(selected.id)
        if (detailRes) setSelected(detailRes)
      }
    } catch {
      setError('Gagal memuat queue')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const act = async (fn: () => Promise<any>, msg: string) => {
    setBusy(true)
    try {
      await fn()
      setSuccess(msg)
      await load()
    } catch (err: any) {
      setError(err.response?.data?.message || 'Aksi gagal')
    } finally {
      setBusy(false)
    }
  }

  const handleSchedule = () =>
    selected && act(() => ticketService.scheduleDeployment(selected.id, scheduleData), 'Deployment dijadwalkan')

  const handleStart = () => selected && act(() => ticketService.startDeployment(selected.id, {}), 'Deployment dimulai')

  const handleStep = (step: TicketDeploymentStepRecord, status: 'completed' | 'failed') =>
    selected && act(() => ticketService.manageDeploymentStep(selected.id, { ...step, status }), `Step ${status}`)

  const handleComplete = () =>
    selected &&
    act(() => ticketService.completeDeployment(selected.id, { summary: 'Completed via UI' }), 'Deployment selesai')

  const handleFail = () =>
    selected && act(() => ticketService.failDeployment(selected.id, { reason: 'Failed via UI' }), 'Deployment gagal')

  const handleStartMonitoring = () =>
    selected && act(() => ticketService.startMonitoring(selected.id), 'Monitoring dimulai')

  const handleClose = () =>
    selected &&
    act(
      () =>
        ticketService.closeTicket(selected.id, { closure_summary: 'Closed via UI', resolution_summary: 'Resolved' }),
      'Tiket ditutup',
    )

  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      {success && <Toast type="success" message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Deployment & Monitoring Workspace"
        subtitle="Kelola eksekusi deployment, pantau sistem, dan tutup tiket"
      />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500">Memuat...</p>
      ) : (
        <div className="grid gap-5 lg:grid-cols-3">
          <SectionCard title="Queue" className="lg:col-span-1">
            <div className="space-y-3">
              {tickets.length === 0 ? (
                <EmptyState title="Queue kosong" message="Tidak ada tiket untuk deployment." />
              ) : (
                tickets.map((ticket) => (
                  <button
                    key={ticket.id}
                    className={`w-full rounded-xl border p-3 text-left ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'bg-white'}`}
                    onClick={() => setSelected(ticket)}
                  >
                    <b className="font-mono text-xs">{ticket.ticket_number}</b>
                    <p className="text-sm font-semibold">{ticket.title}</p>
                    <StatusBadge status={ticket.status} />
                  </button>
                ))
              )}
            </div>
          </SectionCard>

          {selected && (
            <div className="space-y-5 lg:col-span-2">
              <SectionCard title="Deployment Controls">
                {selected.status === 'release_ready' && (
                  <div className="space-y-3">
                    <Input
                      type="datetime-local"
                      label="Jadwal Deployment"
                      value={scheduleData.scheduled_start_at}
                      onChange={(e: any) => setScheduleData({ scheduled_start_at: e.target.value })}
                    />
                    <Button loading={busy} onClick={handleSchedule} disabled={!scheduleData.scheduled_start_at}>
                      Jadwalkan Deployment
                    </Button>
                  </div>
                )}

                {selected.status === 'deployment_scheduled' && (
                  <Button loading={busy} onClick={handleStart}>
                    Mulai Deployment
                  </Button>
                )}

                {selected.status === 'deployment_in_progress' && selected.current_deployment && (
                  <div className="space-y-4">
                    <p className="text-sm text-gray-600">
                      Eksekusi step deployment (Tugas PIC akan ditangani di workspace PIC, namun IT Lead dapat mengambil
                      alih):
                    </p>
                    {selected.current_deployment.steps?.map((step: any) => (
                      <div key={step.id} className="rounded border p-3 text-sm flex items-center justify-between">
                        <div>
                          <b>
                            Step {step.step_number}: {step.title}
                          </b>{' '}
                          ({step.step_type})<p className="text-xs text-gray-500">Status: {step.status}</p>
                        </div>
                        {step.status !== 'completed' && (
                          <div className="flex gap-2">
                            <Button size="sm" variant="success" onClick={() => handleStep(step, 'completed')}>
                              Selesai
                            </Button>
                            <Button size="sm" variant="danger" onClick={() => handleStep(step, 'failed')}>
                              Gagal
                            </Button>
                          </div>
                        )}
                      </div>
                    ))}

                    <div className="flex gap-3 pt-4 border-t">
                      <Button variant="primary" loading={busy} onClick={handleComplete}>
                        Selesaikan Deployment
                      </Button>
                      <Button variant="danger" loading={busy} onClick={handleFail}>
                        Gagalkan Deployment
                      </Button>
                    </div>
                  </div>
                )}

                {selected.status === 'deployed' && (
                  <Button variant="primary" loading={busy} onClick={handleStartMonitoring}>
                    Mulai Masa Monitoring
                  </Button>
                )}
              </SectionCard>

              {['monitoring', 'post_release_issue', 'awaiting_requester_confirmation'].includes(selected.status) && (
                <SectionCard title="Monitoring & Closure">
                  <div className="space-y-3">
                    <p className="text-sm">
                      Status saat ini: <b>{selected.status.replace(/_/g, ' ').toUpperCase()}</b>
                    </p>

                    {selected.status === 'awaiting_requester_confirmation' && (
                      <div className="rounded bg-yellow-50 p-3 text-sm text-yellow-800 border border-yellow-200">
                        Menunggu konfirmasi final dari Requester sebelum dapat ditutup. Jika requester menerima, tiket
                        dapat ditutup. Jika menolak, tiket akan dikembalikan ke Development.
                      </div>
                    )}

                    {/* IT Lead can close if condition met. Closure gate checks if confirmation exists and is accepted */}
                    <Button variant="success" loading={busy} onClick={handleClose}>
                      Close Ticket (Selesai)
                    </Button>
                  </div>
                </SectionCard>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
