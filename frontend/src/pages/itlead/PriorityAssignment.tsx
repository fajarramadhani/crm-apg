import { useNavigate } from 'react-router-dom'
import { TICKETS, SLA_RULES, formatDate } from '../../data'
import {
  PageHeader,
  Button,
  PriorityBadge,
  StatusBadge,
  SLAIndicator,
  Table,
  TR,
  TD,
  SectionCard,
} from '../../components/ui'

const assigned = TICKETS.filter((t) => ['assigned', 'in_progress', 'internal_testing', 'uat'].includes(t.status))

export default function PriorityAssignment() {
  const navigate = useNavigate()

  return (
    <div>
      <PageHeader
        title="Prioritas, SLA & PIC"
        subtitle="Pantau penugasan dan distribusi beban kerja"
        actions={
          <Button variant="ghost" onClick={() => navigate('/itlead/dashboard')}>
            ← Dashboard
          </Button>
        }
      />

      {/* SLA Rules */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {SLA_RULES.map((rule) => (
          <SectionCard key={rule.priority}>
            <div className={`text-center`}>
              <div
                className={`w-10 h-10 rounded-full mx-auto mb-2 flex items-center justify-center text-sm font-bold ${rule.priority === 'critical' ? 'bg-red-100 text-red-700' : rule.priority === 'high' ? 'bg-orange-100 text-orange-700' : rule.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'}`}
              >
                {rule.priority === 'critical'
                  ? '🔴'
                  : rule.priority === 'high'
                    ? '🟠'
                    : rule.priority === 'medium'
                      ? '🟡'
                      : '🟢'}
              </div>
              <p className="text-xs font-semibold text-gray-700 capitalize">{rule.priority}</p>
              <p className="text-2xl font-bold text-gray-900 mt-1">{rule.hours}j</p>
              <p className="text-xs text-gray-500">SLA</p>
            </div>
          </SectionCard>
        ))}
      </div>

      {/* PIC Workload */}
      <SectionCard title="Beban Kerja PIC" className="mb-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {[
            { name: 'Dian Kusuma', div: 'IT Development', tickets: assigned.filter((t) => t.picId === 'u4') },
            { name: 'Linda Susanti', div: 'IT Development', tickets: assigned.filter((t) => t.picId === 'u10') },
          ].map((pic) => (
            <div key={pic.name} className="border border-gray-200 rounded-xl p-4">
              <div className="flex items-center gap-3 mb-3">
                <div className="w-9 h-9 rounded-full bg-[#1E3A8A] text-white flex items-center justify-center text-sm font-semibold">
                  {pic.name
                    .split(' ')
                    .map((n) => n[0])
                    .join('')}
                </div>
                <div>
                  <p className="text-sm font-semibold text-gray-900">{pic.name}</p>
                  <p className="text-xs text-gray-500">{pic.div}</p>
                </div>
                <div className="ml-auto text-right">
                  <p className="text-2xl font-bold text-[#1E3A8A]">{pic.tickets.length}</p>
                  <p className="text-xs text-gray-400">tiket aktif</p>
                </div>
              </div>
              <div className="space-y-1.5">
                {pic.tickets.map((t) => (
                  <div
                    key={t.id}
                    className={`flex items-center gap-2 text-xs p-2 rounded-lg ${t.overSla ? 'bg-red-50 border border-red-200' : 'bg-gray-50'}`}
                  >
                    <span className="font-mono text-gray-400 shrink-0">{t.id.slice(-6)}</span>
                    <span className="truncate text-gray-700 flex-1">{t.title}</span>
                    <PriorityBadge priority={t.priority} />
                    {t.overSla && <span className="text-red-500">🚨</span>}
                  </div>
                ))}
                {pic.tickets.length === 0 && (
                  <p className="text-xs text-gray-400 text-center py-2">Tidak ada tiket aktif</p>
                )}
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* All Assigned Tickets */}
      <SectionCard title="Tiket Ditugaskan">
        <Table headers={['ID', 'Judul', 'PIC', 'Prioritas', 'Status', 'SLA', 'Aplikasi', 'Dibuat']}>
          {assigned.map((t) => (
            <TR key={t.id} highlight={t.overSla} onClick={() => navigate(`/user/tickets/${t.id}`)}>
              <TD>
                <span className="font-mono text-xs">{t.id}</span>
              </TD>
              <TD>
                <p className="text-sm font-medium max-w-[180px] truncate">{t.title}</p>
              </TD>
              <TD>
                <span className="text-xs">{t.pic || '—'}</span>
              </TD>
              <TD>
                <PriorityBadge priority={t.priority} />
              </TD>
              <TD>
                <StatusBadge status={t.status} />
              </TD>
              <TD>
                <div className="w-28">
                  <SLAIndicator slaRemaining={t.slaRemaining} overSla={t.overSla} slaHours={t.slaHours} />
                </div>
              </TD>
              <TD>
                <span className="text-xs text-gray-500 max-w-[100px] truncate block">{t.application}</span>
              </TD>
              <TD>
                <span className="text-xs text-gray-400 whitespace-nowrap">{formatDate(t.createdAt)}</span>
              </TD>
            </TR>
          ))}
        </Table>
      </SectionCard>
    </div>
  )
}
