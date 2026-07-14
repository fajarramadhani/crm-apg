import { PageHeader, SectionCard, Button } from '../../components/ui'

const MATRIX = [
  {
    level: 1,
    trigger: 'Tiket Critical melewati 50% SLA',
    threshold: '12 jam',
    notifyTo: ['IT Lead (Hendra Wijaya)'],
    channel: 'Email + Sistem Notifikasi',
    color: 'border-amber-200 bg-amber-50',
    iconColor: 'bg-amber-500',
  },
  {
    level: 2,
    trigger: 'Tiket Critical melewati SLA',
    threshold: '24 jam',
    notifyTo: ['IT Lead', 'IT Manager (Ahmad Fauzi)'],
    channel: 'Email + WhatsApp + Sistem',
    color: 'border-orange-200 bg-orange-50',
    iconColor: 'bg-orange-500',
  },
  {
    level: 3,
    trigger: 'Tiket Critical melewati 200% SLA',
    threshold: '48 jam',
    notifyTo: ['IT Lead', 'IT Manager', 'Direktur Teknologi'],
    channel: 'Email + WhatsApp + Telepon + Sistem',
    color: 'border-red-200 bg-red-50',
    iconColor: 'bg-red-500',
  },
]

const HIGH_RULES = [
  { level: 1, threshold: '100% SLA (48j)', notifyTo: 'IT Lead', channel: 'Email + Sistem' },
  { level: 2, threshold: '150% SLA (72j)', notifyTo: 'IT Lead + Manager', channel: 'Email + WhatsApp' },
]

export default function EscalationMatrix() {
  return (
    <div>
      <PageHeader
        title="Matrix Eskalasi"
        subtitle="Konfigurasi aturan eskalasi otomatis berdasarkan prioritas dan waktu"
        actions={<Button variant="primary">➕ Tambah Aturan</Button>}
      />

      {/* How it works */}
      <div className="bg-[#1E3A8A]/5 border border-[#1E3A8A]/20 rounded-xl p-5 mb-6">
        <div className="flex items-start gap-3">
          <span className="text-[#1E3A8A] text-xl">ℹ️</span>
          <div>
            <p className="text-sm font-bold text-[#1E3A8A] mb-2">Cara Kerja Eskalasi Otomatis</p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-700">
              <div className="flex items-start gap-2">
                <span className="text-[#1E3A8A] font-bold mt-0.5">1</span>
                <p>Sistem memantau SLA setiap 15 menit</p>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-[#1E3A8A] font-bold mt-0.5">2</span>
                <p>Jika ambang batas terlampaui, notifikasi otomatis dikirim</p>
              </div>
              <div className="flex items-start gap-2">
                <span className="text-[#1E3A8A] font-bold mt-0.5">3</span>
                <p>Eskalasi berlanjut ke level berikutnya jika tidak ada respons</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Critical Escalation */}
      <SectionCard title="🔴 Eskalasi — Prioritas Critical" className="mb-6">
        <div className="space-y-4">
          {MATRIX.map(level => (
            <div key={level.level} className={`border rounded-xl p-4 ${level.color}`}>
              <div className="flex items-start gap-4">
                <div className={`w-9 h-9 rounded-full ${level.iconColor} text-white flex items-center justify-center font-bold text-sm shrink-0`}>
                  L{level.level}
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <p className="text-sm font-bold text-gray-900">Level {level.level}</p>
                    <span className="text-xs px-2 py-0.5 bg-white border border-gray-200 rounded-full text-gray-600">Trigger: {level.threshold}</span>
                  </div>
                  <p className="text-sm text-gray-700 mb-2">{level.trigger}</p>
                  <div className="grid grid-cols-2 gap-4 text-xs">
                    <div>
                      <p className="font-semibold text-gray-600 mb-1">Notifikasi Ke:</p>
                      <ul className="space-y-0.5">
                        {level.notifyTo.map(n => <li key={n} className="text-gray-700">• {n}</li>)}
                      </ul>
                    </div>
                    <div>
                      <p className="font-semibold text-gray-600 mb-1">Channel:</p>
                      <p className="text-gray-700">{level.channel}</p>
                    </div>
                  </div>
                </div>
                <button className="text-xs text-gray-500 hover:text-[#1E3A8A] p-1 shrink-0">✏️</button>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* High Escalation */}
      <SectionCard title="🟠 Eskalasi — Prioritas High" className="mb-6">
        <div className="space-y-3">
          {HIGH_RULES.map(rule => (
            <div key={rule.level} className="flex items-center gap-4 p-4 border border-orange-200 bg-orange-50/50 rounded-xl">
              <div className="w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center font-bold text-sm shrink-0">
                L{rule.level}
              </div>
              <div className="flex-1 grid grid-cols-3 gap-3 text-sm">
                <div><p className="text-xs text-gray-500">Threshold</p><p className="font-medium">{rule.threshold}</p></div>
                <div><p className="text-xs text-gray-500">Notifikasi</p><p className="font-medium">{rule.notifyTo}</p></div>
                <div><p className="text-xs text-gray-500">Channel</p><p className="font-medium">{rule.channel}</p></div>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      {/* Medium & Low */}
      <SectionCard title="🟡🟢 Eskalasi — Prioritas Medium & Low">
        <div className="grid grid-cols-2 gap-4">
          {[
            { priority: 'Medium', trigger: '100% SLA (7 hari)', notify: 'IT Lead', icon: '🟡' },
            { priority: 'Low', trigger: '100% SLA (14 hari)', notify: 'IT Lead', icon: '🟢' },
          ].map(rule => (
            <div key={rule.priority} className="border border-gray-200 rounded-xl p-4 bg-gray-50">
              <p className="text-sm font-bold text-gray-900 mb-2">{rule.icon} {rule.priority}</p>
              <div className="space-y-2 text-xs">
                <div><span className="text-gray-500">Trigger: </span><span className="font-medium">{rule.trigger}</span></div>
                <div><span className="text-gray-500">Notifikasi: </span><span className="font-medium">{rule.notify}</span></div>
                <div><span className="text-gray-500">Channel: </span><span className="font-medium">Email + Sistem</span></div>
              </div>
            </div>
          ))}
        </div>
      </SectionCard>
    </div>
  )
}
