import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS } from '../../data'
import { PageHeader, Button, StatusBadge, SectionCard, Textarea, Toast, Modal } from '../../components/ui'

const uatTickets = TICKETS.filter(t => t.status === 'uat' && t.requesterId === 'u1')
const ticket = uatTickets[0] || TICKETS[0]

const TEST_CASES = [
  { id: 'tc1', title: 'Generate PDF untuk Polis Baru', desc: 'Buka modul Manajemen Polis → Pilih nasabah baru → Klik "Cetak Polis" → Verifikasi PDF berhasil diunduh', expected: 'PDF dokumen polis berhasil ter-generate dan dapat diunduh' },
  { id: 'tc2', title: 'Generate PDF untuk Polis Perpanjangan', desc: 'Buka polis yang akan diperpanjang → Klik "Cetak Polis Perpanjangan" → Verifikasi konten PDF', expected: 'PDF perpanjangan polis berhasil dengan data yang benar' },
  { id: 'tc3', title: 'Validasi Konten PDF Polis', desc: 'Buka PDF yang ter-generate → Periksa nama nasabah, nomor polis, masa berlaku, dan besaran premi', expected: 'Semua data pada PDF sesuai dengan data sistem' },
  { id: 'tc4', title: 'Performa Generate PDF', desc: 'Generate PDF untuk 5 polis berbeda dan catat waktu yang dibutuhkan', expected: 'Proses generate PDF selesai dalam waktu < 10 detik per dokumen' },
  { id: 'tc5', title: 'Generate PDF Batch (Multi-polis)', desc: 'Pilih 3 polis sekaligus → Klik "Cetak Semua" → Verifikasi semua PDF berhasil', expected: 'Semua PDF ter-generate tanpa error' },
]

export default function UAT() {
  const navigate = useNavigate()
  const [results, setResults] = useState<Record<string, 'pass' | 'fail' | 'skip' | ''>>({})
  const [notes, setNotes] = useState<Record<string, string>>({})
  const [generalNote, setGeneralNote] = useState('')
  const [showConfirm, setShowConfirm] = useState(false)
  const [uatResult, setUatResult] = useState<'approve' | 'reject' | null>(null)
  const [toast, setToast] = useState('')
  const [submitted, setSubmitted] = useState(false)

  const setResult = (tcId: string, val: 'pass' | 'fail' | 'skip') => setResults(r => ({ ...r, [tcId]: val }))
  const setNote = (tcId: string, val: string) => setNotes(n => ({ ...n, [tcId]: val }))

  const allAnswered = TEST_CASES.every(tc => results[tc.id])
  const passCount = Object.values(results).filter(r => r === 'pass').length
  const failCount = Object.values(results).filter(r => r === 'fail').length

  const handleSubmit = (result: 'approve' | 'reject') => {
    setUatResult(result)
    setShowConfirm(true)
  }

  const confirmSubmit = () => {
    setSubmitted(true)
    setShowConfirm(false)
    setToast(uatResult === 'approve' ? 'UAT disetujui! Tiket akan dilanjutkan ke tahap deployment.' : 'UAT ditolak. Tiket dikembalikan ke PIC untuk perbaikan.')
    setTimeout(() => navigate('/user/tickets'), 2500)
  }

  if (submitted) return (
    <div className="flex flex-col items-center justify-center min-h-[60vh]">
      <div className="text-6xl mb-4">{uatResult === 'approve' ? '🎉' : '🔄'}</div>
      <h2 className="text-xl font-bold text-gray-900 mb-2">UAT {uatResult === 'approve' ? 'Disetujui' : 'Ditolak'}</h2>
      <p className="text-gray-500 text-sm">Mengalihkan ke halaman riwayat tiket...</p>
    </div>
  )

  return (
    <div className="max-w-3xl">
      {toast && <Toast message={toast} type={uatResult === 'approve' ? 'success' : 'warning'} onClose={() => setToast('')} />}

      <PageHeader
        title="User Acceptance Testing (UAT)"
        subtitle="Verifikasi bahwa perbaikan yang dilakukan sesuai kebutuhan"
        actions={<Button variant="ghost" onClick={() => navigate('/user/dashboard')}>← Kembali</Button>}
      />

      {/* Ticket Info */}
      <SectionCard className="mb-5">
        <div className="flex items-start justify-between">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="font-mono text-xs text-gray-400">{ticket.id}</span>
              <StatusBadge status="uat" />
            </div>
            <h3 className="font-semibold text-gray-900">{ticket.title}</h3>
            <p className="text-xs text-gray-500 mt-1">PIC: {ticket.pic} · {ticket.application}</p>
          </div>
          <div className="text-right text-xs text-gray-500">
            <p className="font-medium">Progress UAT</p>
            <p className="text-2xl font-bold text-[#1E3A8A] mt-1">{Object.keys(results).length}/{TEST_CASES.length}</p>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="mt-4">
          <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div className="h-full bg-[#1E3A8A] rounded-full transition-all" style={{ width: `${(Object.keys(results).length / TEST_CASES.length) * 100}%` }} />
          </div>
          <div className="flex gap-4 mt-2 text-xs">
            <span className="text-emerald-600">✓ Pass: {passCount}</span>
            <span className="text-red-600">✕ Fail: {failCount}</span>
            <span className="text-gray-400">— Belum: {TEST_CASES.length - Object.keys(results).length}</span>
          </div>
        </div>
      </SectionCard>

      {/* Panduan UAT */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 flex gap-3">
        <span className="text-blue-500 text-lg">ℹ️</span>
        <div className="text-sm text-blue-800">
          <p className="font-semibold mb-1">Panduan UAT</p>
          <ul className="space-y-0.5 text-xs text-blue-700 list-disc list-inside">
            <li>Jalankan setiap test case di environment UAT</li>
            <li>Tandai Pass jika hasil sesuai ekspektasi, Fail jika tidak</li>
            <li>Tambahkan catatan jika ada temuan penting</li>
            <li>Submit UAT setelah semua test case selesai dijalankan</li>
          </ul>
        </div>
      </div>

      {/* Test Cases */}
      <div className="space-y-4 mb-6">
        {TEST_CASES.map((tc, idx) => (
          <SectionCard key={tc.id}>
            <div className="flex items-start gap-3 mb-3">
              <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0 ${results[tc.id] === 'pass' ? 'bg-emerald-500 text-white' : results[tc.id] === 'fail' ? 'bg-red-500 text-white' : results[tc.id] === 'skip' ? 'bg-gray-400 text-white' : 'bg-gray-200 text-gray-600'}`}>
                {results[tc.id] === 'pass' ? '✓' : results[tc.id] === 'fail' ? '✕' : results[tc.id] === 'skip' ? '—' : idx + 1}
              </div>
              <div className="flex-1">
                <h4 className="text-sm font-semibold text-gray-900">{tc.title}</h4>
                <p className="text-xs text-gray-500 mt-1 leading-relaxed">{tc.desc}</p>
              </div>
            </div>

            <div className="bg-gray-50 rounded-lg p-3 mb-3">
              <p className="text-xs font-medium text-gray-600 mb-1">Hasil yang Diharapkan:</p>
              <p className="text-xs text-gray-700">{tc.expected}</p>
            </div>

            <div className="flex items-center gap-2 mb-3">
              <span className="text-xs text-gray-500 font-medium mr-1">Hasil:</span>
              {(['pass', 'fail', 'skip'] as const).map(r => (
                <button
                  key={r}
                  onClick={() => setResult(tc.id, r)}
                  className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all ${results[tc.id] === r
                    ? r === 'pass' ? 'bg-emerald-500 text-white border-emerald-500'
                      : r === 'fail' ? 'bg-red-500 text-white border-red-500'
                        : 'bg-gray-500 text-white border-gray-500'
                    : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'}`}
                >
                  {r === 'pass' ? '✓ Pass' : r === 'fail' ? '✕ Fail' : '— Skip'}
                </button>
              ))}
            </div>

            <Textarea
              placeholder="Catatan (opsional)..."
              rows={2}
              value={notes[tc.id] || ''}
              onChange={e => setNote(tc.id, e.target.value)}
            />
          </SectionCard>
        ))}
      </div>

      <SectionCard title="Catatan Umum">
        <Textarea
          placeholder="Tambahkan catatan umum tentang UAT ini..."
          rows={3}
          value={generalNote}
          onChange={e => setGeneralNote(e.target.value)}
        />
      </SectionCard>

      <div className="flex justify-between items-center mt-6">
        <span className="text-sm text-gray-500">{allAnswered ? '✅ Semua test case telah dijawab' : `⏳ ${TEST_CASES.length - Object.keys(results).length} test case belum dijawab`}</span>
        <div className="flex gap-3">
          <Button variant="danger" onClick={() => handleSubmit('reject')} disabled={!allAnswered}>
            ✕ Tolak — Perlu Perbaikan
          </Button>
          <Button variant="success" onClick={() => handleSubmit('approve')} disabled={!allAnswered}>
            ✓ Setujui UAT
          </Button>
        </div>
      </div>

      {/* Confirm Modal */}
      <Modal open={showConfirm} onClose={() => setShowConfirm(false)} title={`Konfirmasi ${uatResult === 'approve' ? 'Persetujuan' : 'Penolakan'} UAT`}>
        <div className="space-y-4">
          <div className={`flex items-center gap-3 p-4 rounded-xl ${uatResult === 'approve' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'}`}>
            <span className="text-2xl">{uatResult === 'approve' ? '✅' : '❌'}</span>
            <div>
              <p className={`font-semibold text-sm ${uatResult === 'approve' ? 'text-emerald-800' : 'text-red-800'}`}>
                {uatResult === 'approve' ? 'UAT Disetujui' : 'UAT Ditolak'}
              </p>
              <p className={`text-xs mt-0.5 ${uatResult === 'approve' ? 'text-emerald-600' : 'text-red-600'}`}>
                {uatResult === 'approve' ? `Pass: ${passCount}/${TEST_CASES.length} · Tiket akan dilanjutkan ke proses deployment.` : `Fail: ${failCount}/${TEST_CASES.length} · Tiket akan dikembalikan ke PIC untuk perbaikan.`}
              </p>
            </div>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setShowConfirm(false)}>Batal</Button>
            <Button variant={uatResult === 'approve' ? 'success' : 'danger'} onClick={confirmSubmit}>Konfirmasi</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
