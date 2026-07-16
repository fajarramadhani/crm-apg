import { useEffect, useMemo, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  EmptyState,
  Input,
  PageHeader,
  SectionCard,
  Select,
  StatusBadge,
  Textarea,
  Toast,
} from '../../components/ui'
import {
  ticketService,
  type InternalTestCaseRecord,
  type InternalTestRunRecord,
  type TicketDevelopmentUpdateRecord,
  type TicketRecord,
  type TicketWorklogRecord,
} from '../../services/ticketService'

const csv = (value: string) =>
  value
    .split('\n')
    .map((v) => v.trim())
    .filter(Boolean)
export default function InternalTestingPIC() {
  const [tickets, setTickets] = useState<TicketRecord[]>([]),
    [ticket, setTicket] = useState<TicketRecord | null>(null),
    [worklogs, setWorklogs] = useState<TicketWorklogRecord[]>([]),
    [updates, setUpdates] = useState<TicketDevelopmentUpdateRecord[]>([]),
    [cases, setCases] = useState<InternalTestCaseRecord[]>([]),
    [runs, setRuns] = useState<InternalTestRunRecord[]>([])
  const [busy, setBusy] = useState(false),
    [loading, setLoading] = useState(true),
    [error, setError] = useState(''),
    [success, setSuccess] = useState('')
  const [minutes, setMinutes] = useState(60),
    [description, setDescription] = useState(''),
    [progress, setProgress] = useState(0),
    [summary, setSummary] = useState(''),
    [completed, setCompleted] = useState(''),
    [remaining, setRemaining] = useState(''),
    [blockers, setBlockers] = useState(''),
    [nextSteps, setNextSteps] = useState('')
  const [caseForm, setCaseForm] = useState({
      case_number: 'IT-01',
      title: '',
      preconditions: '',
      steps: '',
      expected_result: '',
    }),
    [environment, setEnvironment] = useState('staging'),
    [build, setBuild] = useState(''),
    [resultDrafts, setResultDrafts] = useState<
      Record<number, { status: string; actual_result: string; notes: string }>
    >({})
  const activeRun = useMemo(() => runs.find((r) => r.status === 'in_progress'), [runs])
  const message = (cause: unknown) => {
    const api = cause as ApiRequestError
    return api.errors ? Object.values(api.errors).flat()[0] : api.message || 'Permintaan gagal.'
  }
  const detail = async (id: number) => {
    const [t, w, u, c, r] = await Promise.all([
      ticketService.picGet(id),
      ticketService.worklogs(id),
      ticketService.developmentUpdates(id),
      ticketService.internalTestCases(id),
      ticketService.internalTestRuns(id),
    ])
    setTicket(t)
    setProgress(t.progress_percentage)
    setWorklogs(w)
    setUpdates(u)
    setCases(c)
    setRuns(r)
  }
  const load = async (id?: number) => {
    const response = await ticketService.picAssignments({ per_page: 50 })
    const eligible = response.data.filter((t) =>
      ['ready_for_development', 'development_in_progress', 'internal_testing', 'ready_for_qa'].includes(t.status),
    )
    setTickets(eligible)
    const selected = id || ticket?.id || eligible[0]?.id
    if (selected) await detail(selected)
  }
  useEffect(() => {
    load()
      .catch(() => setError('Workspace development tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])
  const act = async (work: () => Promise<unknown>, text: string) => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      await work()
      setSuccess(text)
      await load(ticket.id)
    } catch (cause) {
      setError(message(cause))
    } finally {
      setBusy(false)
    }
  }
  const addWorklog = () =>
    act(
      () =>
        ticketService.addWorklog(ticket!.id, {
          work_date: new Date().toISOString().slice(0, 10),
          minutes_spent: minutes,
          activity_type: 'development',
          description,
          progress_after: progress,
          expected_progress: ticket!.progress_percentage,
        }),
      'Worklog tersimpan.',
    )
  const addUpdate = () =>
    act(
      () =>
        ticketService.addDevelopmentUpdate(ticket!.id, {
          progress_percentage: progress,
          expected_progress: ticket!.progress_percentage,
          summary,
          completed_items: csv(completed),
          remaining_items: csv(remaining),
          blockers: csv(blockers),
          next_steps: csv(nextSteps),
        }),
      'Progress diperbarui.',
    )
  const createCase = () =>
    act(
      () => ticketService.createInternalTestCase(ticket!.id, { ...caseForm, steps: csv(caseForm.steps) }),
      'Test case dibuat.',
    )
  const saveResult = (testCase: InternalTestCaseRecord) => {
    const d = resultDrafts[testCase.id] || { status: 'passed', actual_result: '', notes: '' }
    return act(
      () => ticketService.recordInternalTestResult(ticket!.id, activeRun!.id, { test_case_id: testCase.id, ...d }),
      'Hasil test tersimpan.',
    )
  }
  if (loading) return <p className="py-20 text-center text-sm text-gray-500">Memuat workspace development...</p>
  return (
    <div className="space-y-5">
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}{' '}
      {success && <Toast message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Development & Internal Testing"
        subtitle="Eksekusi approved solution plan, worklog, evidence, dan pengujian internal"
      />
      {tickets.length === 0 ? (
        <EmptyState title="Belum ada tiket development" message="Tiket muncul setelah solution plan disetujui." />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-5">
          <aside className="space-y-2">
            {tickets.map((t) => (
              <button
                key={t.id}
                onClick={() => void detail(t.id)}
                className={`w-full text-left rounded-xl border p-3 ${ticket?.id === t.id ? 'border-blue-700 bg-blue-50' : 'border-gray-200 bg-white'}`}
              >
                <span className="font-mono text-xs text-gray-500">{t.ticket_number}</span>
                <p className="text-sm font-medium mt-1">{t.title}</p>
                <div className="mt-2">
                  <StatusBadge status={t.status} />
                </div>
              </button>
            ))}
          </aside>
          {ticket && (
            <main className="lg:col-span-3 space-y-5">
              <SectionCard title={ticket.ticket_number}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <h2 className="font-bold">{ticket.title}</h2>
                    <p className="text-sm text-gray-500 mt-1">
                      SLA: {ticket.resolution_due_at ? new Date(ticket.resolution_due_at).toLocaleString('id-ID') : '—'}
                    </p>
                  </div>
                  <StatusBadge status={ticket.status} />
                </div>
                <div className="mt-5">
                  <div className="flex justify-between text-sm mb-1">
                    <span>Progress</span>
                    <b>{ticket.progress_percentage}%</b>
                  </div>
                  <div className="h-2.5 rounded-full bg-gray-200">
                    <div
                      className="h-full rounded-full bg-blue-700"
                      style={{ width: `${ticket.progress_percentage}%` }}
                    />
                  </div>
                </div>
                {ticket.status === 'ready_for_development' && (
                  <Button
                    className="mt-5"
                    loading={busy}
                    onClick={() => void act(() => ticketService.startDevelopment(ticket.id), 'Development dimulai.')}
                  >
                    Start Development
                  </Button>
                )}
                {ticket.status === 'ready_for_qa' && (
                  <div className="mt-5 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm font-semibold text-emerald-800">
                    Internal testing lulus. Tiket siap dikirim ke QA.
                  </div>
                )}
              </SectionCard>
              {ticket.status === 'development_in_progress' && (
                <>
                  <SectionCard title="Worklog & Progress">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <Input
                        label="Menit kerja"
                        type="number"
                        value={minutes}
                        onChange={(e) => setMinutes(Number(e.target.value))}
                      />
                      <Input
                        label="Progress 0–100"
                        type="number"
                        value={progress}
                        onChange={(e) => setProgress(Number(e.target.value))}
                      />
                      <Input label="Aktivitas" value="Development" disabled />
                    </div>
                    <Textarea
                      label="Deskripsi pekerjaan"
                      rows={3}
                      value={description}
                      onChange={(e) => setDescription(e.target.value)}
                    />
                    <div className="flex justify-end mt-3">
                      <Button disabled={busy || !description.trim()} onClick={() => void addWorklog()}>
                        Tambah Worklog
                      </Button>
                    </div>
                    <div className="mt-5 border-t pt-4 space-y-3">
                      <Textarea
                        label="Ringkasan progress"
                        rows={2}
                        value={summary}
                        onChange={(e) => setSummary(e.target.value)}
                      />
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Textarea
                          label="Completed items (satu per baris)"
                          rows={3}
                          value={completed}
                          onChange={(e) => setCompleted(e.target.value)}
                        />
                        <Textarea
                          label="Remaining items"
                          rows={3}
                          value={remaining}
                          onChange={(e) => setRemaining(e.target.value)}
                        />
                        <Textarea
                          label="Blockers"
                          rows={3}
                          value={blockers}
                          onChange={(e) => setBlockers(e.target.value)}
                        />
                        <Textarea
                          label="Next steps"
                          rows={3}
                          value={nextSteps}
                          onChange={(e) => setNextSteps(e.target.value)}
                        />
                      </div>
                      <div className="flex justify-end">
                        <Button disabled={busy || !summary.trim()} onClick={() => void addUpdate()}>
                          Simpan Update
                        </Button>
                      </div>
                    </div>
                  </SectionCard>
                  <SectionCard title="Evidence">
                    <label className="block rounded-lg border border-dashed p-4 text-center text-sm text-blue-700 cursor-pointer">
                      Upload screenshot, PDF, TXT, CSV, atau dokumen aman
                      <input
                        className="sr-only"
                        type="file"
                        accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                        onChange={(e) => {
                          const file = e.target.files?.[0]
                          if (file)
                            void act(
                              () => ticketService.uploadDevelopmentEvidence(ticket.id, file),
                              'Evidence diunggah.',
                            )
                        }}
                      />
                    </label>
                    <div className="mt-3 space-y-2">
                      {ticket.attachments
                        .filter((a) =>
                          ['development_evidence', 'test_evidence', 'log', 'documentation'].includes(a.category),
                        )
                        .map((a) => (
                          <div className="text-sm border rounded-lg p-2" key={a.id}>
                            {a.original_name} · {a.category}
                          </div>
                        ))}
                    </div>
                  </SectionCard>
                </>
              )}
              {['development_in_progress', 'internal_testing'].includes(ticket.status) && (
                <SectionCard title="Internal Test Cases">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <Input
                      label="Case number"
                      value={caseForm.case_number}
                      onChange={(e) => setCaseForm({ ...caseForm, case_number: e.target.value })}
                    />
                    <Input
                      label="Judul"
                      value={caseForm.title}
                      onChange={(e) => setCaseForm({ ...caseForm, title: e.target.value })}
                    />
                  </div>
                  <Textarea
                    label="Steps (satu per baris)"
                    rows={3}
                    value={caseForm.steps}
                    onChange={(e) => setCaseForm({ ...caseForm, steps: e.target.value })}
                  />
                  <Textarea
                    label="Expected result"
                    rows={2}
                    value={caseForm.expected_result}
                    onChange={(e) => setCaseForm({ ...caseForm, expected_result: e.target.value })}
                  />
                  {ticket.status === 'development_in_progress' && (
                    <div className="flex justify-end">
                      <Button
                        disabled={busy || !caseForm.title || !caseForm.steps || !caseForm.expected_result}
                        onClick={() => void createCase()}
                      >
                        Tambah Test Case
                      </Button>
                    </div>
                  )}
                  <div className="mt-4 space-y-2">
                    {cases.map((c) => (
                      <div key={c.id} className="border rounded-lg p-3 text-sm">
                        <b>
                          {c.case_number} · {c.title}
                        </b>
                        <p className="text-gray-500 mt-1">Expected: {c.expected_result}</p>
                      </div>
                    ))}
                  </div>
                </SectionCard>
              )}
              {ticket.status === 'development_in_progress' &&
                ticket.progress_percentage === 100 &&
                cases.some((c) => c.is_active) && (
                  <SectionCard title="Mulai Internal Testing">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <Select
                        label="Environment"
                        value={environment}
                        onChange={(e) => setEnvironment(e.target.value)}
                        options={[
                          { value: 'local', label: 'Local' },
                          { value: 'development', label: 'Development' },
                          { value: 'staging', label: 'Staging' },
                        ]}
                      />
                      <Input
                        label="Build reference (metadata)"
                        value={build}
                        onChange={(e) => setBuild(e.target.value)}
                      />
                    </div>
                    <div className="flex justify-end mt-4">
                      <Button
                        loading={busy}
                        onClick={() =>
                          void act(
                            () =>
                              ticketService.startInternalTestRun(ticket.id, {
                                environment,
                                build_reference: build || undefined,
                              }),
                            'Internal testing dimulai.',
                          )
                        }
                      >
                        Start Test Run
                      </Button>
                    </div>
                  </SectionCard>
                )}
              {ticket.status === 'internal_testing' && activeRun && (
                <SectionCard title={`Test Run #${activeRun.run_number}`}>
                  <p className="text-sm text-gray-500 mb-4">
                    {activeRun.environment}
                    {activeRun.build_reference ? ` · ${activeRun.build_reference}` : ''}
                  </p>
                  <div className="space-y-4">
                    {cases
                      .filter((c) => c.is_active)
                      .map((c) => {
                        const saved = activeRun.results.find((r) => r.test_case_id === c.id)
                        const d = resultDrafts[c.id] || { status: 'passed', actual_result: '', notes: '' }
                        return (
                          <div key={c.id} className="border rounded-xl p-4">
                            <b className="text-sm">
                              {c.case_number} · {c.title}
                            </b>
                            {saved ? (
                              <p
                                className={`mt-2 text-sm font-semibold ${saved.status === 'passed' ? 'text-emerald-700' : 'text-red-700'}`}
                              >
                                {saved.status.toUpperCase()}
                              </p>
                            ) : (
                              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                                <Select
                                  label="Result"
                                  value={d.status}
                                  onChange={(e) =>
                                    setResultDrafts({ ...resultDrafts, [c.id]: { ...d, status: e.target.value } })
                                  }
                                  options={['passed', 'failed', 'blocked', 'not_run'].map((v) => ({
                                    value: v,
                                    label: v.replace('_', ' ').toUpperCase(),
                                  }))}
                                />
                                <Input
                                  label="Actual result"
                                  value={d.actual_result}
                                  onChange={(e) =>
                                    setResultDrafts({
                                      ...resultDrafts,
                                      [c.id]: { ...d, actual_result: e.target.value },
                                    })
                                  }
                                />
                                <Input
                                  label="Notes"
                                  value={d.notes}
                                  onChange={(e) =>
                                    setResultDrafts({ ...resultDrafts, [c.id]: { ...d, notes: e.target.value } })
                                  }
                                />
                                <Button disabled={busy} onClick={() => void saveResult(c)}>
                                  Simpan Result
                                </Button>
                              </div>
                            )}
                          </div>
                        )
                      })}
                  </div>
                  <div className="flex justify-end mt-5">
                    <Button
                      loading={busy}
                      onClick={() =>
                        void act(
                          () =>
                            ticketService.completeInternalTestRun(ticket.id, activeRun.id, 'Internal test completed'),
                          'Test run diselesaikan.',
                        )
                      }
                    >
                      Complete Test Run
                    </Button>
                  </div>
                </SectionCard>
              )}
              <SectionCard title="Aktivitas">
                <p className="text-sm font-semibold">
                  Actual effort: {worklogs.reduce((n, w) => n + w.minutes_spent, 0)} menit
                </p>
                <div className="mt-3 space-y-2">
                  {updates.map((u) => (
                    <div key={u.id} className="border-l-2 border-blue-300 pl-3 text-sm">
                      <b>{u.progress_percentage}%</b> · {u.summary}
                      {u.blockers.length > 0 && <p className="text-amber-700">Blocker: {u.blockers.join(', ')}</p>}
                    </div>
                  ))}
                </div>
              </SectionCard>
            </main>
          )}
        </div>
      )}
    </div>
  )
}
