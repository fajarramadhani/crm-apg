import type { Role } from './types'

export const ROLE_LABELS: Record<Role, string> = {
  requester: 'Requester',
  supervisor: 'Supervisor',
  it_lead: 'IT Lead',
  pic: 'PIC / IT Member',
  qa: 'Quality Assurance',
  manager: 'IT Manager',
  executive: 'Executive',
  superadmin: 'Super Admin',
  supervisor_it: 'Supervisor IT',
  pic_it_support: 'PIC IT Support',
  pic_it_develop: 'PIC IT Develop',
}

export const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  pending_validation: 'Menunggu Validasi',
  validated: 'Tervalidasi',
  rejected: 'Ditolak',
  revision: 'Revisi',
  triage: 'Triage',
  assigned: 'Ditugaskan',
  analysis: 'Analysis',
  solution_planning: 'Solution Planning',
  plan_review: 'Plan Review',
  ready_for_development: 'Ready for Development',
  development_in_progress: 'Development In Progress',
  ready_for_qa: 'Ready for QA',
  qa_assignment: 'Penugasan QA',
  qa_in_progress: 'QA In Progress',
  qa_failed: 'QA Failed',
  qa_retest: 'QA Retest',
  ready_for_uat: 'Ready for UAT',
  uat_assignment: 'Penugasan UAT',
  uat_in_progress: 'UAT In Progress',
  uat_failed: 'UAT Failed',
  uat_retest: 'UAT Retest',
  uat_approved: 'UAT Approved',
  approval_pending: 'Menunggu Persetujuan Rilis',
  approval_revision: 'Persetujuan Perlu Revisi',
  release_preparation: 'Persiapan Rilis',
  release_ready: 'Siap Dirilis',
  in_progress: 'Sedang Dikerjakan',
  internal_testing: 'Internal Testing',
  uat: 'UAT',
  pending_approval: 'Menunggu Approval',
  approved: 'Disetujui',
  deploying: 'Deploying',
  done: 'Selesai',
  closed: 'Closed',
  over_sla: 'Over SLA',
}

export const PUBLIC_STATUS_LABELS: Record<string, string> = {
  draft: 'Diajukan',
  pending_validation: 'Diajukan',
  submitted: 'Diajukan',
  under_analysis: 'Sedang Dianalisis',
  validated: 'Sedang Dianalisis',
  triage: 'Sedang Dianalisis',
  assigned: 'Sedang Dianalisis',
  analysis: 'Sedang Dianalisis',
  solution_planning: 'Sedang Dianalisis',
  plan_review: 'Sedang Dianalisis',
  ready_for_development: 'Sedang Ditangani',
  development_in_progress: 'Sedang Ditangani',
  internal_testing: 'Sedang Ditangani',
  need_revision: 'Memerlukan Informasi',
  need_info: 'Memerlukan Informasi',
  waiting_external: 'Menunggu Pihak Eksternal',
  on_hold: 'Ditunda Sementara',
  revision: 'Memerlukan Informasi',
  ready_for_qa: 'Dalam Pemeriksaan Akhir',
  qa_assignment: 'Dalam Pemeriksaan Akhir',
  qa_in_progress: 'Dalam Pemeriksaan Akhir',
  qa_failed: 'Dalam Pemeriksaan Akhir',
  qa_retest: 'Dalam Pemeriksaan Akhir',
  ready_for_uat: 'Dalam Pemeriksaan Akhir',
  uat_assignment: 'Dalam Pemeriksaan Akhir',
  uat_in_progress: 'Dalam Pemeriksaan Akhir',
  uat_failed: 'Dalam Pemeriksaan Akhir',
  uat_retest: 'Dalam Pemeriksaan Akhir',
  uat_approved: 'Dalam Pemeriksaan Akhir',
  approval_pending: 'Dalam Pemeriksaan Akhir',
  approval_revision: 'Dalam Pemeriksaan Akhir',
  release_preparation: 'Dalam Pemeriksaan Akhir',
  release_ready: 'Dalam Pemeriksaan Akhir',
  deployment_scheduled: 'Dalam Pemeriksaan Akhir',
  deployment_in_progress: 'Dalam Pemeriksaan Akhir',
  deployed: 'Dalam Pemeriksaan Akhir',
  monitoring: 'Dalam Pemeriksaan Akhir',
  awaiting_requester_confirmation: 'Dalam Pemeriksaan Akhir',
  done: 'Selesai',
  closed: 'Selesai',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
  reopened: 'Dibuka Kembali',
}

export function getPublicStatusLabel(status: string): string {
  return PUBLIC_STATUS_LABELS[status] || STATUS_LABELS[status] || 'Diajukan'
}

export const PRIORITY_LABELS: Record<string, string> = {
  critical: 'Critical',
  high: 'High',
  medium: 'Medium',
  low: 'Low',
}

export function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    pending_validation: 'bg-yellow-100 text-yellow-700',
    validated: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700',
    revision: 'bg-orange-100 text-orange-700',
    triage: 'bg-purple-100 text-purple-700',
    assigned: 'bg-blue-100 text-blue-700',
    analysis: 'bg-indigo-100 text-indigo-700',
    solution_planning: 'bg-purple-100 text-purple-700',
    plan_review: 'bg-amber-100 text-amber-700',
    ready_for_development: 'bg-emerald-100 text-emerald-700',
    development_in_progress: 'bg-blue-100 text-blue-700',
    ready_for_qa: 'bg-teal-100 text-teal-700',
    qa_assignment: 'bg-indigo-100 text-indigo-700',
    qa_in_progress: 'bg-yellow-100 text-yellow-700',
    qa_failed: 'bg-red-100 text-red-700',
    qa_retest: 'bg-orange-100 text-orange-700',
    ready_for_uat: 'bg-emerald-100 text-emerald-700',
    uat_assignment: 'bg-indigo-100 text-indigo-700',
    uat_in_progress: 'bg-yellow-100 text-yellow-700',
    uat_failed: 'bg-red-100 text-red-700',
    uat_retest: 'bg-orange-100 text-orange-700',
    uat_approved: 'bg-green-100 text-green-700',
    approval_pending: 'bg-amber-100 text-amber-700',
    approval_revision: 'bg-orange-100 text-orange-700',
    release_preparation: 'bg-blue-100 text-blue-700',
    release_ready: 'bg-green-100 text-green-700',
    in_progress: 'bg-blue-100 text-blue-700',
    internal_testing: 'bg-indigo-100 text-indigo-700',
    uat: 'bg-cyan-100 text-cyan-700',
    pending_approval: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    deploying: 'bg-teal-100 text-teal-700',
    done: 'bg-green-100 text-green-700',
    closed: 'bg-gray-200 text-gray-600',
    over_sla: 'bg-red-100 text-red-700',
  }

  return colors[status] || 'bg-gray-100 text-gray-600'
}

export function getPriorityColor(priority: string): string {
  const colors: Record<string, string> = {
    critical: 'bg-red-100 text-red-700 border border-red-200',
    high: 'bg-orange-100 text-orange-700 border border-orange-200',
    medium: 'bg-yellow-100 text-yellow-700 border border-yellow-200',
    low: 'bg-green-100 text-green-700 border border-green-200',
  }

  return colors[priority] || 'bg-gray-100 text-gray-600'
}

export function getSlaLabel(remaining: number, overSla: boolean): string {
  if (overSla || remaining < 0) return `Terlambat ${Math.abs(remaining)} jam`
  if (remaining < 24) return `${remaining} jam lagi`

  const days = Math.floor(remaining / 24)
  const hours = remaining % 24
  return hours > 0 ? `${days}h ${hours}j lagi` : `${days} hari lagi`
}
