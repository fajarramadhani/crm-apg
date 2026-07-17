import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  ticketService,
  type TicketRecord,
  type QaTestCaseRecord,
  type QaTestRunRecord,
} from '../../services/ticketService'
import { PageHeader, Button, SectionCard, Toast, StatusBadge } from '../../components/ui'

export default function TestingForm() {
  const navigate = useNavigate()
  const location = useLocation()
  const ticketId = location.state?.ticketId || Number(new URLSearchParams(location.search).get('ticket_id'))

  const [ticket, setTicket] = useState<TicketRecord | null>(null)
  const [cases, setCases] = useState<QaTestCaseRecord[]>([])
  const [runs, setRuns] = useState<QaTestRunRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [toast, setToast] = useState('')

  // Modals / forms state
  const [showCaseModal, setShowCaseModal] = useState(false)
  const [caseForm, setCaseForm] = useState({
    case_number: '',
    title: '',
    test_type: 'functional',
    preconditions: '',
    steps: '',
    expected_result: '',
    priority: 'medium',
  })

  const [showDefectModal, setShowDefectModal] = useState(false)
  const [defectForm, setDefectForm] = useState({
    qa_test_case_id: 0,
    title: '',
    description: '',
    severity: 'major',
    priority: 'medium',
    steps_to_reproduce: '',
    expected_result: '',
    actual_result: '',
  })

  // Action states
  const [recordingResults, setRecordingResults] = useState<
    Record<number, { status: string; actual_result: string; notes: string }>
  >({})
  const [submittingRun, setSubmittingRun] = useState(false)
  const [defectLoading, setDefectLoading] = useState(false)

  const loadData = () => {
    if (!ticketId) {
      setError('ID Tiket tidak valid.')
      setLoading(false)
      return
    }
    setLoading(true)
    Promise.all([ticketService.get(ticketId), ticketService.qaTestCases(ticketId), ticketService.qaTestRuns(ticketId)])
      .then(([tRes, cRes, rRes]) => {
        setTicket(tRes)
        setCases(cRes)
        setRuns(rRes)

        // Initialize recording results from active run if any
        const active = rRes.find((r) => r.status === 'in_progress')
        if (active && active.results) {
          const initials: typeof recordingResults = {}
          active.results.forEach((res) => {
            initials[res.qa_test_case_id] = {
              status: res.status,
              actual_result: res.actual_result || '',
              notes: res.notes || '',
            }
          })
          setRecordingResults(initials)
        }
      })
      .catch((err) => {
        setError(err.message || 'Gagal memuat detail pengujian.')
      })
      .finally(() => {
        setLoading(false)
      })
  }

  useEffect(() => {
    loadData()
  }, [ticketId])

  const activeRun = runs.find((r) => r.status === 'in_progress')

  const handleStartRun = () => {
    ticketService
      .startQaTestRun(ticketId, { environment: 'staging' })
      .then(() => {
        setToast('Test Run baru dimulai.')
        loadData()
      })
      .catch((err) => setError(err.message || 'Gagal memulai test run.'))
  }

  const handleCreateCase = (e: React.FormEvent) => {
    e.preventDefault()
    ticketService
      .createQaTestCase(ticketId, {
        ...caseForm,
        steps: caseForm.steps.split('\n').filter((s) => s.trim() !== ''),
      })
      .then(() => {
        setShowCaseModal(false)
        setToast('Test Case berhasil ditambahkan.')
        setCaseForm({
          case_number: '',
          title: '',
          test_type: 'functional',
          preconditions: '',
          steps: '',
          expected_result: '',
          priority: 'medium',
        })
        loadData()
      })
      .catch((err) => setError(err.message || 'Gagal menambahkan test case.'))
  }

  const handleRecordResult = (caseId: number, status: 'passed' | 'failed' | 'blocked') => {
    if (!activeRun) return

    const currentRecord = recordingResults[caseId] || { actual_result: '', notes: '' }

    // Save locally
    setRecordingResults((prev) => ({
      ...prev,
      [caseId]: {
        ...currentRecord,
        status,
      },
    }))

    // Save to server
    ticketService
      .recordQaTestResult(ticketId, activeRun.id, {
        qa_test_case_id: caseId,
        status,
        actual_result: currentRecord.actual_result || undefined,
        notes: currentRecord.notes || undefined,
      })
      .then(() => {
        if (status === 'failed') {
          // Open defect modal
          setDefectForm((prev) => ({
            ...prev,
            qa_test_case_id: caseId,
            title: `Defect on: ${cases.find((c) => c.id === caseId)?.title || 'Test Case'}`,
            expected_result: cases.find((c) => c.id === caseId)?.expected_result || '',
            actual_result: currentRecord.actual_result || '',
          }))
          setShowDefectModal(true)
        } else {
          setToast('Hasil uji berhasil direkam.')
        }
      })
      .catch((err) => setError(err.message || 'Gagal merekam hasil uji.'))
  }

  const handleRecordDetailsChange = (caseId: number, field: 'actual_result' | 'notes', val: string) => {
    setRecordingResults((prev) => {
      const current = prev[caseId] || { status: 'not_run', actual_result: '', notes: '' }
      return {
        ...prev,
        [caseId]: {
          ...current,
          [field]: val,
        },
      }
    })
  }

  const handleCreateDefect = (e: React.FormEvent) => {
    e.preventDefault()
    if (!activeRun) return
    setDefectLoading(true)
    ticketService
      .createQaDefect(ticketId, {
        qa_test_run_id: activeRun.id,
        ...defectForm,
      })
      .then(() => {
        setShowDefectModal(false)
        setToast('Defect berhasil dilaporkan.')
        // Reload ticket details so the defects list is refreshed
        loadData()
      })
      .catch((err) => setError(err.message || 'Gagal melaporkan defect.'))
      .finally(() => setDefectLoading(false))
  }

  const handleCompleteRun = () => {
    if (!activeRun) return
    setSubmittingRun(true)
    ticketService
      .completeQaTestRun(ticketId, activeRun.id, 'Test run selesai')
      .then(() => {
        setToast('Test Run berhasil diselesaikan.')
        setTimeout(() => navigate('/qa/dashboard'), 1500)
      })
      .catch((err) => {
        setError(err.message || 'Gagal menyelesaikan test run.')
      })
      .finally(() => setSubmittingRun(false))
  }

  const handleVerifyDefect = (defectId: number, status: 'verified' | 'reopened') => {
    ticketService
      .verifyQaDefect(ticketId, defectId, { status, notes: `Verified by QA: ${status}` })
      .then(() => {
        setToast(`Defect status updated to: ${status}`)
        loadData()
      })
      .catch((err) => setError(err.message || 'Gagal memverifikasi defect.'))
  }

  if (loading) {
    return <p className="py-16 text-center text-sm text-gray-500">Memuat lembar pengujian QA...</p>
  }

  if (!ticket) {
    return <p className="py-16 text-center text-sm text-red-500">Tiket tidak ditemukan.</p>
  }

  const activeCases = cases.filter((c) => c.is_active)

  return (
    <div className="max-w-5xl">
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}

      <PageHeader
        title={`QA Workspace — ${ticket.ticket_number}`}
        subtitle={ticket.title}
        actions={
          <Button variant="ghost" onClick={() => navigate('/qa/dashboard')}>
            ← Dashboard
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div className="lg:col-span-2 space-y-6">
          {/* Active Test Run Area */}
          <SectionCard
            title={
              activeRun
                ? `Active Test Run (Run #${activeRun.run_number} · Cycle ${activeRun.cycle_number})`
                : 'Test Execution Queue'
            }
            actions={
              !activeRun && activeCases.length > 0 ? (
                <Button size="sm" variant="primary" onClick={handleStartRun}>
                  🚀 Mulai Test Run Baru
                </Button>
              ) : undefined
            }
          >
            {!activeRun ? (
              <div className="text-center py-10">
                <div className="text-4xl mb-3">📋</div>
                <p className="text-sm text-gray-600 font-medium">Belum ada Test Run yang aktif.</p>
                {activeCases.length === 0 ? (
                  <p className="text-xs text-gray-400 mt-1">
                    Harap tambahkan minimal 1 Test Case aktif terlebih dahulu.
                  </p>
                ) : (
                  <p className="text-xs text-gray-400 mt-1">Klik tombol di atas untuk memulai test execution cycle.</p>
                )}
              </div>
            ) : (
              <div className="space-y-4">
                <div className="flex items-center justify-between text-xs text-gray-500 font-medium pb-2 border-b">
                  <span>
                    Environment: <b className="text-gray-700">{activeRun.environment}</b>
                  </span>
                  <span>
                    Mulai: <b className="text-gray-700">{new Date(activeRun.started_at).toLocaleTimeString('id-ID')}</b>
                  </span>
                </div>

                <div className="space-y-4 divide-y divide-gray-100">
                  {activeCases.map((tc, idx) => {
                    const rec = recordingResults[tc.id] || { status: 'not_run', actual_result: '', notes: '' }
                    return (
                      <div key={tc.id} className="pt-4 first:pt-0">
                        <div className="flex items-start gap-2 mb-2">
                          <span className="text-xs font-bold text-gray-400 w-5 shrink-0 mt-0.5">{idx + 1}</span>
                          <div className="flex-1">
                            <span className="text-2xs font-bold uppercase text-gray-400 tracking-wider font-mono">
                              {tc.case_number} · {tc.test_type}
                            </span>
                            <h4 className="text-sm font-semibold text-gray-900">{tc.title}</h4>
                            {tc.preconditions && (
                              <p className="text-2xs text-gray-500 mt-0.5">
                                <b>Prasyarat:</b> {tc.preconditions}
                              </p>
                            )}
                          </div>
                        </div>

                        <div className="bg-gray-50 rounded-lg p-2.5 mb-3 ml-7 text-xs leading-relaxed text-gray-700">
                          <p className="font-semibold text-gray-500 mb-0.5">Langkah Uji:</p>
                          <ol className="list-decimal pl-4 space-y-0.5">
                            {tc.steps.map((s, sIdx) => (
                              <li key={sIdx}>{s}</li>
                            ))}
                          </ol>
                          <p className="font-semibold text-gray-500 mt-2 mb-0.5">Hasil yang Diharapkan:</p>
                          <p>{tc.expected_result}</p>
                        </div>

                        <div className="ml-7 space-y-2">
                          <div className="flex items-center gap-2">
                            <span className="text-xs text-gray-500 font-medium">Hasil:</span>
                            {(['passed', 'failed', 'blocked'] as const).map((s) => (
                              <button
                                key={s}
                                onClick={() => handleRecordResult(tc.id, s)}
                                className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition-all ${
                                  rec.status === s
                                    ? s === 'passed'
                                      ? 'bg-emerald-500 text-white border-emerald-500'
                                      : s === 'failed'
                                        ? 'bg-red-500 text-white border-red-500'
                                        : 'bg-yellow-500 text-white border-yellow-500'
                                    : 'bg-white text-gray-500 border-gray-300 hover:border-gray-400'
                                }`}
                              >
                                {s === 'passed' ? '✓ Pass' : s === 'failed' ? '✕ Fail' : '— Blocked'}
                              </button>
                            ))}
                          </div>

                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input
                              type="text"
                              placeholder="Hasil aktual..."
                              value={rec.actual_result}
                              onChange={(e) => handleRecordDetailsChange(tc.id, 'actual_result', e.target.value)}
                              className="text-xs rounded-lg border border-gray-300 px-3 py-1.5"
                            />
                            <input
                              type="text"
                              placeholder="Catatan tambahan..."
                              value={rec.notes}
                              onChange={(e) => handleRecordDetailsChange(tc.id, 'notes', e.target.value)}
                              className="text-xs rounded-lg border border-gray-300 px-3 py-1.5"
                            />
                          </div>
                        </div>
                      </div>
                    )
                  })}
                </div>

                <div className="flex justify-end pt-4 border-t">
                  <Button variant="success" disabled={submittingRun} onClick={handleCompleteRun}>
                    {submittingRun ? 'Menyimpan...' : '📤 Selesaikan Test Run & Submit Hasil'}
                  </Button>
                </div>
              </div>
            )}
          </SectionCard>

          {/* QA Defect Tracking */}
          <SectionCard title="QA Defect Tracking">
            {!ticket.qa_defects || ticket.qa_defects.length === 0 ? (
              <p className="text-sm text-gray-500 text-center py-6">Belum ada defect yang dilaporkan.</p>
            ) : (
              <div className="space-y-3">
                {ticket.qa_defects.map((d) => (
                  <div key={d.id} className="border border-gray-200 rounded-xl p-4 bg-white">
                    <div className="flex justify-between items-start mb-2">
                      <div>
                        <span className="font-mono text-2xs text-gray-400">{d.defect_number}</span>
                        <h4 className="text-sm font-bold text-gray-800">{d.title}</h4>
                      </div>
                      <span
                        className={`px-2 py-0.5 text-xs font-bold rounded uppercase ${
                          d.status === 'open' || d.status === 'reopened'
                            ? 'bg-red-100 text-red-800'
                            : d.status === 'resolved'
                              ? 'bg-green-100 text-green-800'
                              : d.status === 'verified'
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-gray-100 text-gray-800'
                        }`}
                      >
                        {d.status}
                      </span>
                    </div>

                    <p className="text-xs text-gray-600 mb-3">{d.description}</p>

                    <div className="grid grid-cols-2 gap-2 text-2xs text-gray-500 mb-3 font-medium bg-gray-50 p-2 rounded-lg">
                      <p>
                        <b>Severity:</b> {d.severity}
                      </p>
                      <p>
                        <b>Priority:</b> {d.priority}
                      </p>
                      {d.resolution_notes && (
                        <p className="col-span-2 mt-1 text-green-700">
                          <b>Resolution:</b> {d.resolution_notes}
                        </p>
                      )}
                    </div>

                    {d.status === 'resolved' && (
                      <div className="flex gap-2 justify-end border-t pt-3">
                        <button
                          onClick={() => handleVerifyDefect(d.id, 'reopened')}
                          className="px-2.5 py-1 text-2xs font-bold text-red-700 bg-red-50 hover:bg-red-100 rounded"
                        >
                          Reopen Defect
                        </button>
                        <button
                          onClick={() => handleVerifyDefect(d.id, 'verified')}
                          className="px-2.5 py-1 text-2xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded"
                        >
                          Verify Fix
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        <div className="space-y-4">
          {/* Ticket Information Card */}
          <SectionCard title="Detail Tiket">
            <div className="text-xs space-y-2 leading-relaxed">
              <p>
                <b>Aplikasi:</b> {ticket.application?.name || '—'}
              </p>
              <p>
                <b>PIC Assignee:</b> {ticket.assignee?.name || '—'}
              </p>
              <p>
                <b>Prioritas:</b> {ticket.final_priority?.name || '—'}
              </p>
              <p>
                <b>Status Tiket:</b> <StatusBadge status={ticket.status} />
              </p>
              <p>
                <b>Cycle QA:</b> Cycle {ticket.qa_cycle_number || 1}
              </p>
            </div>
          </SectionCard>

          {/* Test Case Management */}
          <SectionCard
            title={`Test Cases (${activeCases.length})`}
            actions={
              <Button size="sm" variant="primary" onClick={() => setShowCaseModal(true)}>
                ＋ Tambah
              </Button>
            }
          >
            {activeCases.length === 0 ? (
              <p className="text-xs text-gray-500 text-center py-4">Belum ada test case.</p>
            ) : (
              <div className="space-y-2">
                {activeCases.map((c) => (
                  <div key={c.id} className="border border-gray-100 rounded-lg p-2.5 bg-white text-xs">
                    <div className="flex justify-between items-center">
                      <b className="font-mono text-2xs text-[#1E3A8A]">{c.case_number}</b>
                      <span className="text-2xs text-gray-400 font-semibold">{c.test_type}</span>
                    </div>
                    <p className="font-medium text-gray-700 mt-1 truncate">{c.title}</p>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>
      </div>

      {/* CREATE TEST CASE MODAL */}
      {showCaseModal && (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto space-y-4">
            <div className="flex justify-between items-center border-b pb-2">
              <h3 className="text-base font-bold text-gray-900">Tambah Test Case Baru</h3>
              <button
                onClick={() => setShowCaseModal(false)}
                className="text-gray-400 hover:text-gray-600 text-xl font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateCase} className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Nomor Case</label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: TC-01"
                    value={caseForm.case_number}
                    onChange={(e) => setCaseForm((prev) => ({ ...prev, case_number: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Tipe Test</label>
                  <select
                    value={caseForm.test_type}
                    onChange={(e) => setCaseForm((prev) => ({ ...prev, test_type: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-2"
                  >
                    <option value="functional">Functional</option>
                    <option value="integration">Integration</option>
                    <option value="performance">Performance</option>
                    <option value="security">Security</option>
                    <option value="regression">Regression</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Judul Skenario</label>
                <input
                  type="text"
                  required
                  placeholder="Verifikasi modul X berjalan..."
                  value={caseForm.title}
                  onChange={(e) => setCaseForm((prev) => ({ ...prev, title: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Prasyarat / Preconditions</label>
                <input
                  type="text"
                  placeholder="Opsional..."
                  value={caseForm.preconditions}
                  onChange={(e) => setCaseForm((prev) => ({ ...prev, preconditions: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Langkah Uji (Satu per baris)</label>
                <textarea
                  required
                  rows={3}
                  placeholder="Langkah 1&#10;Langkah 2&#10;Langkah 3"
                  value={caseForm.steps}
                  onChange={(e) => setCaseForm((prev) => ({ ...prev, steps: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Hasil yang Diharapkan</label>
                <input
                  type="text"
                  required
                  placeholder="Ekspektasi sistem..."
                  value={caseForm.expected_result}
                  onChange={(e) => setCaseForm((prev) => ({ ...prev, expected_result: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Prioritas Skenario</label>
                <select
                  value={caseForm.priority}
                  onChange={(e) => setCaseForm((prev) => ({ ...prev, priority: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-2"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t">
                <Button variant="secondary" onClick={() => setShowCaseModal(false)}>
                  Batal
                </Button>
                <Button variant="primary" type="submit">
                  Simpan Skenario
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* REPORT DEFECT MODAL */}
      {showDefectModal && (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto space-y-4">
            <div className="flex justify-between items-center border-b pb-2">
              <h3 className="text-base font-bold text-gray-900">Laporkan Temuan Bug / Defect</h3>
              <button
                onClick={() => setShowDefectModal(false)}
                className="text-gray-400 hover:text-gray-600 text-xl font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleCreateDefect} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Judul Defect</label>
                <input
                  type="text"
                  required
                  placeholder="Judul deskriptif temuan..."
                  value={defectForm.title}
                  onChange={(e) => setDefectForm((prev) => ({ ...prev, title: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Deskripsi & Temuan Detail</label>
                <textarea
                  required
                  rows={2}
                  placeholder="Informasi detail mengenai defect..."
                  value={defectForm.description}
                  onChange={(e) => setDefectForm((prev) => ({ ...prev, description: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Severity</label>
                  <select
                    value={defectForm.severity}
                    onChange={(e) => setDefectForm((prev) => ({ ...prev, severity: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-2"
                  >
                    <option value="minor">Minor</option>
                    <option value="major">Major</option>
                    <option value="critical">Critical</option>
                    <option value="blocker">Blocker</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Prioritas</label>
                  <select
                    value={defectForm.priority}
                    onChange={(e) => setDefectForm((prev) => ({ ...prev, priority: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 bg-white px-3 py-2"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Langkah Reproduksi Bug</label>
                <textarea
                  rows={2}
                  placeholder="Langkah-langkah mereproduksi defect..."
                  value={defectForm.steps_to_reproduce}
                  onChange={(e) => setDefectForm((prev) => ({ ...prev, steps_to_reproduce: e.target.value }))}
                  className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Ekspektasi Uji</label>
                  <input
                    type="text"
                    placeholder="Hasil yang diharapkan..."
                    value={defectForm.expected_result}
                    onChange={(e) => setDefectForm((prev) => ({ ...prev, expected_result: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-700 mb-1">Hasil Aktual</label>
                  <input
                    type="text"
                    placeholder="Temuan di sistem..."
                    value={defectForm.actual_result}
                    onChange={(e) => setDefectForm((prev) => ({ ...prev, actual_result: e.target.value }))}
                    className="w-full text-sm rounded-lg border border-gray-300 px-3 py-2"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t">
                <Button variant="secondary" onClick={() => setShowDefectModal(false)} disabled={defectLoading}>
                  Batal
                </Button>
                <Button variant="danger" type="submit" disabled={defectLoading}>
                  {defectLoading ? 'Menyimpan...' : 'Laporkan Defect'}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
