import { useEffect, useState } from 'react'
import { AlertTriangle, Ban, Copy, ExternalLink, Link2, RefreshCw, RotateCw, ShieldCheck } from 'lucide-react'
import { ticketService, type PublicTrackingAccess, type PublicTrackingIssueResult } from '../../services/ticketService'
import { Button, Modal, Textarea, Toast } from '../ui'

type Action = 'issue' | 'rotate' | 'revoke'

const statePresentation = {
  never_issued: { label: 'Belum dibuat', classes: 'border-slate-200 bg-slate-100 text-slate-700' },
  active: { label: 'Aktif', classes: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
  expired: { label: 'Kedaluwarsa', classes: 'border-amber-200 bg-amber-50 text-amber-800' },
  revoked: { label: 'Dicabut', classes: 'border-red-200 bg-red-50 text-red-700' },
} as const

const actionPresentation = {
  issue: {
    title: 'Buat link tracking baru',
    warning: 'Link baru akan memberikan akses read-only ke status publik tiket ini.',
    submit: 'Buat Link Baru',
  },
  rotate: {
    title: 'Rotasi link tracking',
    warning: 'Semua link lama akan langsung tidak berlaku setelah rotasi berhasil.',
    submit: 'Rotasi Link',
  },
  revoke: {
    title: 'Cabut link tracking',
    warning: 'Requester tidak dapat membuka tracking sampai link baru dibuat.',
    submit: 'Cabut Link',
  },
} as const

function dateTime(value: string | null): string {
  return value ? new Date(value).toLocaleString('id-ID') : '-'
}

export function PublicTrackingAccessPanel({ ticketId, onChanged }: { ticketId: number; onChanged: () => void }) {
  const [access, setAccess] = useState<PublicTrackingAccess | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [action, setAction] = useState<Action | null>(null)
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState('')
  const [idempotencyKey, setIdempotencyKey] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [notice, setNotice] = useState<{ message: string; type: 'success' | 'error' } | null>(null)
  const [copyError, setCopyError] = useState(false)

  const loadAccess = async () => {
    setLoadError(false)
    try {
      setAccess(await ticketService.publicTrackingAccess(ticketId))
    } catch {
      setLoadError(true)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadAccess()
  }, [ticketId])

  const openAction = (nextAction: Action) => {
    setAction(nextAction)
    setReason('')
    setReasonError('')
    setIdempotencyKey(crypto.randomUUID())
  }

  const closeAction = () => {
    if (!submitting) setAction(null)
  }

  const submit = async () => {
    if (!action || submitting) return
    if (!reason.trim()) {
      setReasonError('Alasan tindakan wajib diisi.')
      return
    }

    setSubmitting(true)
    setReasonError('')
    try {
      let issued: PublicTrackingIssueResult | null = null
      if (action === 'issue') issued = await ticketService.issuePublicTracking(ticketId, reason.trim(), idempotencyKey)
      if (action === 'rotate')
        issued = await ticketService.rotatePublicTracking(ticketId, reason.trim(), idempotencyKey)
      if (action === 'revoke') await ticketService.revokePublicTracking(ticketId, reason.trim(), idempotencyKey)

      setAction(null)
      setNotice({
        type: 'success',
        message: action === 'revoke' ? 'Link tracking berhasil dicabut.' : 'Link tracking baru siap dibagikan.',
      })
      setCopyError(false)
      await loadAccess()
      if (issued) {
        setAccess((current) =>
          current
            ? {
                ...current,
                state: 'active',
                tracking_url: issued.tracking_url,
                expires_at: issued.tracking_expires_at,
                revoked_at: null,
                link_recoverable: true,
                can_issue: false,
                can_rotate: true,
                can_revoke: true,
              }
            : current,
        )
      }
      onChanged()
    } catch {
      setNotice({ type: 'error', message: 'Tindakan tidak dapat diproses. Muat ulang status lalu coba kembali.' })
    } finally {
      setSubmitting(false)
    }
  }

  const copyLink = async () => {
    if (!access?.tracking_url) return
    try {
      await navigator.clipboard.writeText(access.tracking_url)
      setCopyError(false)
      setNotice({ type: 'success', message: 'Link tracking berhasil disalin.' })
    } catch {
      setCopyError(true)
    }
  }

  const presentation = access ? statePresentation[access.state] : statePresentation.never_issued

  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="border-b border-slate-200 bg-gradient-to-r from-slate-950 to-blue-950 px-4 py-5 text-white sm:px-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex min-w-0 gap-3">
            <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-400/15 text-cyan-200">
              <ShieldCheck className="h-5 w-5" aria-hidden="true" />
            </span>
            <div>
              <p className="text-xs font-bold uppercase tracking-[0.16em] text-cyan-200">Pengajuan Publik</p>
              <h2 className="mt-1 text-lg font-bold">Akses Tracking Requester</h2>
              <p className="mt-1 max-w-2xl text-sm text-slate-300">
                Kelola akses read-only tanpa mengubah workflow atau status tiket.
              </p>
            </div>
          </div>
          {!loading && access && (
            <span className={`w-fit rounded-full border px-3 py-1 text-xs font-bold ${presentation.classes}`}>
              {presentation.label}
            </span>
          )}
        </div>
      </div>

      <div className="space-y-5 p-4 sm:p-6">
        {loading && (
          <div className="flex items-center gap-3 py-6 text-sm text-slate-600">
            <RefreshCw className="h-4 w-4 animate-spin" aria-hidden="true" /> Memuat status akses...
          </div>
        )}

        {loadError && !loading && (
          <div className="flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm font-medium text-red-800">Status akses tidak dapat dimuat.</p>
            <Button variant="secondary" size="sm" onClick={() => void loadAccess()}>
              <RefreshCw className="h-4 w-4" /> Coba Lagi
            </Button>
          </div>
        )}

        {access && !loading && (
          <>
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {[
                ['Link dibuat', dateTime(access.created_at)],
                ['Kedaluwarsa', dateTime(access.expires_at)],
                ['Terakhir digunakan', dateTime(access.last_used_at)],
                ['Dicabut pada', dateTime(access.revoked_at)],
              ].map(([label, value]) => (
                <div key={label} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                  <dt className="text-[11px] font-bold uppercase tracking-wide text-slate-500">{label}</dt>
                  <dd className="mt-1 text-sm font-semibold text-slate-900">{value}</dd>
                </div>
              ))}
            </dl>

            {access.state === 'active' && access.tracking_url && (
              <div className="rounded-xl border border-cyan-200 bg-cyan-50/70 p-4">
                <div className="flex items-center gap-2 text-sm font-bold text-cyan-950">
                  <Link2 className="h-4 w-4" aria-hidden="true" /> Link tracking aktif
                </div>
                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                  <input
                    readOnly
                    value={access.tracking_url}
                    onFocus={(event) => event.currentTarget.select()}
                    aria-label="Link tracking aktif"
                    className="min-w-0 flex-1 rounded-lg border border-cyan-200 bg-white px-3 py-2 font-mono text-xs text-slate-800 outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-100"
                  />
                  <Button
                    variant="secondary"
                    onClick={() => void copyLink()}
                    className="w-full justify-center sm:w-auto"
                  >
                    <Copy className="h-4 w-4" /> Salin Link
                  </Button>
                  <a
                    href={access.tracking_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 sm:w-auto"
                  >
                    <ExternalLink className="h-4 w-4" /> Buka
                  </a>
                </div>
                {copyError && (
                  <p className="mt-2 text-xs font-medium text-amber-800" role="alert">
                    Clipboard tidak tersedia. Pilih link pada kolom di atas lalu salin secara manual.
                  </p>
                )}
              </div>
            )}

            {access.state === 'active' && !access.link_recoverable && (
              <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Link aktif tidak dapat dipulihkan secara aman. Rotasi link untuk membuat link pengganti.
              </div>
            )}

            <div className="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
              <p className="max-w-2xl text-xs leading-5 text-slate-500">
                Aktivitas pembuatan, rotasi, dan pencabutan dicatat pada audit tiket tanpa menyimpan token atau URL.
              </p>
              <div className="flex flex-col gap-2 sm:flex-row">
                {access.can_issue && (
                  <Button onClick={() => openAction('issue')} className="w-full justify-center sm:w-auto">
                    <Link2 className="h-4 w-4" /> Buat Link Baru
                  </Button>
                )}
                {access.can_rotate && (
                  <Button
                    variant="warning"
                    onClick={() => openAction('rotate')}
                    className="w-full justify-center sm:w-auto"
                  >
                    <RotateCw className="h-4 w-4" /> Rotasi Link
                  </Button>
                )}
                {access.can_revoke && (
                  <Button
                    variant="danger"
                    onClick={() => openAction('revoke')}
                    className="w-full justify-center sm:w-auto"
                  >
                    <Ban className="h-4 w-4" /> Cabut Link
                  </Button>
                )}
              </div>
            </div>
          </>
        )}
      </div>

      <Modal
        open={action !== null}
        onClose={closeAction}
        title={action ? actionPresentation[action].title : ''}
        size="md"
      >
        {action && (
          <div className="space-y-5">
            <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
              <p>{actionPresentation[action].warning}</p>
            </div>
            <Textarea
              label="Alasan tindakan"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              error={reasonError}
              maxLength={1000}
              rows={4}
              disabled={submitting}
              placeholder="Contoh: requester kehilangan link tracking sebelumnya."
            />
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button variant="secondary" onClick={closeAction} disabled={submitting} className="justify-center">
                Batal
              </Button>
              <Button
                variant={action === 'revoke' ? 'danger' : action === 'rotate' ? 'warning' : 'primary'}
                onClick={() => void submit()}
                loading={submitting}
                className="justify-center"
              >
                {actionPresentation[action].submit}
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {notice && <Toast message={notice.message} type={notice.type} onClose={() => setNotice(null)} />}
    </section>
  )
}
