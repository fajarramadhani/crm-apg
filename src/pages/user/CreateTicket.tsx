import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { APPLICATIONS, DIVISIONS } from '../../data'
import { PageHeader, Button, Input, Select, Textarea, Card, Toast } from '../../components/ui'

export default function CreateTicket() {
  const navigate = useNavigate()
  const [showToast, setShowToast] = useState(false)
  const [step, setStep] = useState(1)
  const [form, setForm] = useState({
    category: 'incident',
    title: '',
    application: '',
    division: 'Operasional Polis',
    priority_suggestion: 'medium',
    description: '',
    impact: '',
    steps_to_reproduce: '',
    expected_result: '',
    actual_result: '',
    urgency: 'normal',
    attachments: [] as string[],
  })

  const update = (field: string, value: string) => setForm((f) => ({ ...f, [field]: value }))

  const handleSubmit = () => {
    setShowToast(true)
    setTimeout(() => {
      navigate('/user/tickets')
    }, 1500)
  }

  const categories = [
    { value: 'incident', label: '🔴 Incident — Gangguan sistem yang berdampak pada operasional' },
    { value: 'request', label: '🔵 Request — Permintaan fitur atau layanan baru' },
    { value: 'change', label: '🟣 Change — Perubahan konfigurasi atau sistem' },
    { value: 'problem', label: '🟠 Problem — Masalah berulang yang perlu investigasi' },
  ]

  const prioritySuggestions = [
    { value: 'critical', label: '🔴 Critical — Sistem tidak dapat digunakan, dampak sangat besar' },
    { value: 'high', label: '🟠 High — Fungsi utama terganggu, ada workaround' },
    { value: 'medium', label: '🟡 Medium — Fungsi non-kritis terganggu' },
    { value: 'low', label: '🟢 Low — Minor issue, tidak mengganggu operasional' },
  ]

  return (
    <div className="max-w-3xl">
      {showToast && (
        <Toast
          message="Tiket berhasil dibuat! Menunggu validasi Supervisor."
          type="success"
          onClose={() => setShowToast(false)}
        />
      )}

      <PageHeader
        title="Buat Request / Tiket IT"
        subtitle="Sampaikan permintaan atau insiden IT Anda"
        actions={
          <Button variant="ghost" onClick={() => navigate('/user/dashboard')}>
            ← Kembali
          </Button>
        }
      />

      {/* Step Indicator */}
      <div className="flex items-center gap-2 mb-6">
        {[1, 2, 3].map((s) => (
          <div key={s} className="flex items-center gap-2">
            <div
              className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-all ${s === step ? 'bg-[#1E3A8A] text-white' : s < step ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500'}`}
            >
              {s < step ? '✓' : s}
            </div>
            <span className={`text-sm ${s === step ? 'text-gray-900 font-medium' : 'text-gray-400'}`}>
              {s === 1 ? 'Informasi Dasar' : s === 2 ? 'Detail Masalah' : 'Konfirmasi'}
            </span>
            {s < 3 && <div className="w-8 h-px bg-gray-300" />}
          </div>
        ))}
      </div>

      <Card className="p-6">
        {step === 1 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold text-gray-900 pb-3 border-b border-gray-100">
              Informasi Dasar Tiket
            </h3>

            <Select
              label="Kategori Tiket *"
              options={categories}
              value={form.category}
              onChange={(e) => update('category', e.target.value)}
            />

            <Input
              label="Judul Tiket *"
              placeholder="Deskripsikan masalah secara singkat dan jelas"
              value={form.title}
              onChange={(e) => update('title', e.target.value)}
              hint="Contoh: Gagal Generate PDF Polis Asuransi"
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label="Aplikasi Terdampak *"
                options={[
                  { value: '', label: '— Pilih Aplikasi —' },
                  ...APPLICATIONS.map((a) => ({ value: a, label: a })),
                ]}
                value={form.application}
                onChange={(e) => update('application', e.target.value)}
              />
              <Select
                label="Divisi Pelapor *"
                options={DIVISIONS.map((d) => ({ value: d, label: d }))}
                value={form.division}
                onChange={(e) => update('division', e.target.value)}
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label="Saran Prioritas"
                options={prioritySuggestions}
                value={form.priority_suggestion}
                onChange={(e) => update('priority_suggestion', e.target.value)}
              />
              <Select
                label="Urgensi"
                options={[
                  { value: 'normal', label: 'Normal — Dalam jam kerja' },
                  { value: 'urgent', label: 'Urgent — Perlu segera hari ini' },
                  { value: 'critical', label: 'Critical — Tidak bisa menunggu' },
                ]}
                value={form.urgency}
                onChange={(e) => update('urgency', e.target.value)}
              />
            </div>

            <div className="flex justify-end">
              <Button variant="primary" onClick={() => setStep(2)} disabled={!form.title || !form.application}>
                Lanjut →
              </Button>
            </div>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold text-gray-900 pb-3 border-b border-gray-100">Detail Masalah</h3>

            <Textarea
              label="Deskripsi Masalah *"
              rows={4}
              placeholder="Jelaskan masalah secara lengkap..."
              value={form.description}
              onChange={(e) => update('description', e.target.value)}
              hint="Deskripsikan masalah yang terjadi, sejak kapan, dan dampaknya terhadap pekerjaan."
            />

            {form.category === 'incident' && (
              <>
                <Textarea
                  label="Langkah Reproduksi"
                  rows={3}
                  placeholder="1. Buka halaman X&#10;2. Klik tombol Y&#10;3. Error muncul"
                  value={form.steps_to_reproduce}
                  onChange={(e) => update('steps_to_reproduce', e.target.value)}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Textarea
                    label="Hasil yang Diharapkan"
                    rows={3}
                    placeholder="Seharusnya sistem melakukan..."
                    value={form.expected_result}
                    onChange={(e) => update('expected_result', e.target.value)}
                  />
                  <Textarea
                    label="Hasil yang Terjadi"
                    rows={3}
                    placeholder="Yang sebenarnya terjadi adalah..."
                    value={form.actual_result}
                    onChange={(e) => update('actual_result', e.target.value)}
                  />
                </div>
              </>
            )}

            <Textarea
              label="Dampak Bisnis"
              rows={2}
              placeholder="Jelaskan dampak terhadap operasional bisnis..."
              value={form.impact}
              onChange={(e) => update('impact', e.target.value)}
            />

            {/* File Upload Placeholder */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Lampiran (opsional)</label>
              <div className="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-[#1E3A8A]/40 transition-colors cursor-pointer">
                <div className="text-2xl mb-2">📎</div>
                <p className="text-sm text-gray-500">
                  Drag & drop file atau <span className="text-[#1E3A8A] font-medium">browse</span>
                </p>
                <p className="text-xs text-gray-400 mt-1">PNG, JPG, PDF, LOG hingga 10MB</p>
              </div>
            </div>

            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(1)}>
                ← Kembali
              </Button>
              <Button variant="primary" onClick={() => setStep(3)} disabled={!form.description}>
                Lanjut →
              </Button>
            </div>
          </div>
        )}

        {step === 3 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold text-gray-900 pb-3 border-b border-gray-100">Konfirmasi Tiket</h3>

            <div className="bg-gray-50 rounded-xl p-5 space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-gray-500 text-xs mb-1">Kategori</p>
                  <p className="font-medium text-gray-900 capitalize">{form.category}</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs mb-1">Saran Prioritas</p>
                  <p className="font-medium text-gray-900 capitalize">{form.priority_suggestion}</p>
                </div>
                <div className="col-span-2">
                  <p className="text-gray-500 text-xs mb-1">Judul</p>
                  <p className="font-medium text-gray-900">{form.title}</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs mb-1">Aplikasi</p>
                  <p className="font-medium text-gray-900">{form.application}</p>
                </div>
                <div>
                  <p className="text-gray-500 text-xs mb-1">Divisi</p>
                  <p className="font-medium text-gray-900">{form.division}</p>
                </div>
                <div className="col-span-2">
                  <p className="text-gray-500 text-xs mb-1">Deskripsi</p>
                  <p className="font-medium text-gray-900 text-sm leading-relaxed">{form.description}</p>
                </div>
              </div>
            </div>

            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3">
              <span className="text-amber-500">⚠️</span>
              <div className="text-sm text-amber-800">
                <p className="font-semibold">Sebelum mengirim</p>
                <p className="mt-1">
                  Tiket akan diteruskan ke Supervisor divisi Anda untuk divalidasi. Prioritas akhir akan ditentukan oleh
                  IT Lead.
                </p>
              </div>
            </div>

            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(2)}>
                ← Kembali
              </Button>
              <Button variant="primary" onClick={handleSubmit}>
                📤 Kirim Tiket
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}
