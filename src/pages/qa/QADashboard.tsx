import { useNavigate } from 'react-router-dom'
import { TICKETS, formatDate } from '../../data'
import { KPICard, SectionCard, StatusBadge, PriorityBadge, SLAIndicator, PageHeader, Button, Table, TR, TD } from '../../components/ui'

const testingQueue = TICKETS.filter(t => t.status === 'internal_testing')
const allQA = TICKETS.filter(t => ['internal_testing', 'uat', 'pending_approval'].includes(t.status))

export default function QADashboard() {
  const navigate = useNavigate()

  return (
    <div>
      <PageHeader title="Dashboard QA" subtitle="Kelola pengujian internal dan verifikasi kualitas" />

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <KPICard title="Antrian Testing" value={testingQueue.length} subtitle="Menunggu QA" color="indigo" icon="🧪" />
        <KPICard title="Total QA Bulan Ini" value={18} subtitle="Tiket diuji" color="blue" icon="🔬" />
        <KPICard title="Pass Rate" value="94.4%" subtitle="Lulus testing" color="green" icon="✅" />
        <KPICard title="Dikirim ke UAT" value={allQA.filter(t => t.status === 'uat').length} subtitle="Siap UAT" color="cyan" icon="👤" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2">
          <SectionCard
            title={`Antrian Testing (${testingQueue.length})`}
            actions={<Button size="sm" variant="primary" onClick={() => navigate('/qa/testing')}>Buka Form</Button>}
          >
            {testingQueue.length === 0 ? (
              <div className="text-center py-10">
                <div className="text-4xl mb-3">🧪</div>
                <p className="text-sm text-gray-600 font-medium">Tidak ada tiket menunggu testing</p>
              </div>
            ) : (
              <div className="space-y-3">
                {testingQueue.map(t => (
                  <div
                    key={t.id}
                    onClick={() => navigate('/qa/testing')}
                    className={`border rounded-xl p-4 cursor-pointer hover:shadow-md transition-all ${t.overSla ? 'border-red-200 bg-red-50/30' : 'border-gray-200 hover:border-[#1E3A8A]/30'}`}
                  >
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <span className="font-mono text-xs text-gray-400">{t.id}</span>
                        <p className="text-sm font-semibold text-gray-900">{t.title}</p>
                        <p className="text-xs text-gray-500 mt-0.5">PIC: {t.pic} · {t.application}</p>
                      </div>
                      <PriorityBadge priority={t.priority} />
                    </div>
                    <div className="flex items-center gap-3">
                      <StatusBadge status={t.status} />
                      <span className="text-xs text-gray-400">{formatDate(t.updatedAt)}</span>
                    </div>
                    <div className="mt-2">
                      <SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>
        </div>

        <div className="space-y-4">
          <SectionCard title="Status Testing">
            <div className="space-y-3">
              {[
                { label: 'Menunggu Testing', count: testingQueue.length, color: 'bg-indigo-500' },
                { label: 'Di UAT', count: TICKETS.filter(t => t.status === 'uat').length, color: 'bg-cyan-500' },
                { label: 'Menunggu Approval', count: TICKETS.filter(t => t.status === 'pending_approval').length, color: 'bg-amber-500' },
                { label: 'Lulus (Bulan Ini)', count: 17, color: 'bg-emerald-500' },
                { label: 'Gagal Testing', count: 1, color: 'bg-red-500' },
              ].map(s => (
                <div key={s.label} className="flex items-center gap-3">
                  <div className={`w-2.5 h-2.5 rounded-full ${s.color} shrink-0`} />
                  <span className="text-sm text-gray-600 flex-1">{s.label}</span>
                  <span className="text-sm font-bold text-gray-900">{s.count}</span>
                </div>
              ))}
            </div>
          </SectionCard>

          <SectionCard title="Aksi Cepat">
            <div className="space-y-2">
              <button onClick={() => navigate('/qa/testing')} className="w-full text-left p-3 rounded-xl text-sm font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors">
                🧪 Form Hasil Pengujian
              </button>
            </div>
          </SectionCard>
        </div>
      </div>

      <div className="mt-6">
        <SectionCard title="Semua Tiket dalam QA Scope">
          <Table headers={['ID', 'Judul', 'PIC', 'Prioritas', 'Status', 'SLA', 'Diupdate']}>
            {allQA.map(t => (
              <TR key={t.id} highlight={t.overSla}>
                <TD><span className="font-mono text-xs">{t.id}</span></TD>
                <TD><p className="text-sm font-medium max-w-[200px] truncate">{t.title}</p></TD>
                <TD><span className="text-xs">{t.pic}</span></TD>
                <TD><PriorityBadge priority={t.priority} /></TD>
                <TD><StatusBadge status={t.status} /></TD>
                <TD><div className="w-28"><SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} /></div></TD>
                <TD><span className="text-xs text-gray-400 whitespace-nowrap">{formatDate(t.updatedAt)}</span></TD>
              </TR>
            ))}
          </Table>
        </SectionCard>
      </div>
    </div>
  )
}
