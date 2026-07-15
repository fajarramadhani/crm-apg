import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { Button, Card, Input, PageHeader, Select, Textarea, Toast } from '../../components/ui'
import {
  masterDataService,
  type Application,
  type ApplicationModule,
  type TicketCategory,
  type TicketPriority,
} from '../../services/masterDataService'
import { ticketService, type TicketPayload } from '../../services/ticketService'

const emptyForm = {
  categoryId: '',
  applicationId: '',
  moduleId: '',
  priorityId: '',
  title: '',
  description: '',
  businessImpact: '',
  urgency: 'normal',
  expectedResult: '',
  actualResult: '',
  reproductionSteps: '',
  requestPurpose: '',
  changeReason: '',
  expectedImpact: '',
  recurringIndication: '',
}

export default function CreateTicket() {
  const navigate = useNavigate()
  const [step, setStep] = useState(1)
  const [form, setForm] = useState(emptyForm)
  const [categories, setCategories] = useState<TicketCategory[]>([])
  const [applications, setApplications] = useState<Application[]>([])
  const [modules, setModules] = useState<ApplicationModule[]>([])
  const [priorities, setPriorities] = useState<TicketPriority[]>([])
  const [files, setFiles] = useState<File[]>([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([
      masterDataService.getTicketCategories(),
      masterDataService.getApplications(),
      masterDataService.getTicketPriorities(),
    ])
      .then(([categoryData, applicationData, priorityData]) => {
        setCategories(categoryData)
        setApplications(applicationData)
        setPriorities(priorityData)
        setForm((current) => ({
          ...current,
          categoryId: String(categoryData[0]?.id ?? ''),
          priorityId: String(priorityData.find((p) => p.key === 'medium')?.id ?? ''),
        }))
      })
      .catch(() => setError('Master data formulir tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (!form.applicationId) {
      setModules([])
      return
    }
    masterDataService
      .getApplicationModules(Number(form.applicationId))
      .then(setModules)
      .catch(() => setModules([]))
  }, [form.applicationId])

  const category = useMemo(
    () => categories.find((item) => item.id === Number(form.categoryId)),
    [categories, form.categoryId],
  )
  const update = (field: keyof typeof form, value: string) => setForm((current) => ({ ...current, [field]: value }))
  const basicValid = Boolean(
    form.categoryId && form.title.trim() && (category?.type !== 'incident' || form.applicationId),
  )
  const detailValid = Boolean(
    form.description.trim() &&
    (category?.type !== 'request' || form.requestPurpose.trim()) &&
    (category?.type !== 'change' || form.changeReason.trim()) &&
    (category?.type !== 'problem' || form.recurringIndication.trim()),
  )

  const submit = async () => {
    setSubmitting(true)
    setError('')
    const payload: TicketPayload = {
      ticket_category_id: Number(form.categoryId),
      title: form.title,
      description: form.description,
      ...(form.applicationId && { application_id: Number(form.applicationId) }),
      ...(form.moduleId && { application_module_id: Number(form.moduleId) }),
      ...(form.priorityId && { requested_priority_id: Number(form.priorityId) }),
      business_impact: form.businessImpact || undefined,
      urgency: form.urgency,
      expected_result: form.expectedResult || undefined,
      actual_result: form.actualResult || undefined,
      reproduction_steps: form.reproductionSteps || undefined,
      request_purpose: form.requestPurpose || undefined,
      change_reason: form.changeReason || undefined,
      expected_impact: form.expectedImpact || undefined,
      recurring_indication: form.recurringIndication || undefined,
    }
    try {
      const ticket = await ticketService.create(payload)
      for (const file of files)
        await ticketService.upload(ticket.id, file, file.type.startsWith('image/') ? 'screenshot' : 'evidence')
      navigate(`/user/tickets/${ticket.id}`, { replace: true })
    } catch (cause) {
      const apiError = cause as ApiRequestError
      setError(apiError.errors ? Object.values(apiError.errors).flat()[0] : apiError.message)
      setSubmitting(false)
    }
  }

  if (loading)
    return (
      <div className="py-20 text-center text-sm text-gray-500" role="status">
        Memuat formulir tiket...
      </div>
    )

  return (
    <div className="max-w-3xl">
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      <PageHeader
        title="Buat Request / Tiket IT"
        subtitle="Sampaikan permintaan atau insiden IT Anda"
        actions={
          <Button variant="ghost" onClick={() => navigate('/user/dashboard')}>
            ← Kembali
          </Button>
        }
      />
      <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
        {[1, 2, 3].map((item) => (
          <div key={item} className="flex items-center gap-2 shrink-0">
            <div
              className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold ${item === step ? 'bg-[#1E3A8A] text-white' : item < step ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500'}`}
            >
              {item < step ? '✓' : item}
            </div>
            <span className={`text-sm ${item === step ? 'font-medium text-gray-900' : 'text-gray-400'}`}>
              {item === 1 ? 'Informasi Dasar' : item === 2 ? 'Detail' : 'Konfirmasi'}
            </span>
            {item < 3 && <div className="w-8 h-px bg-gray-300" />}
          </div>
        ))}
      </div>
      <Card className="p-6">
        {step === 1 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold border-b border-gray-100 pb-3">Informasi Dasar Tiket</h3>
            <Select
              label="Kategori Tiket *"
              value={form.categoryId}
              onChange={(event) => update('categoryId', event.target.value)}
              options={categories.map((item) => ({
                value: String(item.id),
                label: `${item.name} — ${item.description || item.type}`,
              }))}
            />
            <Input
              label="Judul Tiket *"
              value={form.title}
              maxLength={200}
              onChange={(event) => update('title', event.target.value)}
              placeholder="Deskripsikan kebutuhan secara singkat"
            />
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Select
                label={category?.type === 'incident' ? 'Aplikasi Terdampak *' : 'Aplikasi Terkait'}
                value={form.applicationId}
                onChange={(event) => {
                  update('applicationId', event.target.value)
                  update('moduleId', '')
                }}
                options={[
                  { value: '', label: '— Pilih aplikasi —' },
                  ...applications.map((item) => ({ value: String(item.id), label: item.name })),
                ]}
              />
              <Select
                label="Modul Aplikasi"
                value={form.moduleId}
                onChange={(event) => update('moduleId', event.target.value)}
                options={[
                  { value: '', label: '— Pilih modul —' },
                  ...modules.map((item) => ({ value: String(item.id), label: item.name })),
                ]}
                disabled={!form.applicationId}
              />
              <Select
                label="Saran Prioritas"
                value={form.priorityId}
                onChange={(event) => update('priorityId', event.target.value)}
                options={[
                  { value: '', label: '— Tanpa saran —' },
                  ...priorities.map((item) => ({ value: String(item.id), label: item.name })),
                ]}
              />
              <Select
                label="Urgensi"
                value={form.urgency}
                onChange={(event) => update('urgency', event.target.value)}
                options={[
                  { value: 'low', label: 'Low' },
                  { value: 'normal', label: 'Normal' },
                  { value: 'urgent', label: 'Urgent' },
                  { value: 'critical', label: 'Critical' },
                ]}
              />
            </div>
            <p className="text-xs text-gray-500">Divisi pelapor dan requester diambil aman dari profil login Anda.</p>
            <div className="flex justify-end">
              <Button variant="primary" disabled={!basicValid} onClick={() => setStep(2)}>
                Lanjut →
              </Button>
            </div>
          </div>
        )}
        {step === 2 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold border-b border-gray-100 pb-3">Detail {category?.name}</h3>
            <Textarea
              label="Deskripsi *"
              rows={4}
              value={form.description}
              onChange={(event) => update('description', event.target.value)}
            />
            {category?.type === 'incident' && (
              <>
                <Textarea
                  label="Langkah Reproduksi"
                  rows={3}
                  value={form.reproductionSteps}
                  onChange={(event) => update('reproductionSteps', event.target.value)}
                />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <Textarea
                    label="Hasil yang Diharapkan"
                    rows={3}
                    value={form.expectedResult}
                    onChange={(event) => update('expectedResult', event.target.value)}
                  />
                  <Textarea
                    label="Hasil Aktual"
                    rows={3}
                    value={form.actualResult}
                    onChange={(event) => update('actualResult', event.target.value)}
                  />
                </div>
              </>
            )}
            {category?.type === 'request' && (
              <Textarea
                label="Tujuan Kebutuhan *"
                rows={3}
                value={form.requestPurpose}
                onChange={(event) => update('requestPurpose', event.target.value)}
              />
            )}
            {category?.type === 'change' && (
              <>
                <Textarea
                  label="Alasan Perubahan *"
                  rows={3}
                  value={form.changeReason}
                  onChange={(event) => update('changeReason', event.target.value)}
                />
                <Textarea
                  label="Dampak yang Diperkirakan"
                  rows={3}
                  value={form.expectedImpact}
                  onChange={(event) => update('expectedImpact', event.target.value)}
                />
              </>
            )}
            {category?.type === 'problem' && (
              <Textarea
                label="Indikasi Masalah Berulang *"
                rows={3}
                value={form.recurringIndication}
                onChange={(event) => update('recurringIndication', event.target.value)}
              />
            )}
            <Textarea
              label="Dampak Bisnis"
              rows={2}
              value={form.businessImpact}
              onChange={(event) => update('businessImpact', event.target.value)}
            />
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1" htmlFor="attachments">
                Lampiran (maks. 10 file, masing-masing 10 MB)
              </label>
              <input
                id="attachments"
                type="file"
                multiple
                accept=".png,.jpg,.jpeg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx"
                onChange={(event) => setFiles(Array.from(event.target.files || []).slice(0, 10))}
                className="block w-full text-sm border border-dashed border-gray-300 rounded-xl p-4"
              />
              <p className="text-xs text-gray-500 mt-2">
                {files.length ? `${files.length} file dipilih` : 'PNG, JPG, PDF, TXT, CSV, DOC/DOCX, XLS/XLSX.'}
              </p>
            </div>
            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(1)}>
                ← Kembali
              </Button>
              <Button variant="primary" disabled={!detailValid} onClick={() => setStep(3)}>
                Lanjut →
              </Button>
            </div>
          </div>
        )}
        {step === 3 && (
          <div className="space-y-5">
            <h3 className="text-base font-semibold border-b border-gray-100 pb-3">Konfirmasi Tiket</h3>
            <div className="bg-gray-50 rounded-xl p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-xs text-gray-500">Kategori</p>
                <p className="font-medium">{category?.name}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Aplikasi</p>
                <p className="font-medium">
                  {applications.find((item) => item.id === Number(form.applicationId))?.name || 'Tidak ada'}
                </p>
              </div>
              <div className="sm:col-span-2">
                <p className="text-xs text-gray-500">Judul</p>
                <p className="font-medium">{form.title}</p>
              </div>
              <div className="sm:col-span-2">
                <p className="text-xs text-gray-500">Deskripsi</p>
                <p className="whitespace-pre-wrap">{form.description}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Lampiran</p>
                <p className="font-medium">{files.length} file</p>
              </div>
            </div>
            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
              Tiket akan langsung berstatus Pending Validation dan masuk ke antrean Supervisor divisi Anda.
            </div>
            <div className="flex justify-between">
              <Button variant="secondary" onClick={() => setStep(2)}>
                ← Kembali
              </Button>
              <Button variant="primary" disabled={submitting} onClick={() => void submit()}>
                {submitting ? 'Mengirim...' : 'Kirim Tiket'}
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  )
}
