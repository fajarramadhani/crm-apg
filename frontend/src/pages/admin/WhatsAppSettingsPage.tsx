import { useEffect, useState } from 'react'
import { ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react'
import { ApiRequestError } from '../../api/client'
import {
  whatsAppService,
  type WhatsAppDeviceProfile,
  type WhatsAppHistoryPage,
  type WhatsAppSettings,
  type WhatsAppStatus,
} from '../../services/whatsAppService'

const RETRYABLE_STATUSES: WhatsAppStatus[] = ['failed', 'invalid', 'expired']
const EVENT_LABELS: Record<string, string> = {
  ticket_created: 'Tiket dibuat',
  ticket_assigned: 'Tiket ditugaskan',
  important_status_changed: 'Perubahan status penting',
  sla_warning: 'Peringatan SLA',
  sla_breached: 'Pelanggaran SLA',
  ticket_completed: 'Tiket selesai',
}
const STATUS_STYLES: Record<WhatsAppStatus, string> = {
  queued: 'bg-slate-100 text-slate-700',
  processing: 'bg-blue-100 text-blue-700',
  sent: 'bg-emerald-100 text-emerald-700',
  pending: 'bg-amber-100 text-amber-800',
  failed: 'bg-rose-100 text-rose-700',
  invalid: 'bg-orange-100 text-orange-800',
  expired: 'bg-gray-200 text-gray-700',
}

function errorMessage(error: unknown, fallback: string) {
  return error instanceof ApiRequestError ? error.message : fallback
}

function formatDate(value: string | null) {
  return value ? new Date(value).toLocaleString('id-ID') : '-'
}

function maskDevice(value: string | null | undefined) {
  if (!value) return '-'
  if (value.includes('*') || value.length <= 8) return value
  return `${value.slice(0, 4)}${'*'.repeat(Math.max(4, value.length - 8))}${value.slice(-4)}`
}

function StatusBadge({ status }: { status: WhatsAppStatus }) {
  return (
    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase ${STATUS_STYLES[status]}`}>
      {status}
    </span>
  )
}

export default function WhatsAppSettingsPage() {
  const [settings, setSettings] = useState<WhatsAppSettings | null>(null)
  const [settingsLoading, setSettingsLoading] = useState(true)
  const [settingsError, setSettingsError] = useState<string | null>(null)
  const [draftEnabled, setDraftEnabled] = useState(false)
  const [draftEvents, setDraftEvents] = useState<Record<string, boolean>>({})
  const [savingSettings, setSavingSettings] = useState(false)
  const [saveResult, setSaveResult] = useState<{ message: string; error: boolean } | null>(null)
  const [device, setDevice] = useState<WhatsAppDeviceProfile | null>(null)
  const [deviceLoading, setDeviceLoading] = useState(true)
  const [deviceError, setDeviceError] = useState<string | null>(null)
  const [history, setHistory] = useState<WhatsAppHistoryPage | null>(null)
  const [historyLoading, setHistoryLoading] = useState(true)
  const [historyError, setHistoryError] = useState<string | null>(null)
  const [historyPage, setHistoryPage] = useState(1)
  const [retryingId, setRetryingId] = useState<number | null>(null)
  const [testNote, setTestNote] = useState('')
  const [sendingTest, setSendingTest] = useState(false)
  const [testResult, setTestResult] = useState<{ message: string; error: boolean } | null>(null)
  const [showModal, setShowModal] = useState(false)

  const fetchSettings = async () => {
    setSettingsLoading(true)
    setSettingsError(null)
    try {
      const result = await whatsAppService.getSettings()
      setSettings(result)
      setDraftEnabled(result.enabled)
      setDraftEvents(result.events)
    } catch (error: unknown) {
      setSettingsError(errorMessage(error, 'Gagal memuat pengaturan WhatsApp Fonnte.'))
    } finally {
      setSettingsLoading(false)
    }
  }

  const fetchDevice = async () => {
    setDeviceLoading(true)
    setDeviceError(null)
    try {
      setDevice(await whatsAppService.getDeviceProfile())
    } catch (error: unknown) {
      setDevice(null)
      setDeviceError(errorMessage(error, 'Koneksi ke perangkat Fonnte gagal diuji.'))
    } finally {
      setDeviceLoading(false)
    }
  }

  const fetchHistory = async (page: number) => {
    setHistoryLoading(true)
    setHistoryError(null)
    try {
      const result = await whatsAppService.getHistory(page)
      setHistory(result)
      setHistoryPage(result.current_page)
    } catch (error: unknown) {
      setHistoryError(errorMessage(error, 'Gagal memuat riwayat pengiriman.'))
    } finally {
      setHistoryLoading(false)
    }
  }

  useEffect(() => {
    void fetchSettings()
    void fetchDevice()
    void fetchHistory(1)
  }, [])

  const handleSendTestMessage = async () => {
    setSendingTest(true)
    setTestResult(null)
    try {
      const result = await whatsAppService.sendTestMessage(testNote.trim() || undefined)
      setTestResult({ message: result.message, error: false })
      setShowModal(false)
      setTestNote('')
      void fetchSettings()
      void fetchHistory(1)
    } catch (error: unknown) {
      setTestResult({ message: errorMessage(error, 'Gagal membuat pesan pengujian.'), error: true })
    } finally {
      setSendingTest(false)
    }
  }

  const handleSaveSettings = async () => {
    setSavingSettings(true)
    setSaveResult(null)
    try {
      const result = await whatsAppService.updateSettings({ enabled: draftEnabled, events: draftEvents })
      setSettings(result)
      setDraftEnabled(result.enabled)
      setDraftEvents(result.events)
      setSaveResult({ message: 'Pengaturan runtime WhatsApp berhasil disimpan.', error: false })
    } catch (error: unknown) {
      setSaveResult({
        message: errorMessage(error, 'Pengaturan tidak tersimpan. Periksa kembali lalu coba lagi.'),
        error: true,
      })
    } finally {
      setSavingSettings(false)
    }
  }

  const handleRetry = async (id: number) => {
    setRetryingId(id)
    setHistoryError(null)
    try {
      await whatsAppService.retryMessage(id)
      await Promise.all([fetchHistory(historyPage), fetchSettings()])
    } catch (error: unknown) {
      setHistoryError(errorMessage(error, 'Pengiriman tidak dapat diulang.'))
    } finally {
      setRetryingId(null)
    }
  }

  const deviceStatus = String(device?.data.device_status ?? device?.data.status ?? device?.status ?? '-').toUpperCase()
  const deviceConnected = device?.success === true
  const settingsDirty =
    settings !== null &&
    (draftEnabled !== settings.enabled ||
      Object.keys(EVENT_LABELS).some((key) => draftEvents[key] !== settings.events[key]))

  return (
    <div className="mx-auto max-w-7xl p-4 sm:p-6">
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">WhatsApp Fonnte</h1>
          <p className="mt-1 text-sm text-gray-500">
            Konfigurasi, koneksi perangkat, dan pengiriman notifikasi IT Support.
          </p>
        </div>
        <button
          onClick={() => setShowModal(true)}
          disabled={!settings?.ready || settingsLoading}
          className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-400"
        >
          Kirim Pesan Uji
        </button>
      </div>

      {testResult && (
        <div
          className={`mb-6 rounded-lg p-4 text-sm ${testResult.error ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-800'}`}
        >
          {testResult.message}
        </div>
      )}

      {settingsError ? (
        <div className="mb-6 rounded-lg bg-red-50 p-4 text-red-700">
          <p className="font-semibold">{settingsError}</p>
          <button
            onClick={() => void fetchSettings()}
            className="mt-3 rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
          >
            Coba Lagi
          </button>
        </div>
      ) : settingsLoading ? (
        <div className="mb-6 rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm">
          Memuat pengaturan Fonnte...
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Status Layanan</span>
              <div className="mt-2 flex items-center gap-3">
                <span
                  className={`rounded-full px-3 py-1 text-xs font-semibold ${settings?.ready ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}`}
                >
                  {settings?.ready ? 'SIAP' : 'BELUM SIAP'}
                </span>
                <span className="text-sm font-semibold text-gray-600">FONNTE</span>
              </div>
              <p className="mt-3 text-xs text-gray-500">{settings?.status_message}</p>
            </div>
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Nomor IT Support</span>
              <div className="mt-2 font-mono text-xl font-bold text-gray-800">
                {settings?.it_support_number_masked || '-'}
              </div>
              <p className="mt-3 text-xs text-gray-500">Tujuan tetap untuk pesan uji dan notifikasi internal.</p>
            </div>
            <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
              <span className="text-xs font-semibold uppercase tracking-wider text-gray-400">Pengiriman Terakhir</span>
              <div className="mt-2 text-sm font-medium text-gray-800">
                {settings?.last_sent_at ? formatDate(settings.last_sent_at) : 'Belum ada pengiriman'}
              </div>
              <p className="mt-3 text-xs text-gray-500">Waktu terakhir pesan diterima Fonnte sebagai terkirim.</p>
            </div>
          </div>

          <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900">Konfigurasi Fonnte</h2>
            <div className="mt-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800">
              Kredensial provider, nomor IT Support, dan parameter gateway dikelola melalui environment server. Nilai
              tersebut hanya ditampilkan sebagai status dan tidak dapat diedit dari halaman ini.
            </div>
            <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
              <div>
                <p className="text-xs text-gray-500">Base URL</p>
                <p className="mt-1 truncate font-medium text-gray-800" title={settings?.base_url}>
                  {settings?.base_url || '-'}
                </p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Kode negara</p>
                <p className="mt-1 font-medium text-gray-800">{settings?.country_code || '-'}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Connect only</p>
                <p className="mt-1 font-medium text-gray-800">{settings?.connect_only ? 'Ya' : 'Tidak'}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Timeout</p>
                <p className="mt-1 font-medium text-gray-800">{settings?.timeout ?? '-'} detik</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Percobaan gateway</p>
                <p className="mt-1 font-medium text-gray-800">{settings?.retry_times ?? '-'}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">Rahasia</p>
                <p className="mt-1 font-medium text-gray-800">
                  Token {settings?.token_configured ? 'siap' : 'belum siap'} · Webhook{' '}
                  {settings?.webhook_secret_configured ? 'siap' : 'belum siap'}
                </p>
              </div>
            </div>
          </div>

          <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900">Statistik Pengiriman</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
              {(['total', 'queued', 'processing', 'pending', 'sent', 'failed', 'invalid', 'expired'] as const).map(
                (status) => (
                  <div key={status} className="rounded-lg bg-gray-50 p-3 text-center">
                    <div className="text-xs capitalize text-gray-500">{status}</div>
                    <div className="mt-1 text-xl font-bold text-gray-900">{settings?.stats[status] ?? 0}</div>
                  </div>
                ),
              )}
            </div>
          </div>
        </>
      )}

      <div className="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-bold text-gray-900">Perangkat Fonnte</h2>
            <p className="mt-1 text-xs text-gray-500">
              Profil perangkat dimuat langsung dari Fonnte tanpa menampilkan token.
            </p>
          </div>
          <button
            onClick={() => void fetchDevice()}
            disabled={deviceLoading || settings?.token_configured === false}
            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <RefreshCw className={`h-4 w-4 ${deviceLoading ? 'animate-spin' : ''}`} />
            Uji Koneksi
          </button>
        </div>
        {deviceLoading ? (
          <p className="mt-5 text-sm text-gray-500">Menghubungi perangkat Fonnte...</p>
        ) : deviceError ? (
          <div className="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-700">{deviceError}</div>
        ) : (
          <div className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
              <p className="text-xs text-gray-500">Koneksi</p>
              <p className={`mt-1 font-semibold ${deviceConnected ? 'text-emerald-700' : 'text-rose-700'}`}>
                {deviceConnected ? 'TERHUBUNG' : 'BERMASALAH'}
              </p>
            </div>
            <div>
              <p className="text-xs text-gray-500">Status perangkat</p>
              <p className="mt-1 font-semibold text-gray-800">{deviceStatus}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500">Perangkat</p>
              <p className="mt-1 font-mono font-semibold text-gray-800">{maskDevice(device?.data.device)}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500">Paket</p>
              <p className="mt-1 font-semibold text-gray-800">{String(device?.data.package ?? '-')}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500">Kuota / Pesan</p>
              <p className="mt-1 font-semibold text-gray-800">
                {String(device?.data.quota ?? '-')} / {String(device?.data.messages ?? '-')}
              </p>
            </div>
            <div>
              <p className="text-xs text-gray-500">Berakhir</p>
              <p className="mt-1 font-semibold text-gray-800">{String(device?.data.expired ?? '-')}</p>
            </div>
            {device?.message && (
              <div className="col-span-full rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{device.message}</div>
            )}
          </div>
        )}
      </div>

      {!settingsLoading && !settingsError && (
        <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-bold text-gray-900">Kontrol Runtime</h2>
                <p className="mt-1 text-xs text-gray-500">
                  {settings?.has_database_override
                    ? 'Menggunakan pengaturan tersimpan di database.'
                    : 'Belum ada override database; nilai saat ini berasal dari environment.'}
                </p>
              </div>
              <span
                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${settingsDirty ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}
              >
                {settingsDirty ? 'BELUM DISIMPAN' : 'TERSIMPAN'}
              </span>
            </div>

            <div className="mt-4 flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-4">
              <div>
                <p className="text-sm font-semibold text-gray-800">Aktifkan notifikasi WhatsApp</p>
                <p className="mt-1 text-xs text-gray-500">Kontrol global untuk seluruh pengiriman otomatis.</p>
              </div>
              <button
                type="button"
                role="switch"
                aria-checked={draftEnabled}
                aria-label="Aktifkan notifikasi WhatsApp"
                onClick={() => {
                  setDraftEnabled((enabled) => !enabled)
                  setSaveResult(null)
                }}
                disabled={savingSettings}
                className={`relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:opacity-50 ${draftEnabled ? 'bg-emerald-600' : 'bg-gray-300'}`}
              >
                <span
                  className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform ${draftEnabled ? 'translate-x-5' : 'translate-x-0.5'}`}
                />
              </button>
            </div>

            <h3 className="mt-5 text-sm font-bold text-gray-800">Event Notifikasi</h3>
            <div className="mt-4 space-y-3">
              {Object.entries(EVENT_LABELS)
                .filter(([key]) => key in (settings?.events || {}))
                .map(([key, label]) => (
                  <div
                    key={key}
                    className="flex items-center justify-between gap-4 rounded-lg border border-gray-100 p-3"
                  >
                    <div>
                      <p className="text-sm font-medium text-gray-700">{label}</p>
                      <p className="mt-0.5 text-xs text-gray-400">{key}</p>
                    </div>
                    <button
                      type="button"
                      role="switch"
                      aria-checked={draftEvents[key] ?? false}
                      aria-label={`Aktifkan event ${label}`}
                      onClick={() => {
                        setDraftEvents((events) => ({ ...events, [key]: !events[key] }))
                        setSaveResult(null)
                      }}
                      disabled={savingSettings}
                      className={`relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:opacity-50 ${draftEvents[key] ? 'bg-emerald-600' : 'bg-gray-300'}`}
                    >
                      <span
                        className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform ${draftEvents[key] ? 'translate-x-5' : 'translate-x-0.5'}`}
                      />
                    </button>
                  </div>
                ))}
            </div>

            {saveResult && (
              <div
                className={`mt-4 rounded-lg p-3 text-sm ${saveResult.error ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800'}`}
              >
                {saveResult.message}
              </div>
            )}

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
              <p className="text-xs text-gray-500">
                Status efektif: <span className="font-semibold">{settings?.enabled ? 'aktif' : 'nonaktif'}</span>
              </p>
              <button
                onClick={() => void handleSaveSettings()}
                disabled={!settingsDirty || savingSettings}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300"
              >
                {savingSettings ? 'Menyimpan...' : 'Simpan Pengaturan'}
              </button>
            </div>
          </div>
          <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-bold text-gray-900">Template Pesan</h2>
            <p className="mt-1 text-xs text-gray-500">Pratinjau dipotong agar isi pesan tetap mudah dipindai.</p>
            <div className="mt-4 space-y-3">
              {Object.entries(settings?.templates || {}).map(([key, message]) => (
                <div key={key} className="rounded-lg border border-gray-100 p-3">
                  <p className="text-sm font-semibold text-gray-700">{key}</p>
                  <p className="mt-1 truncate text-xs text-gray-500" title={message}>
                    {message}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      <div className="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 sm:p-6">
          <div>
            <h2 className="text-lg font-bold text-gray-900">Riwayat Pengiriman Terbaru</h2>
            <p className="mt-1 text-xs text-gray-500">
              Penerima dan pesan ditampilkan secara terbatas untuk menjaga keamanan data.
            </p>
          </div>
          <button
            onClick={() => void fetchHistory(historyPage)}
            disabled={historyLoading}
            className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          >
            <RefreshCw className={`h-4 w-4 ${historyLoading ? 'animate-spin' : ''}`} /> Segarkan
          </button>
        </div>
        {historyError && <div className="m-5 rounded-lg bg-red-50 p-4 text-sm text-red-700 sm:m-6">{historyError}</div>}
        {historyLoading && !history ? (
          <div className="p-6 text-sm text-gray-500">Memuat riwayat pengiriman...</div>
        ) : history?.data.length === 0 ? (
          <div className="p-6 text-sm text-gray-500">Belum ada riwayat pengiriman.</div>
        ) : (
          <>
            <div className="hidden overflow-x-auto md:block">
              <table className="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead className="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                  <tr>
                    <th className="px-5 py-3">Event / Tiket</th>
                    <th className="px-5 py-3">Penerima</th>
                    <th className="px-5 py-3">Status</th>
                    <th className="px-5 py-3">Percobaan</th>
                    <th className="px-5 py-3">Waktu</th>
                    <th className="px-5 py-3">Keterangan</th>
                    <th className="px-5 py-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {history?.data.map((item) => (
                    <tr key={item.id} className="align-top">
                      <td className="px-5 py-4">
                        <p className="font-semibold text-gray-800">{item.event_type}</p>
                        <p className="mt-1 text-xs text-gray-500">{item.ticket_number || 'Tanpa tiket'}</p>
                      </td>
                      <td className="px-5 py-4 font-mono text-gray-700">{item.recipient_masked}</td>
                      <td className="px-5 py-4">
                        <StatusBadge status={item.status} />
                      </td>
                      <td className="px-5 py-4 text-gray-700">{item.attempts}</td>
                      <td className="whitespace-nowrap px-5 py-4 text-xs text-gray-600">
                        <p>Antre: {formatDate(item.queued_at)}</p>
                        <p className="mt-1">Selesai: {formatDate(item.sent_at || item.failed_at)}</p>
                      </td>
                      <td className="max-w-xs px-5 py-4 text-xs text-gray-600">
                        <p className="truncate" title={item.failure_message || item.failure_code || item.template_name}>
                          {item.failure_message || item.failure_code || item.template_name}
                        </p>
                      </td>
                      <td className="px-5 py-4 text-right">
                        {RETRYABLE_STATUSES.includes(item.status) && (
                          <button
                            onClick={() => void handleRetry(item.id)}
                            disabled={retryingId === item.id}
                            className="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                          >
                            {retryingId === item.id ? 'Memproses...' : 'Ulangi'}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="divide-y divide-gray-100 md:hidden">
              {history?.data.map((item) => (
                <div key={item.id} className="p-5">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-semibold text-gray-900">{item.event_type}</p>
                      <p className="mt-1 text-xs text-gray-500">
                        {item.ticket_number || 'Tanpa tiket'} · {item.recipient_masked}
                      </p>
                    </div>
                    <StatusBadge status={item.status} />
                  </div>
                  <div className="mt-3 grid grid-cols-2 gap-3 text-xs text-gray-600">
                    <div>
                      <span className="text-gray-400">Percobaan</span>
                      <p className="mt-1 font-medium text-gray-700">{item.attempts}</p>
                    </div>
                    <div>
                      <span className="text-gray-400">Masuk antrean</span>
                      <p className="mt-1 font-medium text-gray-700">{formatDate(item.queued_at)}</p>
                    </div>
                  </div>
                  {(item.failure_message || item.failure_code) && (
                    <p className="mt-3 rounded-lg bg-rose-50 p-3 text-xs text-rose-700">
                      {item.failure_message || item.failure_code}
                    </p>
                  )}
                  {RETRYABLE_STATUSES.includes(item.status) && (
                    <button
                      onClick={() => void handleRetry(item.id)}
                      disabled={retryingId === item.id}
                      className="mt-3 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                    >
                      {retryingId === item.id ? 'Memproses...' : 'Ulangi Pengiriman'}
                    </button>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
        {history && history.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-gray-200 px-5 py-4 text-sm text-gray-600 sm:px-6">
            <span>
              {history.from}-{history.to} dari {history.total}
            </span>
            <div className="flex items-center gap-2">
              <button
                aria-label="Halaman sebelumnya"
                onClick={() => void fetchHistory(historyPage - 1)}
                disabled={historyLoading || historyPage <= 1}
                className="rounded-lg border border-gray-300 p-2 hover:bg-gray-50 disabled:opacity-40"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <span className="px-2">
                {historyPage} / {history.last_page}
              </span>
              <button
                aria-label="Halaman berikutnya"
                onClick={() => void fetchHistory(historyPage + 1)}
                disabled={historyLoading || historyPage >= history.last_page}
                className="rounded-lg border border-gray-300 p-2 hover:bg-gray-50 disabled:opacity-40"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900">Kirim Pesan Uji Fonnte</h3>
            <p className="mt-2 text-sm text-gray-600">
              Pesan hanya akan dikirim ke nomor IT Support yang dikonfigurasi:{' '}
              <span className="font-mono font-semibold">{settings?.it_support_number_masked}</span>.
            </p>
            <label className="mt-4 block text-xs font-semibold text-gray-700">Catatan pengujian (opsional)</label>
            <input
              type="text"
              maxLength={100}
              value={testNote}
              onChange={(event) => setTestNote(event.target.value)}
              placeholder="Contoh: Uji koneksi notifikasi"
              className="mt-1 w-full rounded-lg border border-gray-300 p-2 text-sm focus:border-emerald-500 focus:outline-none"
            />
            <div className="mt-6 flex justify-end gap-3">
              <button
                onClick={() => setShowModal(false)}
                disabled={sendingTest}
                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              >
                Batal
              </button>
              <button
                onClick={() => void handleSendTestMessage()}
                disabled={sendingTest}
                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              >
                {sendingTest ? 'Mengantrekan...' : 'Kirim ke IT Support'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
