import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { TICKETS } from '../../data'
import { PageHeader, Button, SectionCard, Textarea, Toast, Select } from '../../components/ui'

const ticket = TICKETS[0]

export default function RCA() {
  const navigate = useNavigate()
  const [toast, setToast] = useState('')
  const [form, setForm] = useState({
    symptom:
      'Sistem tidak dapat meng-generate dokumen PDF polis. Error "PDF generation failed: template not found" muncul saat klik tombol "Cetak Polis".',
    rootCause:
      'File template PDF tidak ter-include dalam deployment package v2.3.1. Template file terhapus dari server production saat proses deployment automated.',
    contributing:
      'Tidak ada validasi file pada proses deployment pipeline. Checklist deployment tidak mencakup verifikasi template files.',
    impact:
      'Proses penerbitan polis untuk 50+ nasabah terhenti selama 2 hari. Nasabah tidak dapat menerima dokumen polis asuransi mereka.',
    solution:
      'Restore file template dari backup server. Update deployment checklist untuk mencakup validasi template files. Tambahkan automated test untuk generate PDF setelah setiap deployment.',
    preventive:
      '1. Tambahkan validasi template file dalam CI/CD pipeline\n2. Implementasi smoke test post-deployment untuk fungsi generate PDF\n3. Monitor file integrity pada server production\n4. Buat alert jika template file hilang atau berubah',
    category: 'deployment',
    recurrence: 'tidak',
  })

  const update = (f: string, v: string) => setForm((prev) => ({ ...prev, [f]: v }))

  const handleSave = () => {
    setToast('RCA berhasil disimpan')
    setTimeout(() => setToast(''), 3000)
  }

  return (
    <div className="max-w-3xl">
      {toast && <Toast message={toast} type="success" onClose={() => setToast('')} />}

      <PageHeader
        title="Root Cause Analysis"
        subtitle={`Analisis penyebab masalah — ${ticket.id}`}
        actions={
          <Button variant="ghost" onClick={() => navigate('/pic/workspace')}>
            ← Workspace
          </Button>
        }
      />

      {/* Ticket Info Banner */}
      <div className="bg-[#1E3A8A]/5 border border-[#1E3A8A]/20 rounded-xl p-4 mb-5 flex items-start gap-3">
        <span className="text-[#1E3A8A] text-lg">🔍</span>
        <div>
          <p className="text-sm font-semibold text-[#1E3A8A]">
            {ticket.id} — {ticket.title}
          </p>
          <p className="text-xs text-gray-500 mt-0.5">
            {ticket.application} · {ticket.division}
          </p>
        </div>
      </div>

      <div className="space-y-4">
        {/* Problem Statement */}
        <SectionCard title="1. Pernyataan Masalah (Problem Statement)">
          <Textarea
            label="Gejala yang Terlihat"
            rows={3}
            value={form.symptom}
            onChange={(e) => update('symptom', e.target.value)}
          />
        </SectionCard>

        {/* Root Cause */}
        <SectionCard title="2. Akar Masalah (Root Cause)">
          <div className="space-y-3">
            <Textarea
              label="Akar Penyebab Utama"
              rows={3}
              value={form.rootCause}
              onChange={(e) => update('rootCause', e.target.value)}
              hint="Gunakan metode 5-Why untuk menemukan akar penyebab yang sebenarnya"
            />
            <Textarea
              label="Faktor Penyebab Tambahan"
              rows={2}
              value={form.contributing}
              onChange={(e) => update('contributing', e.target.value)}
            />
            <div className="grid grid-cols-2 gap-3">
              <Select
                label="Kategori Root Cause"
                value={form.category}
                onChange={(e) => update('category', e.target.value)}
                options={[
                  { value: 'deployment', label: 'Deployment Error' },
                  { value: 'configuration', label: 'Konfigurasi' },
                  { value: 'code_bug', label: 'Bug pada Kode' },
                  { value: 'infrastructure', label: 'Infrastruktur' },
                  { value: 'human_error', label: 'Human Error' },
                  { value: 'third_party', label: 'Third-Party/Vendor' },
                  { value: 'data', label: 'Data Issue' },
                ]}
              />
              <Select
                label="Apakah pernah terjadi sebelumnya?"
                value={form.recurrence}
                onChange={(e) => update('recurrence', e.target.value)}
                options={[
                  { value: 'tidak', label: 'Tidak — Pertama kali' },
                  { value: 'ya', label: 'Ya — Pernah terjadi sebelumnya' },
                  { value: 'mirip', label: 'Mirip — Masalah serupa' },
                ]}
              />
            </div>
          </div>
        </SectionCard>

        {/* Impact */}
        <SectionCard title="3. Dampak (Impact Assessment)">
          <Textarea
            label="Dampak Bisnis & Operasional"
            rows={3}
            value={form.impact}
            onChange={(e) => update('impact', e.target.value)}
          />
        </SectionCard>

        {/* Solution */}
        <SectionCard title="4. Solusi yang Diterapkan">
          <Textarea
            label="Langkah Penyelesaian"
            rows={4}
            value={form.solution}
            onChange={(e) => update('solution', e.target.value)}
          />
        </SectionCard>

        {/* Preventive */}
        <SectionCard title="5. Tindakan Pencegahan (Corrective Action)">
          <Textarea
            label="Langkah Pencegahan Berulang"
            rows={5}
            value={form.preventive}
            onChange={(e) => update('preventive', e.target.value)}
            hint="Tindakan yang harus dilakukan untuk mencegah masalah serupa di masa depan"
          />
        </SectionCard>

        <div className="flex justify-end gap-3">
          <Button variant="secondary" onClick={() => navigate('/pic/workspace')}>
            Batal
          </Button>
          <Button variant="primary" onClick={handleSave}>
            💾 Simpan RCA
          </Button>
        </div>
      </div>
    </div>
  )
}
