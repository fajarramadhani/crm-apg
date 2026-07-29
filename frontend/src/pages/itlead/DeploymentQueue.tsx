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
    setError('')
    try {
      await fn()
      setSuccess(msg)
      await load()
    } catch (err: any) {
      if (err.errors) {
        setError(Object.values(err.errors).flat().join(' | '))
      } else {
        setError(err.message || 'Aksi gagal')
      }
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
    selected && act(() => ticketService.closeTicket(selected.id, { notes: 'Closed via UI' }), 'Tiket ditutup')

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
              <SectionCard title={`Deployment Controls — ${selected.ticket_number}`}>
                <div className="space-y-5">
                  {/* Fitur Atur Tanggal & Waktu Deployment */}
                  <div className="space-y-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <h4 className="text-sm font-semibold text-gray-800">📅 Atur Tanggal & Waktu Deployment</h4>
                    <div className="flex flex-wrap items-end gap-3">
                      <div className="flex-1 min-w-[200px]">
                        <Input
                          type="datetime-local"
                          label="Pilih Tanggal & Jam Rilis"
                          value={scheduleData.scheduled_start_at}
                          onChange={(e: any) => setScheduleData({ scheduled_start_at: e.target.value })}
                        />
                      </div>
                      <Button loading={busy} onClick={handleSchedule} disabled={!scheduleData.scheduled_start_at}>
                        Jadwalkan Deployment
                      </Button>
                    </div>
                  </div>

                  {/* Status & Action Control Buttons */}
                  <div className="pt-2">
                    <h4 className="text-sm font-semibold text-gray-800 mb-3">⚡ Eksekusi Deployment</h4>

                    {['release_ready', 'deployment_scheduled'].includes(selected.status) && (
                      <div className="space-y-3">
                        <p className="text-sm text-gray-600">
                          Tiket siap untuk dirilis. Klik tombol di bawah untuk memulai proses deployment ke lingkungan
                          produksi.
                        </p>
                        <Button variant="success" size="lg" loading={busy} onClick={handleStart}>
                          ▶ Mulai Deployment Sekarang
                        </Button>
                      </div>
                    )}

                    {selected.status === 'deployment_in_progress' && (
                      <div className="space-y-4">
                        <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 font-medium">
                          ⚙️ Deployment sedang berjalan (Deployment In Progress)
                        </div>

                        {selected.current_deployment?.steps && selected.current_deployment.steps.length > 0 && (
                          <div className="space-y-2">
                            {selected.current_deployment.steps.map((step: any) => (
                              <div
                                key={step.id}
                                className="rounded-xl border p-3 text-sm flex items-center justify-between bg-white shadow-sm"
                              >
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
                          </div>
                        )}

                        <div className="flex flex-wrap gap-3 pt-3 border-t">
                          <Button variant="success" size="lg" loading={busy} onClick={handleComplete}>
                            ✓ Selesaikan Deployment
                          </Button>
                          <Button variant="danger" loading={busy} onClick={handleFail}>
                            ✕ Gagalkan Deployment
                          </Button>
                        </div>
                      </div>
                    )}

                    {selected.status === 'deployed' && (
                      <div className="space-y-3">
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 font-medium">
                          ✅ Deployment telah berhasil dilakukan!
                        </div>
                        <Button variant="primary" size="lg" loading={busy} onClick={handleStartMonitoring}>
                          Mulai Masa Monitoring
                        </Button>
                      </div>
                    )}

                    {['monitoring', 'post_release_issue', 'awaiting_requester_confirmation', 'closed'].includes(
                      selected.status,
                    ) && (
                      <div className="space-y-4">
                        <div className="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-900 font-medium">
                          🔍 Status: {selected.status.replace(/_/g, ' ').toUpperCase()}
                        </div>
                        <Button variant="success" size="lg" loading={busy} onClick={handleClose}>
                          ✓ Close Ticket (Selesai Penanganan)
                        </Button>
                      </div>
                    )}
                  </div>
                </div>
              </SectionCard>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
