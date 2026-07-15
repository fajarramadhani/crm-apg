import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import {
  Button,
  EmptyState,
  Input,
  Modal,
  PageHeader,
  PriorityBadge,
  SectionCard,
  Select,
  StatusBadge,
  Textarea,
  Toast,
} from '../../components/ui'
import {
  ticketService,
  type AnalysisPayload,
  type SolutionPlanPayload,
  type SolutionPlanRecord,
  type TicketAnalysisRecord,
  type TicketRecord,
} from '../../services/ticketService'

const emptyAnalysis: AnalysisPayload = {
  problem_summary: '',
  root_cause: '',
  technical_impact: '',
  business_impact: '',
  affected_components: [],
  evidence: '',
  assumptions: '',
  limitations: '',
}
const emptyPlan: SolutionPlanPayload = {
  solution_summary: '',
  implementation_steps: [{ order: 1, description: '' }],
  affected_components: [],
  dependencies: [],
  estimated_effort_minutes: 60,
  risk_level: 'low',
  risk_description: '',
  rollback_plan: '',
  testing_plan: '',
  deployment_consideration: '',
}

export default function Workspace() {
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [selected, setSelected] = useState<TicketRecord | null>(null)
  const [analysis, setAnalysis] = useState<TicketAnalysisRecord | null>(null)
  const [plan, setPlan] = useState<SolutionPlanRecord | null>(null)
  const [analysisForm, setAnalysisForm] = useState<AnalysisPayload>(emptyAnalysis)
  const [planForm, setPlanForm] = useState<SolutionPlanPayload>(emptyPlan)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [confirm, setConfirm] = useState<'start' | 'complete' | 'submit' | null>(null)

  const message = (cause: unknown) => {
    const api = cause as ApiRequestError
    return api.errors ? Object.values(api.errors).flat()[0] : api.message || 'Permintaan gagal diproses.'
  }
  const loadList = async () => {
    const response = await ticketService.picAssignments({ per_page: 50 })
    setTickets(response.data)
    return response.data
  }
  const loadDetail = async (id: number) => {
    const ticket = await ticketService.picGet(id)
    setSelected(ticket)
    const analysisData = ['analysis', 'solution_planning', 'plan_review', 'ready_for_development'].includes(
      ticket.status,
    )
      ? await ticketService.getAnalysis(id)
      : null
    const currentAnalysis = analysisData?.current || null
    setAnalysis(currentAnalysis)
    setAnalysisForm(currentAnalysis ? analysisPayload(currentAnalysis) : emptyAnalysis)
    const planData = ['solution_planning', 'plan_review', 'ready_for_development'].includes(ticket.status)
      ? await ticketService.getSolutionPlan(id)
      : null
    const currentPlan = planData?.current || null
    setPlan(currentPlan)
    setPlanForm(currentPlan ? planPayload(currentPlan) : emptyPlan)
  }
  const refresh = async (id = selected?.id) => {
    await loadList()
    if (id) await loadDetail(id)
  }
  useEffect(() => {
    loadList()
      .then((items) => items[0] && loadDetail(items[0].id))
      .catch(() => setError('Assignment PIC tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])

  const act = async (work: () => Promise<unknown>, text: string) => {
    setBusy(true)
    setError('')
    try {
      await work()
      setConfirm(null)
      setSuccess(text)
      await refresh()
    } catch (cause) {
      setError(message(cause))
    } finally {
      setBusy(false)
    }
  }
  const saveAnalysis = () =>
    act(async () => {
      if (analysis)
        await ticketService.updateAnalysis(selected!.id, analysis.id, {
          ...analysisForm,
          expected_lock_version: analysis.lock_version,
        })
      else await ticketService.createAnalysis(selected!.id, analysisForm)
    }, 'Draft analysis berhasil disimpan.')
  const savePlan = () =>
    act(
      async () => {
        if (plan?.status === 'draft')
          await ticketService.updateSolutionPlan(selected!.id, plan.id, {
            ...planForm,
            expected_lock_version: plan.lock_version,
          })
        else await ticketService.createSolutionPlan(selected!.id, normalizeSteps(planForm))
      },
      plan?.status === 'revision_requested'
        ? 'Versi revisi solution plan berhasil dibuat.'
        : 'Draft solution plan berhasil disimpan.',
    )

  return (
    <div>
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      {success && <Toast message={success} onClose={() => setSuccess('')} />}
      <PageHeader
        title="Workspace Tiket"
        subtitle="Analisis, RCA, dan perencanaan solusi untuk assignment aktif Anda"
      />
      {loading ? (
        <p className="py-16 text-center text-sm text-gray-500" role="status">
          Memuat assignment...
        </p>
      ) : tickets.length === 0 ? (
        <EmptyState title="Belum ada assignment" message="Tiket yang ditugaskan kepada Anda akan muncul di sini." />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-5">
          <div className="space-y-2">
            {tickets.map((ticket) => (
              <button
                key={ticket.id}
                onClick={() => void loadDetail(ticket.id)}
                className={`w-full text-left border rounded-xl p-3 ${selected?.id === ticket.id ? 'border-blue-700 bg-blue-50' : 'bg-white border-gray-200'}`}
              >
                <span className="font-mono text-xs text-gray-500">{ticket.ticket_number}</span>
                <p className="font-medium text-sm mt-1">{ticket.title}</p>
                <div className="flex gap-2 mt-2 flex-wrap">
                  <StatusBadge status={ticket.status} />
                  {ticket.final_priority && <PriorityBadge priority={ticket.final_priority.key} />}
                </div>
              </button>
            ))}
          </div>
          <div className="lg:col-span-3 space-y-5">
            {selected && (
              <>
                <SectionCard title={selected.ticket_number}>
                  <div className="flex flex-wrap gap-2 mb-4">
                    <StatusBadge status={selected.status} />
                    {selected.final_priority && <PriorityBadge priority={selected.final_priority.key} />}
                  </div>
                  <h2 className="font-bold text-lg">{selected.title}</h2>
                  <p className="text-sm text-gray-600 whitespace-pre-wrap mt-2">{selected.description}</p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 rounded-xl p-4 mt-5">
                    <Info label="Requester" value={selected.requester.name} />
                    <Info label="Aplikasi" value={selected.application?.name || '—'} />
                    <Info
                      label="Deadline SLA"
                      value={
                        selected.resolution_due_at ? new Date(selected.resolution_due_at).toLocaleString('id-ID') : '—'
                      }
                    />
                    <Info label="PIC" value={selected.assignee?.name || '—'} />
                  </div>
                  {selected.status === 'assigned' && (
                    <div className="mt-5">
                      <Button disabled={busy} onClick={() => setConfirm('start')}>
                        Start Analysis
                      </Button>
                    </div>
                  )}
                </SectionCard>
                {['analysis', 'solution_planning', 'plan_review', 'ready_for_development'].includes(
                  selected.status,
                ) && (
                  <AnalysisForm
                    form={analysisForm}
                    setForm={setAnalysisForm}
                    readOnly={selected.status !== 'analysis' || Boolean(analysis?.completed_at)}
                    busy={busy}
                    onSave={saveAnalysis}
                    onComplete={() => setConfirm('complete')}
                    canComplete={selected.status === 'analysis' && Boolean(analysis) && !analysis?.completed_at}
                    version={analysis?.version}
                  />
                )}
                {['solution_planning', 'plan_review', 'ready_for_development'].includes(selected.status) && (
                  <PlanForm
                    form={planForm}
                    setForm={setPlanForm}
                    plan={plan}
                    readOnly={selected.status !== 'solution_planning'}
                    busy={busy}
                    onSave={savePlan}
                    onSubmit={() => setConfirm('submit')}
                  />
                )}
              </>
            )}
          </div>
        </div>
      )}
      <Modal
        open={confirm !== null}
        onClose={() => !busy && setConfirm(null)}
        title={
          confirm === 'start'
            ? 'Mulai Analysis'
            : confirm === 'complete'
              ? 'Selesaikan Analysis'
              : 'Submit Solution Plan'
        }
      >
        <p className="text-sm text-gray-600">
          {confirm === 'start'
            ? 'Status tiket akan berubah menjadi Analysis.'
            : confirm === 'complete'
              ? 'RCA akan dikunci dan tiket masuk Solution Planning.'
              : 'Plan akan dikunci dan dikirim kepada IT Lead untuk ditinjau.'}
        </p>
        <div className="flex justify-end gap-2 mt-5">
          <Button variant="secondary" disabled={busy} onClick={() => setConfirm(null)}>
            Batal
          </Button>
          <Button
            loading={busy}
            onClick={() =>
              confirm === 'start'
                ? void act(() => ticketService.startAnalysis(selected!.id), 'Analysis dimulai.')
                : confirm === 'complete'
                  ? void act(() => ticketService.completeAnalysis(selected!.id, analysis!.id), 'Analysis selesai.')
                  : void act(
                      () => ticketService.submitSolutionPlan(selected!.id, plan!.id),
                      'Solution plan dikirim ke IT Lead.',
                    )
            }
          >
            Konfirmasi
          </Button>
        </div>
      </Modal>
    </div>
  )
}

function AnalysisForm({
  form,
  setForm,
  readOnly,
  busy,
  onSave,
  onComplete,
  canComplete,
  version,
}: {
  form: AnalysisPayload
  setForm: (value: AnalysisPayload) => void
  readOnly: boolean
  busy: boolean
  onSave: () => void
  onComplete: () => void
  canComplete: boolean
  version?: number
}) {
  const field = (key: keyof AnalysisPayload, value: string | string[]) => setForm({ ...form, [key]: value })
  return (
    <SectionCard title={`Root Cause Analysis${version ? ` · v${version}` : ''}`}>
      <fieldset disabled={readOnly || busy} className="space-y-4">
        <Textarea
          label="Problem Summary *"
          rows={3}
          value={form.problem_summary}
          onChange={(e) => field('problem_summary', e.target.value)}
        />
        <Textarea
          label="Root Cause"
          rows={4}
          value={form.root_cause || ''}
          onChange={(e) => field('root_cause', e.target.value)}
          hint="Wajib sebelum Complete Analysis"
        />
        <Textarea
          label="Technical Impact *"
          rows={3}
          value={form.technical_impact}
          onChange={(e) => field('technical_impact', e.target.value)}
        />
        <Textarea
          label="Business Impact"
          rows={3}
          value={form.business_impact || ''}
          onChange={(e) => field('business_impact', e.target.value)}
        />
        <Input
          label="Affected Components"
          value={form.affected_components.join(', ')}
          onChange={(e) => field('affected_components', csv(e.target.value))}
          hint="Pisahkan dengan koma"
        />
        <Textarea
          label="Evidence / Referensi Lampiran"
          rows={2}
          value={form.evidence || ''}
          onChange={(e) => field('evidence', e.target.value)}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Textarea
            label="Assumptions"
            rows={2}
            value={form.assumptions || ''}
            onChange={(e) => field('assumptions', e.target.value)}
          />
          <Textarea
            label="Limitations"
            rows={2}
            value={form.limitations || ''}
            onChange={(e) => field('limitations', e.target.value)}
          />
        </div>
      </fieldset>
      {!readOnly && (
        <div className="flex justify-end gap-2 mt-5">
          <Button variant="secondary" disabled={busy} onClick={onSave}>
            Save Draft
          </Button>
          {canComplete && (
            <Button disabled={busy} onClick={onComplete}>
              Complete Analysis
            </Button>
          )}
        </div>
      )}
    </SectionCard>
  )
}

function PlanForm({
  form,
  setForm,
  plan,
  readOnly,
  busy,
  onSave,
  onSubmit,
}: {
  form: SolutionPlanPayload
  setForm: (value: SolutionPlanPayload) => void
  plan: SolutionPlanRecord | null
  readOnly: boolean
  busy: boolean
  onSave: () => void
  onSubmit: () => void
}) {
  const field = (key: keyof SolutionPlanPayload, value: unknown) => setForm({ ...form, [key]: value })
  const revision = plan?.status === 'revision_requested'
  const steps = form.implementation_steps
  const changeStep = (index: number, description: string) =>
    field(
      'implementation_steps',
      steps.map((step, i) => (i === index ? { ...step, description } : step)),
    )
  const move = (index: number, delta: number) => {
    const copy = [...steps]
    const target = index + delta
    if (target < 0 || target >= copy.length) return
    ;[copy[index], copy[target]] = [copy[target], copy[index]]
    field(
      'implementation_steps',
      copy.map((step, i) => ({ ...step, order: i + 1 })),
    )
  }
  return (
    <SectionCard title={`Solution Plan${plan ? ` · v${plan.version} · ${plan.status.replace('_', ' ')}` : ''}`}>
      {revision && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
          <p className="text-sm font-semibold text-amber-900">Revisi diminta oleh IT Lead</p>
          <p className="text-sm text-amber-800 mt-1 whitespace-pre-wrap">{plan.review_notes}</p>
          <p className="text-xs text-amber-700 mt-2">Ubah form di bawah lalu Save Draft untuk membuat versi baru.</p>
        </div>
      )}
      <fieldset disabled={(readOnly && !revision) || busy} className="space-y-4">
        <Textarea
          label="Solution Summary *"
          rows={3}
          value={form.solution_summary}
          onChange={(e) => field('solution_summary', e.target.value)}
        />
        <div>
          <p className="text-sm font-medium text-gray-700 mb-2">Implementation Steps *</p>
          <div className="space-y-2">
            {steps.map((step, index) => (
              <div key={index} className="flex items-start gap-2">
                <span className="mt-2 text-xs font-semibold text-gray-500 w-5">{index + 1}.</span>
                <Textarea rows={2} value={step.description} onChange={(e) => changeStep(index, e.target.value)} />
                <div className="flex flex-col gap-1">
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={index === 0}
                    onClick={() => move(index, -1)}
                  >
                    ↑
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={index === steps.length - 1}
                    onClick={() => move(index, 1)}
                  >
                    ↓
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="danger"
                    disabled={steps.length === 1}
                    onClick={() =>
                      field(
                        'implementation_steps',
                        steps.filter((_, i) => i !== index).map((item, i) => ({ ...item, order: i + 1 })),
                      )
                    }
                  >
                    ×
                  </Button>
                </div>
              </div>
            ))}
          </div>
          <Button
            type="button"
            size="sm"
            variant="secondary"
            className="mt-2"
            onClick={() => field('implementation_steps', [...steps, { order: steps.length + 1, description: '' }])}
          >
            + Tambah Step
          </Button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input
            label="Dependencies"
            value={form.dependencies.join(', ')}
            onChange={(e) => field('dependencies', csv(e.target.value))}
          />
          <Input
            label="Affected Components"
            value={form.affected_components.join(', ')}
            onChange={(e) => field('affected_components', csv(e.target.value))}
          />
          <Input
            label="Effort (menit) *"
            type="number"
            min={1}
            value={form.estimated_effort_minutes}
            onChange={(e) => field('estimated_effort_minutes', Number(e.target.value))}
            hint={effort(form.estimated_effort_minutes)}
          />
          <Select
            label="Risk Level *"
            value={form.risk_level}
            onChange={(e) => field('risk_level', e.target.value)}
            options={['low', 'medium', 'high', 'critical'].map((value) => ({
              value,
              label: value[0].toUpperCase() + value.slice(1),
            }))}
          />
        </div>
        <Textarea
          label="Risk Description"
          rows={2}
          value={form.risk_description || ''}
          onChange={(e) => field('risk_description', e.target.value)}
        />
        <Textarea
          label="Rollback Plan"
          rows={3}
          value={form.rollback_plan || ''}
          onChange={(e) => field('rollback_plan', e.target.value)}
          hint="Wajib untuk risiko High/Critical"
        />
        <Textarea
          label="Testing Plan *"
          rows={3}
          value={form.testing_plan}
          onChange={(e) => field('testing_plan', e.target.value)}
        />
        <Textarea
          label="Deployment Consideration"
          rows={2}
          value={form.deployment_consideration || ''}
          onChange={(e) => field('deployment_consideration', e.target.value)}
        />
      </fieldset>
      {(!readOnly || revision) && (
        <div className="flex justify-end gap-2 mt-5">
          <Button variant="secondary" disabled={busy} onClick={onSave}>
            {revision ? 'Buat Versi Revisi' : 'Save Draft'}
          </Button>
          {plan?.status === 'draft' && (
            <Button disabled={busy} onClick={onSubmit}>
              Submit ke IT Lead
            </Button>
          )}
        </div>
      )}
    </SectionCard>
  )
}

const csv = (value: string) =>
  value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
const effort = (minutes: number) =>
  minutes >= 480 ? `${(minutes / 480).toFixed(1)} hari kerja` : `${(minutes / 60).toFixed(1)} jam`
const normalizeSteps = (payload: SolutionPlanPayload): SolutionPlanPayload => ({
  ...payload,
  implementation_steps: payload.implementation_steps.map((step, index) => ({
    order: index + 1,
    description: step.description,
  })),
})
const analysisPayload = (value: TicketAnalysisRecord): AnalysisPayload => ({
  problem_summary: value.problem_summary,
  root_cause: value.root_cause,
  technical_impact: value.technical_impact,
  business_impact: value.business_impact,
  affected_components: value.affected_components,
  evidence: value.evidence,
  assumptions: value.assumptions,
  limitations: value.limitations,
})
const planPayload = (value: SolutionPlanRecord): SolutionPlanPayload => ({
  solution_summary: value.solution_summary,
  implementation_steps: value.implementation_steps,
  affected_components: value.affected_components,
  dependencies: value.dependencies,
  estimated_effort_minutes: value.estimated_effort_minutes,
  risk_level: value.risk_level,
  risk_description: value.risk_description,
  rollback_plan: value.rollback_plan,
  testing_plan: value.testing_plan,
  deployment_consideration: value.deployment_consideration,
})
function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="font-medium text-sm">{value}</p>
    </div>
  )
}
