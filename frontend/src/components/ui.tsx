import React from 'react'
import { getStatusColor, getPriorityColor, STATUS_LABELS, PRIORITY_LABELS, getSlaLabel } from '../presentation'
import type { TicketStatus, Priority } from '../types'
import {
  ClipboardList,
  AlertTriangle,
  Info,
  CheckCircle,
  XCircle,
  RefreshCw,
  MessageSquare,
  User,
  Paperclip,
  Siren,
} from 'lucide-react'

// ── Badge ─────────────────────────────────────────────────────────────────────
export function StatusBadge({ status }: { status: TicketStatus | string }) {
  return (
    <span
      className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${getStatusColor(status)}`}
    >
      {STATUS_LABELS[status] || status}
    </span>
  )
}

export function PriorityBadge({ priority }: { priority: Priority | string }) {
  const colors: Record<string, string> = {
    critical: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-yellow-500',
    low: 'bg-emerald-500',
  }
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ${getPriorityColor(priority)}`}
    >
      <span className={`w-2 h-2 rounded-full ${colors[priority] || 'bg-gray-400'}`} />
      {PRIORITY_LABELS[priority] || priority}
    </span>
  )
}

export function CategoryBadge({ category }: { category: string }) {
  const map: Record<string, string> = {
    incident: 'bg-red-50 text-red-600',
    request: 'bg-blue-50 text-blue-600',
    change: 'bg-purple-50 text-purple-600',
    problem: 'bg-orange-50 text-orange-600',
  }
  const labels: Record<string, string> = {
    incident: 'Incident',
    request: 'Request',
    change: 'Change',
    problem: 'Problem',
  }
  return (
    <span
      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${map[category] || 'bg-gray-100 text-gray-600'}`}
    >
      {labels[category] || category}
    </span>
  )
}

// ── SLA Indicator ─────────────────────────────────────────────────────────────
export function SLAIndicator({
  slaRemaining,
  overSla,
  slaHours,
}: {
  slaRemaining: number
  overSla: boolean
  slaHours: number
}) {
  const pct = overSla ? 100 : Math.max(0, Math.min(100, ((slaHours - Math.max(0, slaRemaining)) / slaHours) * 100))
  const color = overSla ? 'bg-red-500' : slaRemaining < 8 ? 'bg-amber-500' : 'bg-emerald-500'
  const textColor = overSla ? 'text-red-600' : slaRemaining < 8 ? 'text-amber-600' : 'text-emerald-600'
  return (
    <div className="w-full">
      <div className="flex justify-between items-center mb-1">
        <span className={`text-xs font-medium ${textColor}`}>{getSlaLabel(slaRemaining, overSla)}</span>
        <span className="text-xs text-gray-400">{slaHours}j SLA</span>
      </div>
      <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden">
        <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

// ── Buttons ───────────────────────────────────────────────────────────────────
interface BtnProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'warning' | 'success'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading,
  children,
  className = '',
  disabled,
  ...props
}: BtnProps) {
  const variants = {
    primary: 'bg-[#1E3A8A] hover:bg-[#1e40af] text-white shadow-sm',
    secondary: 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 shadow-sm',
    danger: 'bg-red-600 hover:bg-red-700 text-white shadow-sm',
    ghost: 'bg-transparent hover:bg-gray-100 text-gray-600',
    warning: 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm',
    success: 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm',
  }
  const sizes = { sm: 'px-3 py-1.5 text-xs', md: 'px-4 py-2 text-sm', lg: 'px-6 py-2.5 text-sm' }
  return (
    <button
      {...props}
      disabled={disabled || loading}
      className={`inline-flex items-center gap-2 font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${variants[variant]} ${sizes[size]} ${className}`}
    >
      {loading && <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />}
      {children}
    </button>
  )
}

// ── Input / Select / Textarea ─────────────────────────────────────────────────
interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
}

export function Input({ label, error, hint, className = '', id, ...props }: InputProps) {
  const inputId = id ?? React.useId()
  const descriptionId = `${inputId}-description`
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <input
        id={inputId}
        aria-invalid={Boolean(error)}
        aria-describedby={error || hint ? descriptionId : undefined}
        {...props}
        className={`w-full px-3 py-2 text-sm border rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A] transition-colors ${error ? 'border-red-400' : 'border-gray-300'} ${className}`}
      />
      {error && (
        <p id={descriptionId} className="mt-1 text-xs text-red-600">
          {error}
        </p>
      )}
      {hint && !error && (
        <p id={descriptionId} className="mt-1 text-xs text-gray-500">
          {hint}
        </p>
      )}
    </div>
  )
}

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
  options: { value: string; label: string }[]
}

export function Select({ label, error, options, className = '', id, ...props }: SelectProps) {
  const selectId = id ?? React.useId()
  const errorId = `${selectId}-error`
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={selectId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <select
        id={selectId}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? errorId : undefined}
        {...props}
        className={`w-full px-3 py-2 text-sm border rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A] transition-colors ${error ? 'border-red-400' : 'border-gray-300'} ${className}`}
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      {error && (
        <p id={errorId} className="mt-1 text-xs text-red-600">
          {error}
        </p>
      )}
    </div>
  )
}

interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
  hint?: string
}

export function Textarea({ label, error, hint, className = '', id, ...props }: TextareaProps) {
  const textareaId = id ?? React.useId()
  const descriptionId = `${textareaId}-description`
  return (
    <div className="w-full">
      {label && (
        <label htmlFor={textareaId} className="block text-sm font-medium text-gray-700 mb-1">
          {label}
        </label>
      )}
      <textarea
        id={textareaId}
        aria-invalid={Boolean(error)}
        aria-describedby={error || hint ? descriptionId : undefined}
        {...props}
        className={`w-full px-3 py-2 text-sm border rounded-lg bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A] transition-colors resize-vertical ${error ? 'border-red-400' : 'border-gray-300'} ${className}`}
      />
      {error && (
        <p id={descriptionId} className="mt-1 text-xs text-red-600">
          {error}
        </p>
      )}
      {hint && !error && (
        <p id={descriptionId} className="mt-1 text-xs text-gray-500">
          {hint}
        </p>
      )}
    </div>
  )
}

// ── Card ──────────────────────────────────────────────────────────────────────
export function Card({
  children,
  className = '',
  onClick,
}: {
  children: React.ReactNode
  className?: string
  onClick?: () => void
}) {
  return (
    <div
      onClick={onClick}
      className={`bg-white border border-gray-200 rounded-xl shadow-sm ${onClick ? 'cursor-pointer hover:shadow-md hover:border-[#1E3A8A]/30 transition-all' : ''} ${className}`}
    >
      {children}
    </div>
  )
}

// ── KPI Card ──────────────────────────────────────────────────────────────────
export function KPICard({
  title,
  value,
  subtitle,
  color = 'blue',
  icon,
}: {
  title: string
  value: string | number
  subtitle?: string
  color?: string
  icon?: React.ReactNode
}) {
  const colors: Record<string, string> = {
    blue: 'from-[#1E3A8A] to-[#2563EB]',
    red: 'from-red-600 to-red-500',
    green: 'from-emerald-600 to-emerald-500',
    amber: 'from-amber-500 to-amber-400',
    purple: 'from-purple-600 to-purple-500',
    gray: 'from-slate-600 to-slate-500',
  }
  return (
    <div className={`bg-gradient-to-br ${colors[color] || colors.blue} rounded-xl p-5 text-white shadow-md`}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm font-medium opacity-80">{title}</p>
          <p className="text-3xl font-bold mt-1">{value}</p>
          {subtitle && <p className="text-xs opacity-70 mt-1">{subtitle}</p>}
        </div>
        {icon && <div className="opacity-70 text-2xl">{icon}</div>}
      </div>
    </div>
  )
}

// ── Modal ─────────────────────────────────────────────────────────────────────
export function Modal({
  open,
  onClose,
  title,
  children,
  size = 'md',
}: {
  open: boolean
  onClose: () => void
  title: string
  children: React.ReactNode
  size?: 'sm' | 'md' | 'lg' | 'xl'
}) {
  const titleId = React.useId()
  const dialogRef = React.useRef<HTMLDivElement>(null)
  const onCloseRef = React.useRef(onClose)
  onCloseRef.current = onClose
  React.useEffect(() => {
    if (!open) return
    const previous = document.activeElement as HTMLElement | null
    const dialog = dialogRef.current
    const focusable = () =>
      Array.from(
        dialog?.querySelectorAll<HTMLElement>(
          'button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), a[href]',
        ) ?? [],
      )
    focusable()[0]?.focus()
    const keydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        onCloseRef.current()
        return
      }
      if (event.key !== 'Tab') return
      const items = focusable()
      if (!items.length) return
      const first = items[0]
      const last = items[items.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }
    document.addEventListener('keydown', keydown)
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', keydown)
      document.body.style.overflow = previousOverflow
      previous?.focus()
    }
  }, [open])
  if (!open) return null
  const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }
  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
    >
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onMouseDown={onClose} aria-hidden="true" />
      <div
        ref={dialogRef}
        className={`relative bg-white rounded-2xl shadow-2xl w-full ${sizes[size]} max-h-[calc(100vh-2rem)] overflow-y-auto`}
      >
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h2 id={titleId} className="text-base font-semibold text-gray-900">
            {title}
          </h2>
          <button
            onClick={onClose}
            aria-label="Tutup dialog"
            className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg hover:bg-gray-100 transition-colors"
          >
            <svg className="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div className="p-6">{children}</div>
      </div>
    </div>
  )
}

// ── Toast ─────────────────────────────────────────────────────────────────────
export function Toast({
  message,
  type = 'success',
  onClose,
}: {
  message: string
  type?: 'success' | 'error' | 'warning' | 'info'
  onClose: () => void
}) {
  const styles = {
    success: 'bg-emerald-600 text-white',
    error: 'bg-red-600 text-white',
    warning: 'bg-amber-500 text-white',
    info: 'bg-[#1E3A8A] text-white',
  }
  const Icons = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
  }
  const Icon = Icons[type]
  return (
    <div
      className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl ${styles[type]} animate-in slide-in-from-bottom-4`}
      role={type === 'error' ? 'alert' : 'status'}
      aria-live={type === 'error' ? 'assertive' : 'polite'}
    >
      <Icon className="w-5 h-5 shrink-0" aria-hidden="true" />
      <span className="text-sm font-medium">{message}</span>
      <button
        onClick={onClose}
        aria-label="Tutup notifikasi"
        className="ml-2 min-h-11 min-w-11 opacity-70 hover:opacity-100"
      >
        <XCircle className="w-4 h-4" aria-hidden="true" />
      </button>
    </div>
  )
}

// ── Table ─────────────────────────────────────────────────────────────────────
export function Table({ headers, children }: { headers: string[]; children: React.ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-gray-200 bg-gray-50">
            {headers.map((h) => (
              <th
                key={h}
                className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"
              >
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">{children}</tbody>
      </table>
    </div>
  )
}

export function TR({
  children,
  onClick,
  highlight,
}: {
  children: React.ReactNode
  onClick?: () => void
  highlight?: boolean
}) {
  return (
    <tr
      onClick={onClick}
      onKeyDown={
        onClick
          ? (event) => {
              if (event.key === 'Enter' || event.key === ' ') onClick()
            }
          : undefined
      }
      tabIndex={onClick ? 0 : undefined}
      className={`transition-colors ${onClick ? 'cursor-pointer hover:bg-blue-50/50' : ''} ${highlight ? 'bg-red-50/50' : ''}`}
    >
      {children}
    </tr>
  )
}

export function TD({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return <td className={`px-4 py-3 text-gray-700 ${className}`}>{children}</td>
}

// ── Avatar ────────────────────────────────────────────────────────────────────
export function Avatar({ initials, size = 'md' }: { initials: string; size?: 'sm' | 'md' | 'lg' }) {
  const sizes = { sm: 'w-7 h-7 text-xs', md: 'w-9 h-9 text-sm', lg: 'w-11 h-11 text-base' }
  return (
    <div
      className={`${sizes[size]} rounded-full bg-[#1E3A8A] text-white flex items-center justify-center font-semibold shrink-0`}
    >
      {initials}
    </div>
  )
}

// ── Activity Timeline ─────────────────────────────────────────────────────────
export function ActivityTimeline({ logs }: { logs: import('../types').ActivityLog[] }) {
  const roleColors: Record<string, string> = {
    requester: 'bg-blue-100 text-blue-700',
    supervisor: 'bg-purple-100 text-purple-700',
    it_lead: 'bg-indigo-100 text-indigo-700',
    pic: 'bg-teal-100 text-teal-700',
    qa: 'bg-cyan-100 text-cyan-700',
    manager: 'bg-amber-100 text-amber-700',
    executive: 'bg-slate-100 text-slate-700',
    admin: 'bg-gray-100 text-gray-700',
  }
  const TimelineIcon = ({ type }: { type: string }) => {
    const cls = 'w-3.5 h-3.5'
    switch (type) {
      case 'status_change':
        return <RefreshCw className={cls} aria-hidden="true" />
      case 'comment':
        return <MessageSquare className={cls} aria-hidden="true" />
      case 'assignment':
        return <User className={cls} aria-hidden="true" />
      case 'attachment':
        return <Paperclip className={cls} aria-hidden="true" />
      case 'escalation':
        return <Siren className={cls} aria-hidden="true" />
      default:
        return <Info className={cls} aria-hidden="true" />
    }
  }
  return (
    <div className="relative">
      <div className="absolute left-5 top-0 bottom-0 w-px bg-gray-200" />
      <div className="space-y-6">
        {logs.map((log) => (
          <div key={log.id} className="relative flex gap-4 pl-1">
            <div className="relative z-10 w-8 h-8 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center text-base shrink-0 shadow-sm text-gray-500">
              <TimelineIcon type={log.type} />
            </div>
            <div className="flex-1 min-w-0 pb-2">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-sm font-semibold text-gray-900">{log.actor}</span>
                <span
                  className={`text-xs px-1.5 py-0.5 rounded font-medium ${roleColors[log.actorRole] || 'bg-gray-100 text-gray-600'}`}
                >
                  {log.actorRole.toUpperCase()}
                </span>
                <span className="text-xs text-gray-400">
                  {new Date(log.timestamp).toLocaleString('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
              <p className="text-sm font-medium text-gray-700 mt-0.5">{log.action}</p>
              {log.comment && (
                <div className="mt-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                  <p className="text-sm text-gray-600">{log.comment}</p>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

// ── Page Header ───────────────────────────────────────────────────────────────
export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: string
  subtitle?: string
  actions?: React.ReactNode
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
      <div>
        <h1 className="text-xl font-bold text-gray-900">{title}</h1>
        {subtitle && <p className="text-sm text-gray-500 mt-0.5">{subtitle}</p>}
      </div>
      {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
    </div>
  )
}

// ── Filter Bar ────────────────────────────────────────────────────────────────
export function FilterBar({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3 flex-wrap mb-4 p-3 bg-gray-50 rounded-xl border border-gray-200">
      {children}
    </div>
  )
}

// ── Empty State ───────────────────────────────────────────────────────────────
export function EmptyState({ title, message, icon }: { title: string; message: string; icon?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <div className="text-gray-300 mb-4">{icon ?? <ClipboardList className="w-12 h-12" />}</div>
      <h3 className="text-base font-semibold text-gray-700">{title}</h3>
      <p className="text-sm text-gray-500 mt-1 max-w-xs">{message}</p>
    </div>
  )
}

// ── Over SLA Alert ────────────────────────────────────────────────────────────
export function OverSLABanner({ ticketId }: { ticketId: string }) {
  return (
    <div className="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
      <Siren className="w-5 h-5 text-red-500 shrink-0" aria-hidden="true" />
      <div>
        <p className="text-sm font-semibold text-red-700">Tiket Over SLA</p>
        <p className="text-xs text-red-600">
          {ticketId} telah melewati batas SLA. Eskalasi otomatis telah dikirim ke IT Lead dan Manager.
        </p>
      </div>
    </div>
  )
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
export function Tabs({ tabs, active, onChange }: { tabs: string[]; active: string; onChange: (t: string) => void }) {
  return (
    <div className="flex gap-1 p-1 bg-gray-100 rounded-xl w-fit mb-6">
      {tabs.map((tab) => (
        <button
          key={tab}
          onClick={() => onChange(tab)}
          className={`px-4 py-1.5 text-sm font-medium rounded-lg transition-all ${active === tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}
        >
          {tab}
        </button>
      ))}
    </div>
  )
}

// ── Section Card ──────────────────────────────────────────────────────────────
export function SectionCard({
  title,
  children,
  className = '',
  actions,
}: {
  title?: string
  children: React.ReactNode
  className?: string
  actions?: React.ReactNode
}) {
  return (
    <div className={`bg-white border border-gray-200 rounded-xl shadow-sm ${className}`}>
      {title && (
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 className="text-sm font-semibold text-gray-800">{title}</h3>
          {actions && <div>{actions}</div>}
        </div>
      )}
      <div className="p-5">{children}</div>
    </div>
  )
}
