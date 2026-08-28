import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Check, X, AlertTriangle } from 'lucide-react'
import {
  PageHeader,
  Button,
  StatusBadge,
  PriorityBadge,
  SectionCard,
  Textarea,
  Toast,
  Modal,
  Input,
  Select,
  EmptyState,
} from '../../components/ui'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import { ApiRequestError } from '../../api/client'

export default function UAT() {
  const navigate = useNavigate()
  const ticketId = Number(new URLSearchParams(window.location.search).get('ticket_id'))

  const [ticket, setTicket] = useState<TicketRecord | null>(null)
  const [assignedTickets, setAssignedTickets] = useState<TicketRecord[]>([])
  const [scenarios, setScenarios] = useState<any[]>([])
  const [findings, setFindings] = useState<any[]>([])

  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [toast, setToast] = useState('')

  // UAT Execution states
  const [activeRun, setActiveRun] = useState<any | null>(null)
  const [results, setResults] = useState<Record<number, 'accepted' | 'rejected' | 'blocked'>>({})
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [actualResults, setActualResults] = useState<Record<number, string>>({})

  // Modal states
  const [showConfirm, setShowConfirm] = useState(false)
  const [showFindingModal, setShowFindingModal] = useState(false)
  const [showScenarioModal, setShowScenarioModal] = useState(false)

  // UAT Finding Form states
  const [findingTitle, setFindingTitle] = useState('')
  const [findingDescription, setFindingDescription] = useState('')
  const [findingImpact, setFindingImpact] = useState('')
  const [findingSeverity, setFindingSeverity] = useState('major')
  const [linkedScenarioId, setLinkedScenarioId] = useState<number | null>(null)

  // UAT Scenario Form states
  const [scenarioNumber, setScenarioNumber] = useState('')
  const [scenarioTitle, setScenarioTitle] = useState('')
  const [scenarioObjective, setScenarioObjective] = useState('')
  const [scenarioSteps, setScenarioSteps] = useState('')
  const [scenarioExpected, setScenarioExpected] = useState('')
  const [scenarioCriteria, setScenarioCriteria] = useState('')
  const [scenarioPriority, setScenarioPriority] = useState('medium')

  const loadData = () => {
    if (!ticketId) {
      setLoading(true)
      ticketService
        .requesterUatAssignments()
        .then((list) => {
          setAssignedTickets(list)
          if (list.length === 1) {
            navigate(`/user/uat?ticket_id=${list[0].id}`, { replace: true })
          }
        })
        .catch((cause) => {
          setError((cause as ApiRequestError).message || 'Gagal memuat antrian UAT.')
        })
        .finally(() => setLoading(false))
      return
    }
    setLoading(true)
    Promise.all([
      ticketService.get(ticketId),
      ticketService.getUatScenarios(ticketId),
      ticketService.getUatRuns(ticketId),
      ticketService.getUatFindings(ticketId),
    ])
      .then(([t, s, r, f]) => {
        setTicket(t)
        setScenarios(s)
        setFindings(f)

        const active = r.find((run) => run.status === 'in_progress')
        if (active) {
          setActiveRun(active)
          const initialResults: Record<number, 'accepted' | 'rejected' | 'blocked'> = {}
          const initialNotes: Record<number, string> = {}
          const initialActual: Record<number, string> = {}
          active.results.forEach((res: any) => {
            initialResults[res.uat_scenario_id] = res.status
            initialNotes[res.uat_scenario_id] = res.notes || ''
            initialActual[res.uat_scenario_id] = res.actual_result || ''
          })
          setResults(initialResults)
          setNotes(initialNotes)
          setActualResults(initialActual)
        } else {
          setActiveRun(null)
          setResults({})
          setNotes({})
          setActualResults({})
        }
      })
      .catch((cause) => {
        setError('Gagal memuat data UAT: ' + (cause as ApiRequestError).message)
      })
      .finally(() => setLoading(false))
  }

  useEffect(loadData, [ticketId])

  const handleStartUat = async () => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      const updated = await ticketService.startUat(ticket.id)
      setTicket(updated)
      setToast('UAT berhasil dimulai! Silakan jalankan skenario pengujian.')
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const handleCreateScenario = async () => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      await ticketService.createUatScenario(ticket.id, {
        scenario_number: scenarioNumber,
        title: scenarioTitle,
        business_objective: scenarioObjective,
        steps: scenarioSteps.split('\n').filter((s) => s.trim()),
        expected_result: scenarioExpected,
        acceptance_criteria: scenarioCriteria.split('\n').filter((s) => s.trim()),
        priority: scenarioPriority,
      })
      setToast('Skenario UAT berhasil ditambahkan.')
      setShowScenarioModal(false)
      resetScenarioForm()
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const resetScenarioForm = () => {
    setScenarioNumber('')
    setScenarioTitle('')
    setScenarioObjective('')
    setScenarioSteps('')
    setScenarioExpected('')
    setScenarioCriteria('')
    setScenarioPriority('medium')
  }

  const handleStartUatRun = async () => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      const run = await ticketService.startUatRun(ticket.id, { environment: 'staging' })
      setActiveRun(run)
      setToast('UAT run baru dimulai.')
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const handleRecordResult = async (scenarioId: number, status: 'accepted' | 'rejected' | 'blocked') => {
    if (!ticket || !activeRun) return
    setError('')
    const noteVal = notes[scenarioId] || ''
    const actualVal = actualResults[scenarioId] || ''

    if (['rejected', 'blocked'].includes(status) && !noteVal.trim() && !actualVal.trim()) {
      setError('Hasil rejected atau blocked membutuhkan catatan atau hasil aktual.')
      return
    }

    try {
      await ticketService.recordUatResult(ticket.id, activeRun.id, {
        uat_scenario_id: scenarioId,
        status,
        actual_result: actualVal || undefined,
        notes: noteVal || undefined,
      })
      setResults((prev) => ({ ...prev, [scenarioId]: status }))
      setToast('Hasil skenario berhasil disimpan.')
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    }
  }

  const handleCreateFinding = async () => {
    if (!ticket || !activeRun) return
    setBusy(true)
    setError('')
    try {
      await ticketService.createUatFinding(ticket.id, {
        uat_run_id: activeRun.id,
        uat_scenario_id: linkedScenarioId as number,
        title: findingTitle,
        description: findingDescription,
        business_impact: findingImpact,
        severity: findingSeverity,
      })
      setToast('Temuan UAT (finding) berhasil dilaporkan.')
      setShowFindingModal(false)
      resetFindingForm()
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const resetFindingForm = () => {
    setFindingTitle('')
    setFindingDescription('')
    setFindingImpact('')
    setFindingSeverity('major')
    setLinkedScenarioId(null)
  }

  const handleCompleteRun = async () => {
    if (!ticket || !activeRun) return
    setBusy(true)
    setError('')
    try {
      await ticketService.completeUatRun(ticket.id, activeRun.id, 'UAT Run Completed.')
      setToast('Sesi pengujian UAT diselesaikan.')
      setShowConfirm(false)
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const handleVerifyFinding = async (findingId: number) => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      await ticketService.verifyUatFinding(ticket.id, findingId, 'Verified OK.')
      setToast('Temuan berhasil diverifikasi (verified).')
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const handleReopenFinding = async (findingId: number) => {
    if (!ticket) return
    setBusy(true)
    setError('')
    try {
      await ticketService.reopenUatFinding(ticket.id, findingId, 'Reopened.')
      setToast('Temuan dibuka kembali (reopened).')
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const handleUploadEvidence = async (file?: File, findingId?: number) => {
    if (!ticket || !file) return
    setBusy(true)
    setError('')
    try {
      await ticketService.uploadUatEvidence(
        ticket.id,
        file,
        findingId ? 'uat_finding_evidence' : 'uat_evidence',
        findingId,
      )
      setToast('Bukti file UAT berhasil diunggah.')
      loadData()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return (
      <div className="py-20 text-center text-sm text-gray-500" role="status">
        Memuat data UAT...
      </div>
    )
  }

  if (!ticketId) {
    return (
      <div className="max-w-4xl mx-auto pb-10">
        {error && <Toast message={error} type="error" onClose={() => setError('')} />}
        <PageHeader
          title="Antrean UAT Requester"
          subtitle="Daftar tiket yang membutuhkan pengujian dan persetujuan (sign-off) UAT dari Anda"
        />
        <SectionCard title={`Tiket Perlu UAT (${assignedTickets.length})`}>
          {assignedTickets.length === 0 ? (
            <EmptyState
              title="Belum ada tiket UAT"
              message="Tidak ada tiket yang membutuhkan persetujuan UAT saat ini."
            />
          ) : (
            <div className="space-y-3">
              {assignedTickets.map((t) => (
                <div
                  key={t.id}
                  className="flex flex-wrap items-center justify-between gap-4 border rounded-xl p-4 hover:border-blue-400 bg-white"
                >
                  <div>
                    <span className="font-mono text-xs text-gray-500">{t.ticket_number}</span>
                    <p className="font-semibold text-sm text-gray-900">{t.title}</p>
                    <p className="text-xs text-gray-500 mt-1">
                      Ditugaskan untuk UAT:{' '}
                      {t.uat_assigned_at ? new Date(t.uat_assigned_at).toLocaleString('id-ID') : '—'}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    {t.final_priority && <PriorityBadge priority={t.final_priority.key} />}
                    <StatusBadge status={t.status} />
                    <Button variant="primary" size="sm" onClick={() => navigate(`/user/uat?ticket_id=${t.id}`)}>
                      Jalankan UAT →
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </SectionCard>
      </div>
    )
  }

  if (!ticket) {
    return (
      <div className="py-20 text-center">
        <p className="text-red-700">{error || 'Tiket tidak ditemukan.'}</p>
        <Button variant="secondary" onClick={() => navigate('/user/dashboard')}>
          Kembali ke Dashboard
        </Button>
      </div>
    )
  }

  const allScenariosRun = scenarios.length > 0 && scenarios.every((s) => results[s.id])
  const failedScenariosCount = Object.values(results).filter((r) => ['rejected', 'blocked'].includes(r)).length

  return (
    <div className="max-w-4xl mx-auto pb-10">
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}

      <PageHeader
        title="UAT Workspace"
        subtitle={`Nomor Tiket: ${ticket.ticket_number}`}
        actions={
          <Button variant="ghost" onClick={() => navigate(`/user/tickets/${ticket.id}`)}>
            ← Kembali ke Detail
          </Button>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* Kolom Kiri: Skenario & Eksekusi */}
        <div className="lg:col-span-2 space-y-5">
          <SectionCard title="Status Pengujian">
            <div className="flex justify-between items-center mb-4">
              <div>
                <p className="text-xs text-gray-500">Status Tiket Saat Ini</p>
                <div className="mt-1">
                  <StatusBadge status={ticket.status} />
                </div>
              </div>
              <div className="text-right">
                <p className="text-xs text-gray-500">Cycle UAT Ke</p>
                <p className="text-xl font-bold text-[#1E3A8A]">{ticket.uat_cycle_number}</p>
              </div>
            </div>

            {ticket.status === 'uat_assignment' && (
              <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                <p className="text-sm text-blue-900 mb-3 font-medium">
                  Anda telah ditunjuk sebagai penguji UAT untuk tiket ini.
                </p>
                <Button variant="primary" disabled={busy} onClick={handleStartUat}>
                  Mulai Pengujian UAT
                </Button>
              </div>
            )}

            {ticket.status === 'uat_retest' && (
              <div className="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center">
                <p className="text-sm text-orange-900 mb-3 font-medium">
                  Perbaikan PIC siap diuji ulang. Mulai siklus retest baru.
                </p>
                <Button variant="warning" disabled={busy} onClick={handleStartUat}>
                  Mulai Retest UAT
                </Button>
              </div>
            )}
          </SectionCard>

          {['uat_in_progress'].includes(ticket.status) && (
            <>
              {!activeRun ? (
                <SectionCard title="UAT Run Baru">
                  <div className="text-center py-6 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                    <p className="text-sm text-gray-500 mb-3">Belum ada sesi UAT run yang berjalan saat ini.</p>
                    <Button variant="primary" disabled={busy} onClick={handleStartUatRun}>
                      Mulai Sesi UAT Run Baru
                    </Button>
                  </div>
                </SectionCard>
              ) : (
                <SectionCard title={`Skenario UAT (${scenarios.length})`}>
                  <div className="space-y-4">
                    {scenarios.map((sc) => {
                      const currentStatus = results[sc.id]
                      return (
                        <div key={sc.id} className="border rounded-xl p-4 bg-gray-50 space-y-3">
                          <div className="flex justify-between items-start">
                            <div>
                              <span className="text-xs font-semibold text-gray-400">{sc.scenario_number}</span>
                              <h4 className="font-semibold text-gray-900 text-sm mt-0.5">{sc.title}</h4>
                            </div>
                            <span className="text-xs px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium">
                              {sc.priority}
                            </span>
                          </div>

                          <div className="text-xs space-y-1">
                            <p className="text-gray-500">
                              <strong className="text-gray-700">Tujuan:</strong> {sc.business_objective}
                            </p>
                            <p className="text-gray-500">
                              <strong className="text-gray-700">Langkah:</strong> {sc.steps?.join(' → ')}
                            </p>
                            <p className="text-gray-500">
                              <strong className="text-gray-700">Ekspektasi:</strong> {sc.expected_result}
                            </p>
                            <p className="text-gray-500">
                              <strong className="text-gray-700">Kriteria Penerimaan:</strong>{' '}
                              {sc.acceptance_criteria?.join(', ')}
                            </p>
                          </div>

                          <div className="space-y-2 pt-2 border-t border-gray-200">
                            <div className="grid grid-cols-2 gap-2">
                              <Input
                                label="Hasil Aktual"
                                placeholder="Tulis hasil yang didapat..."
                                value={actualResults[sc.id] || ''}
                                onChange={(e) => setActualResults({ ...actualResults, [sc.id]: e.target.value })}
                              />
                              <Input
                                label="Catatan Tambahan"
                                placeholder="Tambahkan catatan jika gagal/blocked..."
                                value={notes[sc.id] || ''}
                                onChange={(e) => setNotes({ ...notes, [sc.id]: e.target.value })}
                              />
                            </div>

                            <div className="flex gap-2 justify-end pt-1">
                              <Button
                                size="sm"
                                variant={currentStatus === 'accepted' ? 'success' : 'ghost'}
                                onClick={() => handleRecordResult(sc.id, 'accepted')}
                              >
                                <Check className="w-4 h-4 shrink-0" /> Terima (Accept)
                              </Button>
                              <Button
                                size="sm"
                                variant={currentStatus === 'rejected' ? 'danger' : 'ghost'}
                                onClick={() => handleRecordResult(sc.id, 'rejected')}
                              >
                                <X className="w-4 h-4 shrink-0" /> Tolak (Reject)
                              </Button>
                              <Button
                                size="sm"
                                variant={currentStatus === 'blocked' ? 'warning' : 'ghost'}
                                onClick={() => handleRecordResult(sc.id, 'blocked')}
                              >
                                <AlertTriangle className="w-4 h-4 shrink-0" /> Blokir (Block)
                              </Button>
                            </div>
                          </div>
                        </div>
                      )
                    })}

                    <div className="pt-4 border-t border-gray-200 flex justify-between items-center">
                      <Button
                        variant="secondary"
                        onClick={() => {
                          resetFindingForm()
                          setShowFindingModal(true)
                        }}
                      >
                        ＋ Laporkan Temuan (Finding)
                      </Button>
                      <Button
                        variant="primary"
                        disabled={busy || !allScenariosRun}
                        onClick={() => setShowConfirm(true)}
                      >
                        Selesaikan Sesi UAT
                      </Button>
                    </div>
                  </div>
                </SectionCard>
              )}
            </>
          )}

          {/* Skenario UAT List (Read Only if not in progress) */}
          {!['uat_in_progress'].includes(ticket.status) && scenarios.length > 0 && (
            <SectionCard title="Daftar Skenario UAT">
              <div className="space-y-3">
                {scenarios.map((sc) => (
                  <div key={sc.id} className="border rounded-xl p-3 bg-gray-50 flex justify-between items-start">
                    <div>
                      <span className="text-xs font-mono text-gray-400">{sc.scenario_number}</span>
                      <h4 className="font-semibold text-gray-900 text-sm">{sc.title}</h4>
                      <p className="text-xs text-gray-500 mt-1">{sc.business_objective}</p>
                    </div>
                    <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">{sc.priority}</span>
                  </div>
                ))}
              </div>
            </SectionCard>
          )}
        </div>

        {/* Kolom Kanan: Temuan & Evidence */}
        <div className="space-y-5">
          {/* UAT Scenarios Builder (Untuk QA/PIC/Requester) */}
          {['uat_assignment', 'uat_in_progress', 'uat_retest'].includes(ticket.status) && (
            <SectionCard title="Persiapan Skenario UAT">
              <p className="text-xs text-gray-500 mb-3">
                Tambahkan skenario pengujian agar siap dijalankan saat sesi UAT berlangsung.
              </p>
              <Button
                variant="secondary"
                className="w-full"
                onClick={() => {
                  resetScenarioForm()
                  setShowScenarioModal(true)
                }}
              >
                ＋ Tambah Skenario UAT
              </Button>
            </SectionCard>
          )}

          {/* UAT Findings List */}
          <SectionCard title={`Temuan Hasil UAT (${findings.length})`}>
            <div className="space-y-3">
              {findings.length === 0 && <p className="text-xs text-gray-500">Belum ada temuan yang dilaporkan.</p>}
              {findings.map((f) => (
                <div key={f.id} className="border rounded-xl p-3 bg-white space-y-2">
                  <div className="flex justify-between items-start">
                    <div>
                      <span className="text-xs font-mono text-red-500">{f.finding_number}</span>
                      <h5 className="font-semibold text-gray-900 text-xs mt-0.5">{f.title}</h5>
                    </div>
                    <span
                      className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${
                        f.status === 'verified'
                          ? 'bg-green-100 text-green-800'
                          : f.status === 'resolved'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {f.status}
                    </span>
                  </div>
                  <p className="text-xs text-gray-600 line-clamp-2">{f.description}</p>
                  <div className="flex justify-between items-center text-[10px] text-gray-400 pt-1 border-t border-gray-100">
                    <span>Severity: {f.severity}</span>
                    <div className="flex gap-1">
                      {f.status === 'retest' && (
                        <>
                          <button className="text-green-600 hover:underline" onClick={() => handleVerifyFinding(f.id)}>
                            Verify
                          </button>
                          <button className="text-red-600 hover:underline" onClick={() => handleReopenFinding(f.id)}>
                            Reopen
                          </button>
                        </>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </SectionCard>

          {/* Evidence Upload */}
          <SectionCard title="Bukti Berkas UAT">
            <p className="text-xs text-gray-500 mb-3">
              Unggah tangkapan layar atau dokumen sebagai bukti penerimaan pengujian.
            </p>
            <label className="block text-center border border-dashed rounded-lg p-3 text-sm text-blue-700 cursor-pointer">
              ＋ Unggah Bukti UAT
              <input
                className="sr-only"
                type="file"
                accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                onChange={(e) => handleUploadEvidence(e.target.files?.[0])}
              />
            </label>
          </SectionCard>
        </div>
      </div>

      {/* Modal Confirm Selesai UAT */}
      <Modal open={showConfirm} onClose={() => setShowConfirm(false)} title="Selesaikan Sesi UAT">
        <div className="space-y-4 text-sm text-gray-600">
          <p>Apakah Anda yakin ingin menyelesaikan sesi UAT run saat ini?</p>
          <div className="bg-gray-50 p-3 rounded-lg border">
            <p>
              <strong>Total Skenario:</strong> {scenarios.length}
            </p>
            <p className="text-red-600">
              <strong>Skenario Gagal/Blocked:</strong> {failedScenariosCount}
            </p>
            <p className="mt-2 text-xs text-gray-400">
              *Jika ada skenario yang gagal/blocked, tiket akan dikembalikan secara otomatis ke PIC untuk diperbaiki.
            </p>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowConfirm(false)}>
              Batal
            </Button>
            <Button variant="primary" disabled={busy} onClick={handleCompleteRun}>
              Selesaikan
            </Button>
          </div>
        </div>
      </Modal>

      {/* Modal Tambah Finding */}
      <Modal open={showFindingModal} onClose={() => setShowFindingModal(false)} title="Laporkan Temuan UAT">
        <div className="space-y-4">
          <Input label="Judul Temuan" value={findingTitle} onChange={(e) => setFindingTitle(e.target.value)} />
          <Textarea
            label="Deskripsi Detail"
            rows={3}
            value={findingDescription}
            onChange={(e) => setFindingDescription(e.target.value)}
          />
          <Input label="Dampak Bisnis" value={findingImpact} onChange={(e) => setFindingImpact(e.target.value)} />
          <Select
            label="Keparahan (Severity)"
            value={findingSeverity}
            onChange={(e) => setFindingSeverity(e.target.value)}
            options={[
              { value: 'critical', label: 'Critical' },
              { value: 'major', label: 'Major' },
              { value: 'minor', label: 'Minor' },
              { value: 'cosmetic', label: 'Cosmetic' },
            ]}
          />
          <Select
            label="Kaitkan Skenario UAT"
            value={linkedScenarioId || ''}
            onChange={(e) => setLinkedScenarioId(Number(e.target.value) || null)}
            options={[
              { value: '', label: 'Tidak dikaitkan' },
              ...scenarios.map((s) => ({ value: String(s.id), label: `${s.scenario_number} - ${s.title}` })),
            ]}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowFindingModal(false)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={busy || !findingTitle.trim() || !findingDescription.trim() || !linkedScenarioId}
              onClick={handleCreateFinding}
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>

      {/* Modal Tambah Skenario */}
      <Modal open={showScenarioModal} onClose={() => setShowScenarioModal(false)} title="Tambah Skenario UAT">
        <div className="space-y-4">
          <Input
            label="Nomor Skenario (contoh: USC-01)"
            value={scenarioNumber}
            onChange={(e) => setScenarioNumber(e.target.value)}
          />
          <Input label="Judul Skenario" value={scenarioTitle} onChange={(e) => setScenarioTitle(e.target.value)} />
          <Input
            label="Tujuan Bisnis"
            value={scenarioObjective}
            onChange={(e) => setScenarioObjective(e.target.value)}
          />
          <Textarea
            label="Langkah-Langkah (Satu per baris)"
            rows={3}
            value={scenarioSteps}
            onChange={(e) => setScenarioSteps(e.target.value)}
            placeholder="Buka halaman utama&#10;Klik login&#10;Masukkan kredensial"
          />
          <Input
            label="Hasil yang Diharapkan"
            value={scenarioExpected}
            onChange={(e) => setScenarioExpected(e.target.value)}
          />
          <Textarea
            label="Kriteria Penerimaan (Satu per baris)"
            rows={2}
            value={scenarioCriteria}
            onChange={(e) => setScenarioCriteria(e.target.value)}
            placeholder="Polis PDF ter-generate&#10;Waktu < 5 detik"
          />
          <Select
            label="Prioritas"
            value={scenarioPriority}
            onChange={(e) => setScenarioPriority(e.target.value)}
            options={[
              { value: 'critical', label: 'Critical' },
              { value: 'high', label: 'High' },
              { value: 'medium', label: 'Medium' },
              { value: 'low', label: 'Low' },
            ]}
          />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowScenarioModal(false)}>
              Batal
            </Button>
            <Button
              variant="primary"
              disabled={busy || !scenarioNumber.trim() || !scenarioTitle.trim() || !scenarioExpected.trim()}
              onClick={handleCreateScenario}
            >
              Simpan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
