import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'
import { ApiRequestError } from '../../api/client'
import { ticketService, type TicketRecord } from '../../services/ticketService'
import {
  KPICard,
  SectionCard,
  StatusBadge,
  PriorityBadge,
  PageHeader,
  Button,
  Table,
  TR,
  TD,
  Toast,
  EmptyState,
} from '../../components/ui'

export default function QADashboard() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [tickets, setTickets] = useState<TicketRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  const load = () => {
    setLoading(true)
    ticketService
      .listMyTickets({ per_page: 100 })
      .then((res) => {
        setTickets(res.data)
      })
      .catch((err) => {
        setError((err as ApiRequestError).message || 'Gagal memuat antrian QA')
      })
      .finally(() => {
        setLoading(false)
      })
  }

  useEffect(() => {
    load()
  }, [])

  const handleStartTesting = (ticketId: number) => {
    setActionLoading(ticketId)
    ticketService
      .qaStart(ticketId)
      .then(() => {
        load()
      })
      .catch((err) => {
        setError(err.message || 'Gagal memulai pengujian')
      })
      .finally(() => {
        setActionLoading(null)
      })
  }

  // Filter tickets that belong to the current QA assignee
  const qaTickets = tickets.filter((t) => t.qa_assignee?.id === user?.id)

  // testingQueue: Tickets in qa_assignment, qa_in_progress, or qa_retest
  const testingQueue = qaTickets.filter((t) => ['qa_assignment', 'qa_in_progress', 'qa_retest'].includes(t.status))

  // Ready for UAT or completed QA
  const completedQa = qaTickets.filter((t) => ['ready_for_uat', 'uat', 'closed'].includes(t.status))

  // Failed testing / rework in progress
  const failedQa = qaTickets.filter(
    (t) => t.status === 'qa_failed' || (t.status === 'development_in_progress' && t.latest_qa_result === 'failed'),
  )

  return (
    <div>
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}

      <PageHeader
        title="Dashboard QA"
        subtitle="Kelola antrian pengujian, eksekusi test case, dan verifikasi kualitas tiket"
      />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard
          title="Antrian Testing"
          value={testingQueue.length}
          subtitle="Perlu pengujian"
          color="indigo"
          icon="🧪"
        />
        <KPICard title="QA Rework" value={failedQa.length} subtitle="PIC sedang rework" color="red" icon="🛠️" />
        <KPICard title="Lulus QA" value={completedQa.length} subtitle="Siap UAT / Selesai" color="green" icon="✅" />
        <KPICard title="Total Ditugaskan" value={qaTickets.length} subtitle="Tiket QA Anda" color="blue" icon="🔬" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <SectionCard title={`Antrian Testing (${testingQueue.length})`}>
            {loading ? (
              <p className="py-12 text-center text-sm text-gray-500">Memuat data...</p>
            ) : testingQueue.length === 0 ? (
              <div className="text-center py-10">
                <div className="text-4xl mb-3">🧪</div>
                <p className="text-sm text-gray-600 font-medium">Tidak ada tiket menunggu testing</p>
              </div>
            ) : (
              <div className="space-y-3">
                {testingQueue.map((t) => (
                  <div
                    key={t.id}
                    className={`border rounded-xl p-4 transition-all border-gray-200 hover:border-[#1E3A8A]/30 bg-white`}
                  >
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <span className="font-mono text-xs text-gray-400">{t.ticket_number}</span>
                        <h4 className="text-sm font-semibold text-gray-900">{t.title}</h4>
                        <p className="text-xs text-gray-500 mt-0.5">
                          PIC: {t.assignee?.name || '—'} · Aplikasi: {t.application?.name || '—'}
                        </p>
                      </div>
                      <PriorityBadge priority={t.final_priority?.key || 'medium'} />
                    </div>

                    <div className="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">
                      <div className="flex items-center gap-3">
                        <StatusBadge status={t.status} />
                        <span className="text-xs text-gray-400">
                          Cycle {t.qa_cycle_number || 1} · Run #{t.qa_run_number || 0}
                        </span>
                      </div>
                      <div className="flex gap-2">
                        {t.status === 'qa_assignment' ? (
                          <Button
                            size="sm"
                            variant="primary"
                            disabled={actionLoading === t.id}
                            onClick={() => handleStartTesting(t.id)}
                          >
                            {actionLoading === t.id ? 'Memulai...' : 'Mulai Testing'}
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            variant="success"
                            onClick={() => navigate('/qa/testing', { state: { ticketId: t.id } })}
                          >
                            Uji Sekarang
                          </Button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        <div className="space-y-4">
          <SectionCard title="Status Testing">
            <div className="space-y-3 font-medium">
              {[
                {
                  label: 'Menunggu Pengujian',
                  count: testingQueue.filter((t) => t.status === 'qa_assignment').length,
                  color: 'bg-indigo-500',
                },
                {
                  label: 'Sedang Diuji',
                  count: testingQueue.filter((t) => t.status === 'qa_in_progress' || t.status === 'qa_retest').length,
                  color: 'bg-yellow-500',
                },
                { label: 'Sedang Rework', count: failedQa.length, color: 'bg-red-500' },
                { label: 'Lulus & Siap UAT', count: completedQa.length, color: 'bg-emerald-500' },
              ].map((s) => (
                <div key={s.label} className="flex items-center gap-3">
                  <div className={`w-2.5 h-2.5 rounded-full ${s.color} shrink-0`} />
                  <span className="text-sm text-gray-600 flex-1">{s.label}</span>
                  <span className="text-sm font-bold text-gray-900">{s.count}</span>
                </div>
              ))}
            </div>
          </SectionCard>

          <SectionCard title="Panduan Proses QA">
            <div className="text-xs text-gray-600 space-y-2 leading-relaxed">
              <p>
                <b>1. Mulai Testing:</b> Ubah status tiket dari Ready for QA menjadi QA In Progress.
              </p>
              <p>
                <b>2. Buat Test Case & Run:</b> Definisikan langkah uji dan jalankan pengujian.
              </p>
              <p>
                <b>3. Laporkan Defect:</b> Jika uji gagal, laporkan defect agar PIC bisa langsung melakukan rework.
              </p>
              <p>
                <b>4. Verifikasi Fix:</b> Jalankan retest setelah PIC menyerahkan perbaikan.
              </p>
            </div>
          </SectionCard>
        </div>
      </div>

      <div className="mt-6">
        <SectionCard title="Semua Tiket dalam Lingkup QA Anda">
          {loading ? (
            <p className="py-12 text-center text-sm text-gray-500">Memuat data...</p>
          ) : qaTickets.length === 0 ? (
            <EmptyState title="Tidak ada tiket" message="Anda belum ditugaskan ke tiket mana pun." />
          ) : (
            <Table headers={['Ticket Number', 'Judul', 'PIC', 'Prioritas', 'Status', 'QA Run/Cycle', 'QA Hasil']}>
              {qaTickets.map((t) => (
                <TR key={t.id} onClick={() => navigate(`/user/tickets/${t.id}`)}>
                  <TD>
                    <span className="font-mono text-xs font-bold text-gray-700">{t.ticket_number}</span>
                  </TD>
                  <TD>
                    <p className="text-sm font-medium max-w-[240px] truncate">{t.title}</p>
                  </TD>
                  <TD>
                    <span className="text-xs">{t.assignee?.name || '—'}</span>
                  </TD>
                  <TD>
                    <PriorityBadge priority={t.final_priority?.key || 'medium'} />
                  </TD>
                  <TD>
                    <StatusBadge status={t.status} />
                  </TD>
                  <TD>
                    <span className="text-xs">
                      Cycle {t.qa_cycle_number || 1} · Run #{t.qa_run_number || 0}
                    </span>
                  </TD>
                  <TD>
                    {t.latest_qa_result ? (
                      <span
                        className={`inline-block px-1.5 py-0.5 text-2xs font-bold rounded ${t.latest_qa_result === 'passed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}
                      >
                        {t.latest_qa_result.toUpperCase()}
                      </span>
                    ) : (
                      '—'
                    )}
                  </TD>
                </TR>
              ))}
            </Table>
          )}
        </SectionCard>
      </div>
    </div>
  )
}
