import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS } from '../../data'
import { PageHeader, Button, SectionCard, Textarea, Select, Toast } from '../../components/ui'

const ticket = TICKETS.find(t => t.status === 'internal_testing') || TICKETS[2]

const QA_TEST_CASES = [
  { id: 'qatc1', area: 'Fungsionalitas', title: 'Generate PDF Polis Jiwa — Happy Path', desc: 'Pilih nasabah baru → Generate → Verifikasi PDF', expected: 'PDF ter-generate dalam < 10 detik, konten benar' },
  { id: 'qatc2', area: 'Fungsionalitas', title: 'Generate PDF Polis Kesehatan & Kendaraan', desc: 'Uji semua tipe produk asuransi', expected: 'PDF tersedia untuk semua jenis polis' },
  { id: 'qatc3', area: 'Integrasi', title: 'Integrasi dengan modul Manajemen Polis', desc: 'Verifikasi data PDF sesuai data di database', expected: 'Data PDF konsisten dengan data sistem' },
  { id: 'qatc4', area: 'Performa', title: 'Load Test — Generate 20 PDF Bersamaan', desc: 'Simulasi 20 user generate PDF sekaligus', expected: 'Sistem tidak timeout, semua PDF berhasil' },
  { id: 'qatc5', area: 'Error Handling', title: 'Verifikasi Error Message', desc: 'Test dengan kondisi abnormal (session timeout, dll)', expected: 'Error message informatif, tidak crash' },
  { id: 'qatc6', area: 'Regression', title: 'Modul Manajemen Polis tidak terimbas', desc: 'Verifikasi fitur lain di modul tidak terdampak', expected: 'Semua fitur lain tetap berjalan normal' },
]

export default function TestingForm() {
  const navigate = useNavigate()
  const [results, setResults] = useState<Record<string, 'pass' | 'fail' | 'skip' | ''>>({})
  const [notes, setNotes] = useState<Record<string, string>>({})
  const [defects, setDefects] = useState('')
  const [recommendation, setRecommendation] = useState('pass')
  const [toast, setToast] = useState('')

  const setResult = (id: string, v: 'pass' | 'fail' | 'skip') => setResults(r => ({ ...r, [id]: v }))
  const setNote = (id: string, v: string) => setNotes(n => ({ ...n, [id]: v }))

  const passCount = Object.values(results).filter(r => r === 'pass').length
  const failCount = Object.values(results).filter(r => r === 'fail').length
  const allAnswered = QA_TEST_CASES.every(tc => results[tc.id])

  const handleSubmit = () => {
    setToast(recommendation === 'pass' ? 'Testing selesai. Tiket diteruskan ke UAT.' : 'Testing gagal. Tiket dikembalikan ke PIC.')
    setTimeout(() => navigate('/qa/dashboard'), 2000)
  }

  const areaGroups = [...new Set(QA_TEST_CASES.map(tc => tc.area))]

  return (
    <div className="max-w-3xl">
      {toast && <Toast message={toast} type={recommendation === 'pass' ? 'success' : 'error'} onClose={() => setToast('')} />}

      <PageHeader
        title="Form Hasil Pengujian QA"
        subtitle={`Internal Testing — ${ticket.id}`}
        actions={<Button variant="ghost" onClick={() => navigate('/qa/dashboard')}>← Dashboard</Button>}
      />

      {/* Header Info */}
      <SectionCard className="mb-5">
        <div className="flex items-start justify-between">
          <div>
            <p className="font-mono text-xs text-gray-400 mb-1">{ticket.id}</p>
            <h3 className="text-base font-bold text-gray-900">{ticket.title}</h3>
            <p className="text-xs text-gray-500 mt-1">{ticket.application} · PIC: {ticket.pic}</p>
          </div>
          <div className="text-right">
            <p className="text-xs text-gray-400">Progress</p>
            <p className="text-3xl font-bold text-[#1E3A8A]">{Object.keys(results).length}/{QA_TEST_CASES.length}</p>
            <div className="flex gap-2 justify-end mt-1">
              <span className="text-xs text-emerald-600">✓{passCount}</span>
              <span className="text-xs text-red-600">✕{failCount}</span>
            </div>
          </div>
        </div>
        <div className="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
          <div className="h-full bg-[#1E3A8A] rounded-full transition-all" style={{ width: `${(Object.keys(results).length / QA_TEST_CASES.length) * 100}%` }} />
        </div>
      </SectionCard>

      {/* Test Cases by Area */}
      <div className="space-y-5 mb-6">
        {areaGroups.map(area => (
          <SectionCard key={area} title={`Area: ${area}`}>
            <div className="space-y-4">
              {QA_TEST_CASES.filter(tc => tc.area === area).map((tc, idx) => (
                <div key={tc.id} className={`p-4 border rounded-xl ${results[tc.id] === 'fail' ? 'border-red-200 bg-red-50/20' : results[tc.id] === 'pass' ? 'border-emerald-200 bg-emerald-50/20' : 'border-gray-200'}`}>
                  <div className="flex items-start gap-2 mb-2">
                    <span className="text-xs font-bold text-gray-400 w-5 shrink-0 mt-0.5">{idx + 1}</span>
                    <div className="flex-1">
                      <p className="text-sm font-semibold text-gray-900">{tc.title}</p>
                      <p className="text-xs text-gray-500 mt-0.5">{tc.desc}</p>
                    </div>
                  </div>
                  <div className="bg-gray-50 rounded-lg p-2.5 mb-3 ml-7">
                    <p className="text-xs font-medium text-gray-500 mb-0.5">Ekspektasi:</p>
                    <p className="text-xs text-gray-700">{tc.expected}</p>
                  </div>
                  <div className="flex items-center gap-2 mb-2 ml-7">
                    <span className="text-xs text-gray-500 font-medium">Hasil:</span>
                    {(['pass', 'fail', 'skip'] as const).map(r => (
                      <button
                        key={r}
                        onClick={() => setResult(tc.id, r)}
                        className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition-all ${results[tc.id] === r
                          ? r === 'pass' ? 'bg-emerald-500 text-white border-emerald-500' : r === 'fail' ? 'bg-red-500 text-white border-red-500' : 'bg-gray-400 text-white border-gray-400'
                          : 'bg-white text-gray-500 border-gray-300 hover:border-gray-400'}`}
                      >
                        {r === 'pass' ? '✓ Pass' : r === 'fail' ? '✕ Fail' : '— Skip'}
                      </button>
                    ))}
                  </div>
                  <div className="ml-7">
                    <Textarea
                      placeholder="Catatan / bug detail (opsional)..."
                      rows={2}
                      value={notes[tc.id] || ''}
                      onChange={e => setNote(tc.id, e.target.value)}
                    />
                  </div>
                </div>
              ))}
            </div>
          </SectionCard>
        ))}
      </div>

      {/* Defect Summary */}
      <SectionCard title="Ringkasan Defect" className="mb-4">
        <Textarea
          label="Daftar Defect yang Ditemukan"
          rows={3}
          placeholder="Tuliskan daftar defect beserta severity-nya..."
          value={defects}
          onChange={e => setDefects(e.target.value)}
        />
        <div className="mt-3">
          <Select
            label="Rekomendasi QA"
            value={recommendation}
            onChange={e => setRecommendation(e.target.value)}
            options={[
              { value: 'pass', label: '✅ PASS — Lulus, diteruskan ke UAT' },
              { value: 'fail', label: '❌ FAIL — Gagal, kembalikan ke PIC' },
              { value: 'conditional', label: '⚠️ CONDITIONAL PASS — Ada minor defect, bisa dilanjutkan' },
            ]}
          />
        </div>
      </SectionCard>

      <div className="flex justify-between items-center">
        <span className="text-sm text-gray-500">
          {allAnswered ? '✅ Semua test case selesai' : `⏳ ${QA_TEST_CASES.length - Object.keys(results).length} test case belum selesai`}
        </span>
        <div className="flex gap-3">
          <Button variant="secondary" onClick={() => navigate('/qa/dashboard')}>Batal</Button>
          <Button
            variant={recommendation === 'pass' ? 'success' : recommendation === 'fail' ? 'danger' : 'warning'}
            onClick={handleSubmit}
            disabled={!allAnswered}
          >
            📤 Submit Hasil Testing
          </Button>
        </div>
      </div>
    </div>
  )
}
