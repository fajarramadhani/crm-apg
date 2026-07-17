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
    [uatFindings, setUatFindings] = useState<any[]>([]),
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
    [activityType, setActivityType] = useState('development'),
    [defectNotes, setDefectNotes] = useState<Record<number, string>>({}),
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
    setUatFindings(
      ['uat_failed', 'uat_retest', 'uat_in_progress', 'uat_approved'].includes(t.status)
        ? await ticketService.picUatFindings(id)
        : [],
    )
    setProgress(t.progress_percentage)
    setActivityType(t.qa_defects && t.qa_defects.length > 0 ? 'rework' : 'development')
    setWorklogs(w)
    setUpdates(u)
    setCases(c)
    setRuns(r)
  }
  const load = async (id?: number) => {
    const response = await ticketService.picAssignments({ per_page: 50 })
    const eligible = response.data.filter((t) =>
      [
        'ready_for_development',
        'development_in_progress',
        'internal_testing',
        'ready_for_qa',
        'qa_retest',
        'ready_for_uat',
      ].includes(t.status),
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
          activity_type: activityType,
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
                {ticket.qa_defects && ticket.qa_defects.length > 0 && (
                  <SectionCard
                    title={`Temuan Defect QA (${ticket.qa_defects.length})`}
                    className="border-red-100 bg-red-50/10"
                  >
                    <div className="space-y-4">
                      {ticket.qa_defects.map((d) => (
                        <div key={d.id} className="border border-red-100 rounded-xl p-4 bg-white shadow-2xs">
                          <div className="flex justify-between items-start mb-2">
                            <div>
                              <span className="font-mono text-2xs text-red-500 font-bold">{d.defect_number}</span>
                              <h4 className="text-sm font-bold text-gray-900">{d.title}</h4>
                            </div>
                            <span
                              className={`px-2 py-0.5 text-xs font-bold rounded uppercase ${
                                d.status === 'open' || d.status === 'reopened'
                                  ? 'bg-red-100 text-red-800'
                                  : d.status === 'in_progress'
                                    ? 'bg-blue-100 text-blue-800'
                                    : d.status === 'resolved'
                                      ? 'bg-green-100 text-green-800'
                                      : 'bg-gray-100 text-gray-800'
                              }`}
                            >
                              {d.status}
                            </span>
                          </div>

                          <p className="text-xs text-gray-600 mb-3 leading-relaxed">{d.description}</p>

                          <div className="grid grid-cols-2 gap-2 text-2xs text-gray-500 mb-3 bg-gray-50 p-2 rounded-lg font-medium">
                            <p>
                              <b>Severity:</b> {d.severity.toUpperCase()}
                            </p>
                            <p>
                              <b>Priority:</b> {d.priority.toUpperCase()}
                            </p>
                            {d.expected_result && (
                              <p className="col-span-2">
                                <b>Ekspektasi:</b> {d.expected_result}
                              </p>
                            )}
                            {d.actual_result && (
                              <p className="col-span-2">
                                <b>Aktual:</b> {d.actual_result}
                              </p>
                            )}
                          </div>

                          {(d.status === 'open' || d.status === 'reopened') && (
                            <div className="flex justify-end pt-2 border-t">
                              <Button
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                  void act(
                                    () => ticketService.startDefectFix(ticket.id, d.id),
                                    'Mulai memperbaiki defect.',
                                  )
                                }
                              >
                                🛠️ Mulai Perbaikan
                              </Button>
                            </div>
                          )}

                          {d.status === 'in_progress' && (
                            <div className="pt-3 border-t space-y-2">
                              <Textarea
                                placeholder="Tuliskan catatan perbaikan..."
                                rows={2}
                                value={defectNotes[d.id] || ''}
                                onChange={(e) => setDefectNotes((prev) => ({ ...prev, [d.id]: e.target.value }))}
                              />
                              <div className="flex justify-end">
                                <Button
                                  size="sm"
                                  variant="success"
                                  disabled={busy || !(defectNotes[d.id] || '').trim()}
                                  onClick={() =>
                                    void act(
                                      () =>
                                        ticketService.resolveDefect(ticket.id, d.id, {
                                          resolution_notes: defectNotes[d.id],
                                        }),
                                      'Defect diselesaikan.',
                                    ).then(() => {
                                      setDefectNotes((prev) => {
                                        const next = { ...prev }
                                        delete next[d.id]
                                        return next
                                      })
                                    })
                                  }
                                >
                                  ✓ Selesaikan Perbaikan
                                </Button>
                              </div>
                            </div>
                          )}

                          {d.status === 'resolved' && d.resolution_notes && (
                            <div className="text-2xs text-green-700 bg-green-50 p-2 rounded border border-green-100 font-medium">
                              <b>Catatan Perbaikan:</b> {d.resolution_notes}
                            </div>
                          )}
                        </div>
                      ))}

                      <div className="border-t pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-indigo-50/30 p-4 rounded-xl border border-indigo-100/50 mt-4">
                        <div>
                          <h4 className="text-sm font-bold text-indigo-900">Kirim Perbaikan ke QA</h4>
                          <p className="text-2xs text-indigo-700 mt-1 leading-relaxed max-w-md">
                            Pastikan seluruh defect telah diselesaikan, progress mencapai 100%, serta Anda telah
                            mencatat minimal 1 worklog rework dan 1 internal test run yang sukses setelah kegagalan QA.
                          </p>
                        </div>
                        <Button
                          variant="primary"
                          disabled={
                            busy ||
                            ticket.status !== 'development_in_progress' ||
                            ticket.progress_percentage !== 100 ||
                            ticket.qa_defects.some((d) => ['open', 'in_progress', 'reopened'].includes(d.status))
                          }
                          onClick={() =>
                            void act(
                              () => ticketService.submitQaRetest(ticket.id),
                              'Tiket berhasil dikirim ulang ke QA.',
                            )
                          }
                        >
                          🚀 Kirim ke QA
                        </Button>
                      </div>
                    </div>
                  </SectionCard>
                )}
                {uatFindings.length > 0 && (
                  <SectionCard
                    title={`Temuan UAT (${uatFindings.length})`}
                    className="border-orange-100 bg-orange-50/10"
                  >
                    <div className="space-y-4">
                      {uatFindings.map((finding) => (
                        <div key={finding.id} className="rounded-xl border border-orange-100 bg-white p-4 shadow-2xs">
                          <div className="mb-2 flex items-start justify-between gap-3">
                            <div>
                              <span className="font-mono text-2xs font-bold text-orange-600">
                                {finding.finding_number}
                              </span>
                              <h4 className="text-sm font-bold text-gray-900">{finding.title}</h4>
                            </div>
                            <span className="rounded bg-orange-100 px-2 py-0.5 text-xs font-bold uppercase text-orange-800">
                              {finding.status}
                            </span>
                          </div>
                          <p className="mb-3 text-xs leading-relaxed text-gray-600">{finding.description}</p>
                          {(finding.status === 'open' || finding.status === 'reopened') && (
                            <div className="flex justify-end border-t pt-2">
                              <Button
                                size="sm"
                                disabled={busy}
                                onClick={() =>
                                  void act(
                                    () => ticketService.picStartUatFinding(ticket.id, finding.id),
                                    'Perbaikan finding UAT dimulai.',
                                  )
                                }
                              >
                                Mulai Perbaikan UAT
                              </Button>
                            </div>
                          )}
                          {finding.status === 'in_progress' && (
                            <div className="space-y-2 border-t pt-3">
                              <label className="block cursor-pointer rounded-lg border border-dashed border-orange-200 p-2 text-center text-xs text-orange-700">
                                Unggah evidence perbaikan UAT
                                <input
                                  className="sr-only"
                                  type="file"
                                  accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                                  onChange={(event) => {
                                    const file = event.target.files?.[0]
                                    if (file) {
                                      void act(
                                        () => ticketService.uploadPicUatEvidence(ticket.id, file, finding.id),
                                        'Evidence perbaikan UAT berhasil diunggah.',
                                      )
                                    }
                                  }}
                                />
                              </label>
                              <Textarea
                                rows={2}
                                placeholder="Tuliskan catatan penyelesaian finding UAT..."
                                value={defectNotes[finding.id] || ''}
                                onChange={(event) =>
                                  setDefectNotes((previous) => ({ ...previous, [finding.id]: event.target.value }))
                                }
                              />
                              <div className="flex justify-end">
                                <Button
                                  size="sm"
                                  variant="success"
                                  disabled={busy || !(defectNotes[finding.id] || '').trim()}
                                  onClick={() =>
                                    void act(
                                      () =>
                                        ticketService.picResolveUatFinding(ticket.id, finding.id, {
                                          resolution_notes: defectNotes[finding.id],
                                        }),
                                      'Finding UAT diselesaikan.',
                                    )
                                  }
                                >
                                  Selesaikan Perbaikan UAT
                                </Button>
                              </div>
                            </div>
                          )}
                          {finding.resolution_notes && (
                            <p className="mt-2 rounded border border-green-100 bg-green-50 p-2 text-2xs font-medium text-green-700">
                              <b>Catatan Perbaikan:</b> {finding.resolution_notes}
                            </p>
                          )}
                        </div>
                      ))}
                      <div className="flex flex-col justify-between gap-3 rounded-xl border border-orange-100 bg-orange-50/30 p-4 sm:flex-row sm:items-center">
                        <div>
                          <h4 className="text-sm font-bold text-orange-900">Kirim ke Retest UAT</h4>
                          <p className="mt-1 max-w-md text-2xs leading-relaxed text-orange-700">
                            Pastikan finding selesai, progress 100%, worklog rework dan internal test sukses sudah
                            dicatat.
                          </p>
                        </div>
                        <Button
                          variant="primary"
                          disabled={
                            busy ||
                            ticket.status !== 'development_in_progress' ||
                            ticket.progress_percentage !== 100 ||
                            uatFindings.some((finding) => ['open', 'in_progress', 'reopened'].includes(finding.status))
                          }
                          onClick={() =>
                            void act(
                              () => ticketService.picSubmitUatRetest(ticket.id, { requires_qa_retest: false }),
                              'Tiket berhasil dikirim ulang ke UAT.',
                            )
                          }
                        >
                          Kirim ke UAT Retest
                        </Button>
                      </div>
                    </div>
                  </SectionCard>
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
                      {ticket.qa_defects && ticket.qa_defects.length > 0 ? (
                        <Select
                          label="Aktivitas"
                          value={activityType}
                          onChange={(e) => setActivityType(e.target.value)}
                          options={[
                            { value: 'development', label: 'Development' },
                            { value: 'rework', label: 'Rework (Perbaikan Defect)' },
                          ]}
                        />
                      ) : (
                        <Input label="Aktivitas" value="Development" disabled />
                      )}
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
