import { useState } from 'react'
import { formatDateTime } from '../../data'
import { PageHeader, Table, TR, TD, FilterBar, Input, Select } from '../../components/ui'

const AUDIT_DATA = [
  { id: 'aud1', timestamp: '2026-07-13T10:15:00Z', actor: 'Hendra Wijaya', role: 'IT Lead', action: 'ASSIGN_PIC', resource: 'IT-2026-000004', detail: 'PIC ditugaskan: Dian Kusuma. Prioritas: High. SLA: 48 jam.', ip: '10.0.1.45' },
  { id: 'aud2', timestamp: '2026-07-13T10:05:00Z', actor: 'Sistem', role: 'SYSTEM', action: 'SLA_ESCALATION', resource: 'IT-2026-000006', detail: 'Eskalasi Level 2: Notifikasi dikirim ke IT Lead dan Manager.', ip: 'system' },
  { id: 'aud3', timestamp: '2026-07-13T09:45:00Z', actor: 'Sari Pratiwi', role: 'QA', action: 'QA_PASS', resource: 'IT-2026-000001', detail: 'Internal testing PASSED. 6/6 test case lulus. Tiket diteruskan ke UAT.', ip: '10.0.1.67' },
  { id: 'aud4', timestamp: '2026-07-13T09:00:00Z', actor: 'Admin Sistem', role: 'Admin', action: 'USER_CREATE', resource: 'USR-NEW-009', detail: 'User baru dibuat: linda.baru@apg.co.id, Role: User, Divisi: IT.', ip: '10.0.1.12' },
  { id: 'aud5', timestamp: '2026-07-13T08:30:00Z', actor: 'Budi Santoso', role: 'Supervisor', action: 'TICKET_VALIDATE', resource: 'IT-2026-000005', detail: 'Tiket divalidasi dan diteruskan ke IT Lead untuk triage.', ip: '10.0.1.23' },
  { id: 'aud6', timestamp: '2026-07-13T08:05:00Z', actor: 'Rina Marlina', role: 'User', action: 'TICKET_CREATE', resource: 'IT-2026-000005', detail: 'Tiket baru dibuat: Penambahan Role Baru: Auditor Internal. Kategori: Change.', ip: '10.0.2.34' },
  { id: 'aud7', timestamp: '2026-07-12T16:30:00Z', actor: 'Dian Kusuma', role: 'PIC', action: 'STATUS_UPDATE', resource: 'IT-2026-000003', detail: 'Status diubah dari in_progress ke internal_testing.', ip: '10.0.1.45' },
  { id: 'aud8', timestamp: '2026-07-12T14:00:00Z', actor: 'Admin Sistem', role: 'Admin', action: 'SLA_RULE_UPDATE', resource: 'SLA-RULE-001', detail: 'Aturan SLA Critical diupdate: 24 jam → 4 jam untuk kategori Production Down.', ip: '10.0.1.12' },
  { id: 'aud9', timestamp: '2026-07-12T09:00:00Z', actor: 'Sistem', role: 'SYSTEM', action: 'SLA_BREACH', resource: 'IT-2026-000003', detail: 'Tiket melewati batas SLA 48 jam. Eskalasi Level 1 dikirim.', ip: 'system' },
  { id: 'aud10', timestamp: '2026-07-11T14:05:00Z', actor: 'Sari Pratiwi', role: 'QA', action: 'QA_SUBMIT', resource: 'IT-2026-000001', detail: 'Hasil pengujian QA disubmit: PASS. Tiket diteruskan ke fase UAT.', ip: '10.0.1.67' },
  { id: 'aud11', timestamp: '2026-07-10T13:00:00Z', actor: 'Ahmad Fauzi', role: 'Manager', action: 'APPROVE_DEPLOY', resource: 'IT-2026-000007', detail: 'Deployment disetujui oleh Manager. Catatan: Sesuai kebutuhan OJK compliance.', ip: '10.0.1.78' },
  { id: 'aud12', timestamp: '2026-07-09T08:30:00Z', actor: 'Sistem', role: 'SYSTEM', action: 'SLA_BREACH', resource: 'IT-2026-000001', detail: 'Tiket melewati SLA 24 jam. Eskalasi otomatis Level 2 dikirim.', ip: 'system' },
]

const ACTION_COLORS: Record<string, string> = {
  TICKET_CREATE: 'bg-blue-100 text-blue-700',
  TICKET_VALIDATE: 'bg-purple-100 text-purple-700',
  ASSIGN_PIC: 'bg-indigo-100 text-indigo-700',
  STATUS_UPDATE: 'bg-teal-100 text-teal-700',
  QA_PASS: 'bg-emerald-100 text-emerald-700',
  QA_SUBMIT: 'bg-cyan-100 text-cyan-700',
  SLA_ESCALATION: 'bg-red-100 text-red-700',
  SLA_BREACH: 'bg-red-100 text-red-700',
  SLA_RULE_UPDATE: 'bg-amber-100 text-amber-700',
  USER_CREATE: 'bg-violet-100 text-violet-700',
  APPROVE_DEPLOY: 'bg-green-100 text-green-700',
}

export default function AuditLog() {
  const [search, setSearch] = useState('')
  const [actionFilter, setActionFilter] = useState('')

  const filtered = AUDIT_DATA.filter(log => {
    const matchSearch = !search || log.detail.toLowerCase().includes(search.toLowerCase()) || log.actor.toLowerCase().includes(search.toLowerCase()) || log.resource.toLowerCase().includes(search.toLowerCase())
    const matchAction = !actionFilter || log.action === actionFilter
    return matchSearch && matchAction
  })

  const uniqueActions = [...new Set(AUDIT_DATA.map(l => l.action))]

  return (
    <div>
      <PageHeader
        title="Audit Log"
        subtitle="Rekam jejak seluruh aktivitas dan perubahan sistem"
      />

      <FilterBar>
        <Input placeholder="🔍 Cari log..." value={search} onChange={e => setSearch(e.target.value)} className="w-52" />
        <Select
          options={[{ value: '', label: 'Semua Aksi' }, ...uniqueActions.map(a => ({ value: a, label: a }))]}
          value={actionFilter}
          onChange={e => setActionFilter(e.target.value)}
          className="w-48"
        />
        <span className="ml-auto text-xs text-gray-400">{filtered.length} entri</span>
      </FilterBar>

      <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <Table headers={['Waktu', 'Aktor', 'Role', 'Aksi', 'Resource', 'Detail', 'IP']}>
          {filtered.map(log => (
            <TR key={log.id} highlight={log.action.includes('BREACH') || log.action.includes('ESCALATION')}>
              <TD>
                <span className="text-xs text-gray-500 whitespace-nowrap font-mono">
                  {formatDateTime(log.timestamp)}
                </span>
              </TD>
              <TD>
                <span className="text-xs font-medium text-gray-800">{log.actor}</span>
              </TD>
              <TD>
                <span className="text-xs text-gray-500">{log.role}</span>
              </TD>
              <TD>
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap ${ACTION_COLORS[log.action] || 'bg-gray-100 text-gray-600'}`}>
                  {log.action}
                </span>
              </TD>
              <TD>
                <span className="text-xs font-mono text-gray-600">{log.resource}</span>
              </TD>
              <TD>
                <p className="text-xs text-gray-600 max-w-[300px] leading-relaxed">{log.detail}</p>
              </TD>
              <TD>
                <span className="text-xs font-mono text-gray-400">{log.ip}</span>
              </TD>
            </TR>
          ))}
        </Table>
      </div>
    </div>
  )
}
