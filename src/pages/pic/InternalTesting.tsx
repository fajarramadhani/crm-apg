import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS } from '../../data'
import { PageHeader, Button, SectionCard, Textarea, Toast, Select } from '../../components/ui'

const ticket = TICKETS[0]

export default function InternalTestingPIC() {
  const navigate = useNavigate()
  const [toast, setToast] = useState('')
  const [form, setForm] = useState({
    testEnv: 'staging',
    testDate: '2026-07-10',
    testerNote: '',
    overallResult: 'pass',
  })

  const handleSubmit = () => {
    setToast('Internal testing disubmit ke QA untuk verifikasi.')
    setTimeout(() => navigate('/pic/workspace'), 2000)
  }

  const testScenarios = [
    { id: 1, scenario: 'Generate PDF polis untuk nasabah baru (Jiwa)', result: 'pass', note: 'PDF berhasil dalam 2.8 detik' },
    { id: 2, scenario: 'Generate PDF polis kesehatan', result: 'pass', note: 'PDF berhasil dalam 3.1 detik' },
    { id: 3, scenario: 'Generate PDF polis kendaraan', result: 'pass', note: 'PDF berhasil dalam 2.5 detik' },
    { id: 4, scenario: 'Verifikasi konten PDF (nama, nomor polis, premi)', result: 'pass', note: 'Data sesuai dengan sistem' },
    { id: 5, scenario: 'Generate PDF batch 10 polis sekaligus', result: 'pass', note: 'Semua 10 PDF berhasil dalam 28 detik' },
    { id: 6, scenario: 'Edge case: polis dengan karakter khusus', result: 'pass', note: 'Handle karakter khusus dengan benar' },
  ]

  return (
    <div className="max-w-3xl">
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Internal Testing"
        subtitle={`Pengujian developer — ${ticket.id}`}
        actions={<Button variant="ghost" onClick={() => navigate('/pic/workspace')}>← Workspace</Button>}
      />

      <div className="bg-teal-50 border border-teal-200 rounded-xl p-4 mb-5 flex gap-3">
        <span className="text-teal-500 text-lg">🔬</span>
        <div className="text-sm text-teal-800">
          <p className="font-semibold">Internal Testing oleh PIC</p>
          <p className="mt-0.5 text-xs text-teal-600">Lakukan pengujian menyeluruh sebelum menyerahkan ke QA. Pastikan semua skenario sudah diuji.</p>
        </div>
      </div>

      <div className="space-y-4">
        <SectionCard title="Konfigurasi Testing">
          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Environment"
              value={form.testEnv}
              onChange={e => setForm(f => ({ ...f, testEnv: e.target.value }))}
              options={[
                { value: 'local', label: 'Local Development' },
                { value: 'dev', label: 'Development Server' },
                { value: 'staging', label: 'Staging Server' },
              ]}
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tanggal Testing</label>
              <input
                type="date"
                value={form.testDate}
                onChange={e => setForm(f => ({ ...f, testDate: e.target.value }))}
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30"
              />
            </div>
          </div>
        </SectionCard>

        <SectionCard title="Hasil Skenario Pengujian">
          <div className="space-y-3">
            {testScenarios.map(s => (
              <div key={s.id} className={`flex items-start gap-3 p-3 rounded-xl border ${s.result === 'pass' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'}`}>
                <div className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 ${s.result === 'pass' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'}`}>
                  {s.result === 'pass' ? '✓' : '✕'}
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-gray-900">{s.scenario}</p>
                  {s.note && <p className="text-xs text-gray-500 mt-0.5">{s.note}</p>}
                </div>
                <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${s.result === 'pass' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                  {s.result.toUpperCase()}
                </span>
              </div>
            ))}
          </div>
          <div className="mt-3 p-3 bg-gray-50 rounded-xl text-sm">
            <span className="font-semibold text-gray-700">Hasil: </span>
            <span className="text-emerald-600 font-bold">{testScenarios.filter(s => s.result === 'pass').length} PASS</span>
            <span className="mx-2 text-gray-300">|</span>
            <span className="text-red-600 font-bold">{testScenarios.filter(s => s.result === 'fail').length} FAIL</span>
          </div>
        </SectionCard>

        <SectionCard title="Catatan Testing">
          <Textarea
            label="Catatan tambahan untuk QA"
            rows={4}
            placeholder="Informasi penting, area yang perlu perhatian khusus, atau instruksi testing untuk QA..."
            value={form.testerNote}
            onChange={e => setForm(f => ({ ...f, testerNote: e.target.value }))}
          />
          <div className="mt-3">
            <Select
              label="Keputusan Akhir"
              value={form.overallResult}
              onChange={e => setForm(f => ({ ...f, overallResult: e.target.value }))}
              options={[
                { value: 'pass', label: '✅ PASS — Siap untuk QA Testing' },
                { value: 'fail', label: '❌ FAIL — Perlu perbaikan lebih lanjut' },
                { value: 'conditional', label: '⚠️ CONDITIONAL — Ada catatan minor' },
              ]}
            />
          </div>
        </SectionCard>

        <div className="flex justify-end gap-3">
          <Button variant="secondary" onClick={() => navigate('/pic/workspace')}>Batal</Button>
          <Button variant={form.overallResult === 'pass' ? 'success' : 'warning'} onClick={handleSubmit}>
            🔬 Submit ke QA
          </Button>
        </div>
      </div>
    </div>
  )
}
