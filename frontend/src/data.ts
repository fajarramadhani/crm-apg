import type { User, Ticket, ActivityLog, Notification, Role } from './types'

export const USERS: User[] = [
  {
    id: 'u1',
    name: 'Rina Marlina',
    email: 'rina.marlina@apg.co.id',
    role: 'requester',
    division: 'Operasional Polis',
    avatar: 'RM',
  },
  {
    id: 'u2',
    name: 'Budi Santoso',
    email: 'budi.santoso@apg.co.id',
    role: 'supervisor',
    division: 'Operasional Polis',
    avatar: 'BS',
  },
  { id: 'u3', name: 'Hendra Wijaya', email: 'hendra.wijaya@apg.co.id', role: 'it_lead', division: 'IT', avatar: 'HW' },
  {
    id: 'u4',
    name: 'Dian Kusuma',
    email: 'dian.kusuma@apg.co.id',
    role: 'pic',
    division: 'IT Development',
    avatar: 'DK',
  },
  { id: 'u5', name: 'Sari Pratiwi', email: 'sari.pratiwi@apg.co.id', role: 'qa', division: 'IT QA', avatar: 'SP' },
  { id: 'u6', name: 'Ahmad Fauzi', email: 'ahmad.fauzi@apg.co.id', role: 'manager', division: 'IT', avatar: 'AF' },
  {
    id: 'u7',
    name: 'Direktur Teknologi',
    email: 'cto@apg.co.id',
    role: 'executive',
    division: 'Direksi',
    avatar: 'DT',
  },
  { id: 'u8', name: 'Admin Sistem', email: 'admin@apg.co.id', role: 'admin', division: 'IT', avatar: 'AS' },
  {
    id: 'u9',
    name: 'Reza Firmansyah',
    email: 'reza.firmansyah@apg.co.id',
    role: 'requester',
    division: 'Keuangan',
    avatar: 'RF',
  },
  {
    id: 'u10',
    name: 'Linda Susanti',
    email: 'linda.susanti@apg.co.id',
    role: 'pic',
    division: 'IT Development',
    avatar: 'LS',
  },
]

export const TICKETS: Ticket[] = [
  {
    id: 'IT-2026-000001',
    title: 'Gagal Generate PDF Polis Asuransi',
    description:
      'Sistem tidak dapat meng-generate dokumen PDF polis asuransi jiwa untuk nasabah baru. Error muncul saat klik tombol "Cetak Polis" pada modul Manajemen Polis. Pesan error: "PDF generation failed: template not found". Berdampak pada proses penerbitan polis yang tidak dapat dilanjutkan.',
    category: 'incident',
    priority: 'critical',
    status: 'uat',
    application: 'Sistem Manajemen Polis (SMP)',
    division: 'Operasional Polis',
    requester: 'Rina Marlina',
    requesterId: 'u1',
    supervisor: 'Budi Santoso',
    pic: 'Dian Kusuma',
    picId: 'u4',
    createdAt: '2026-07-08T08:30:00Z',
    updatedAt: '2026-07-13T09:15:00Z',
    slaDeadline: '2026-07-09T08:30:00Z',
    slaHours: 24,
    slaRemaining: -96,
    overSla: true,
    tags: ['PDF', 'Polis', 'Critical', 'Production'],
    attachments: ['screenshot_error.png', 'log_server_2026-07-08.txt'],
  },
  {
    id: 'IT-2026-000002',
    title: 'Request Tambah Fitur Export Excel Laporan Klaim',
    description:
      'Perlu ditambahkan fitur export data laporan klaim ke format Excel (.xlsx) pada modul Laporan Klaim. Saat ini hanya tersedia export PDF yang tidak memudahkan analisis data lanjutan.',
    category: 'request',
    priority: 'medium',
    status: 'in_progress',
    application: 'Sistem Manajemen Klaim (SMK)',
    division: 'Keuangan',
    requester: 'Reza Firmansyah',
    requesterId: 'u9',
    supervisor: 'Budi Santoso',
    pic: 'Linda Susanti',
    picId: 'u10',
    createdAt: '2026-07-09T10:00:00Z',
    updatedAt: '2026-07-13T08:00:00Z',
    slaDeadline: '2026-07-16T10:00:00Z',
    slaHours: 168,
    slaRemaining: 71,
    overSla: false,
    tags: ['Export', 'Excel', 'Laporan'],
    attachments: [],
  },
  {
    id: 'IT-2026-000003',
    title: 'Dashboard Premi Tidak Menampilkan Data Bulan Berjalan',
    description:
      'Dashboard monitoring premi pada halaman utama tidak menampilkan data bulan Juli 2026. Data terakhir yang muncul adalah bulan Juni 2026.',
    category: 'incident',
    priority: 'high',
    status: 'internal_testing',
    application: 'Dashboard Monitoring (DM)',
    division: 'Aktuaria',
    requester: 'Rina Marlina',
    requesterId: 'u1',
    supervisor: 'Budi Santoso',
    pic: 'Dian Kusuma',
    picId: 'u4',
    createdAt: '2026-07-10T09:00:00Z',
    updatedAt: '2026-07-12T16:00:00Z',
    slaDeadline: '2026-07-12T09:00:00Z',
    slaHours: 48,
    slaRemaining: -24,
    overSla: true,
    tags: ['Dashboard', 'Premi', 'Data'],
    attachments: ['screenshot_dashboard.png'],
  },
  {
    id: 'IT-2026-000004',
    title: 'Performa Login Lambat - Lebih dari 30 Detik',
    description:
      'Proses login ke aplikasi SMP membutuhkan waktu lebih dari 30 detik sejak kemarin sore. User melaporkan timeout setelah menunggu lebih dari 1 menit.',
    category: 'incident',
    priority: 'high',
    status: 'assigned',
    application: 'Sistem Manajemen Polis (SMP)',
    division: 'IT',
    requester: 'Rina Marlina',
    requesterId: 'u1',
    supervisor: 'Budi Santoso',
    pic: 'Dian Kusuma',
    picId: 'u4',
    createdAt: '2026-07-11T07:00:00Z',
    updatedAt: '2026-07-13T07:30:00Z',
    slaDeadline: '2026-07-13T07:00:00Z',
    slaHours: 48,
    slaRemaining: 2,
    overSla: false,
    tags: ['Performance', 'Login', 'Urgent'],
    attachments: [],
  },
  {
    id: 'IT-2026-000005',
    title: 'Penambahan Role Baru: Auditor Internal',
    description:
      'Diperlukan penambahan role baru "Auditor Internal" dengan akses read-only ke seluruh modul laporan. Kebutuhan dari tim Audit Internal untuk keperluan audit Q3 2026.',
    category: 'change',
    priority: 'low',
    status: 'pending_validation',
    application: 'Sistem IAM (Identity Access Management)',
    division: 'Audit Internal',
    requester: 'Reza Firmansyah',
    requesterId: 'u9',
    supervisor: 'Budi Santoso',
    pic: '',
    picId: '',
    createdAt: '2026-07-13T08:00:00Z',
    updatedAt: '2026-07-13T08:00:00Z',
    slaDeadline: '2026-07-20T08:00:00Z',
    slaHours: 168,
    slaRemaining: 167,
    overSla: false,
    tags: ['Role', 'IAM', 'Audit'],
    attachments: ['surat_permohonan_audit.pdf'],
  },
  {
    id: 'IT-2026-000006',
    title: 'Notifikasi Email Perpanjangan Polis Tidak Terkirim',
    description:
      'Sistem notifikasi email untuk reminder perpanjangan polis tidak terkirim sejak tanggal 10 Juli 2026. Lebih dari 500 nasabah tidak menerima notifikasi.',
    category: 'incident',
    priority: 'critical',
    status: 'over_sla',
    application: 'Notification Service (NS)',
    division: 'Operasional Polis',
    requester: 'Rina Marlina',
    requesterId: 'u1',
    supervisor: 'Budi Santoso',
    pic: 'Linda Susanti',
    picId: 'u10',
    createdAt: '2026-07-10T14:00:00Z',
    updatedAt: '2026-07-13T06:00:00Z',
    slaDeadline: '2026-07-11T14:00:00Z',
    slaHours: 24,
    slaRemaining: -43,
    overSla: true,
    tags: ['Email', 'Notifikasi', 'Critical'],
    attachments: [],
  },
  {
    id: 'IT-2026-000007',
    title: 'Update Template Dokumen SURAT KETERANGAN KECELAKAAN',
    description:
      'Permohonan update template Surat Keterangan Kecelakaan sesuai format terbaru dari Otoritas Jasa Keuangan (OJK) per Juli 2026.',
    category: 'change',
    priority: 'medium',
    status: 'pending_approval',
    application: 'Document Management System (DMS)',
    division: 'Legal & Compliance',
    requester: 'Reza Firmansyah',
    requesterId: 'u9',
    supervisor: 'Budi Santoso',
    pic: 'Dian Kusuma',
    picId: 'u4',
    createdAt: '2026-07-07T11:00:00Z',
    updatedAt: '2026-07-13T10:00:00Z',
    slaDeadline: '2026-07-14T11:00:00Z',
    slaHours: 168,
    slaRemaining: 25,
    overSla: false,
    tags: ['Template', 'OJK', 'Compliance'],
    attachments: ['template_ojk_2026.docx'],
  },
  {
    id: 'IT-2026-000008',
    title: 'Integrasi API Pembayaran Premi via Virtual Account',
    description:
      'Pengembangan fitur baru integrasi API bank untuk pembayaran premi melalui Virtual Account. Mendukung Bank BCA, BRI, dan Mandiri.',
    category: 'request',
    priority: 'high',
    status: 'closed',
    application: 'Payment Gateway (PG)',
    division: 'Keuangan',
    requester: 'Reza Firmansyah',
    requesterId: 'u9',
    supervisor: 'Budi Santoso',
    pic: 'Linda Susanti',
    picId: 'u10',
    createdAt: '2026-06-15T09:00:00Z',
    updatedAt: '2026-07-10T16:00:00Z',
    slaDeadline: '2026-07-01T09:00:00Z',
    slaHours: 384,
    slaRemaining: 0,
    overSla: false,
    tags: ['API', 'Payment', 'VA', 'Bank'],
    attachments: [],
  },
]

export const ACTIVITY_LOGS: ActivityLog[] = [
  {
    id: 'al1',
    ticketId: 'IT-2026-000001',
    actor: 'Rina Marlina',
    actorRole: 'requester',
    action: 'Tiket dibuat',
    comment:
      'Melaporkan kegagalan generate PDF polis. Sangat urgent karena ada 50+ nasabah menunggu penerbitan polis hari ini.',
    timestamp: '2026-07-08T08:30:00Z',
    type: 'status_change',
  },
  {
    id: 'al2',
    ticketId: 'IT-2026-000001',
    actor: 'Budi Santoso',
    actorRole: 'supervisor',
    action: 'Tiket divalidasi',
    comment: 'Tiket valid dan memerlukan penanganan segera. Diteruskan ke IT Lead untuk triage.',
    timestamp: '2026-07-08T09:15:00Z',
    type: 'status_change',
  },
  {
    id: 'al3',
    ticketId: 'IT-2026-000001',
    actor: 'Hendra Wijaya',
    actorRole: 'it_lead',
    action: 'Priority ditetapkan: Critical | SLA: 24 jam',
    comment: 'Dampak signifikan pada operasional. Assign ke Dian Kusuma sebagai PIC.',
    timestamp: '2026-07-08T09:45:00Z',
    type: 'assignment',
  },
  {
    id: 'al4',
    ticketId: 'IT-2026-000001',
    actor: 'Dian Kusuma',
    actorRole: 'pic',
    action: 'Tiket mulai dikerjakan',
    comment:
      'Investigasi awal: menemukan bahwa file template PDF hilang dari server setelah deployment terkahir. Sedang proses restore.',
    timestamp: '2026-07-08T10:30:00Z',
    type: 'comment',
  },
  {
    id: 'al5',
    ticketId: 'IT-2026-000001',
    actor: 'Dian Kusuma',
    actorRole: 'pic',
    action: 'Update progress',
    comment:
      'Root cause confirmed: template file tidak ter-include dalam deployment package v2.3.1. Fix sedang dikerjakan.',
    timestamp: '2026-07-08T13:00:00Z',
    type: 'comment',
  },
  {
    id: 'al6',
    ticketId: 'IT-2026-000001',
    actor: 'Sistem',
    actorRole: 'admin',
    action: 'SLA Terlampaui - Eskalasi Otomatis',
    comment: 'Tiket telah melewati batas SLA 24 jam. Notifikasi dikirim ke IT Lead dan Manager.',
    timestamp: '2026-07-09T08:30:00Z',
    type: 'escalation',
  },
  {
    id: 'al7',
    ticketId: 'IT-2026-000001',
    actor: 'Dian Kusuma',
    actorRole: 'pic',
    action: 'Fix selesai - Masuk Internal Testing',
    comment:
      'Fix telah selesai. Template PDF berhasil di-restore dan deploy ke environment testing. Siap untuk internal testing oleh QA.',
    timestamp: '2026-07-10T09:00:00Z',
    type: 'status_change',
  },
  {
    id: 'al8',
    ticketId: 'IT-2026-000001',
    actor: 'Sari Pratiwi',
    actorRole: 'qa',
    action: 'Internal Testing selesai - PASSED',
    comment:
      'Testing berhasil: Generate PDF polis berhasil untuk semua jenis produk. Performance: rata-rata 3.2 detik per dokumen. Siap untuk UAT.',
    timestamp: '2026-07-11T14:00:00Z',
    type: 'status_change',
  },
  {
    id: 'al9',
    ticketId: 'IT-2026-000001',
    actor: 'Rina Marlina',
    actorRole: 'requester',
    action: 'UAT dimulai',
    comment: 'Mulai melakukan UAT sesuai test case yang diberikan.',
    timestamp: '2026-07-12T09:00:00Z',
    type: 'status_change',
  },
]

export const NOTIFICATIONS: Notification[] = [
  {
    id: 'n1',
    userId: 'u1',
    title: 'Tiket IT-2026-000001 siap UAT',
    message: 'Tiket "Gagal Generate PDF Polis" telah lulus internal testing dan siap untuk dilakukan UAT oleh Anda.',
    type: 'info',
    read: false,
    ticketId: 'IT-2026-000001',
    createdAt: '2026-07-11T14:05:00Z',
  },
  {
    id: 'n2',
    userId: 'u2',
    title: 'Tiket baru menunggu validasi',
    message: 'Tiket IT-2026-000005 "Penambahan Role Baru: Auditor Internal" menunggu validasi Anda.',
    type: 'info',
    read: false,
    ticketId: 'IT-2026-000005',
    createdAt: '2026-07-13T08:05:00Z',
  },
  {
    id: 'n3',
    userId: 'u3',
    title: 'ESKALASI: Tiket Over SLA',
    message:
      'Tiket IT-2026-000006 "Notifikasi Email Perpanjangan Polis" telah melewati SLA 43 jam. Perlu tindakan segera.',
    type: 'error',
    read: false,
    ticketId: 'IT-2026-000006',
    createdAt: '2026-07-13T06:05:00Z',
  },
  {
    id: 'n4',
    userId: 'u4',
    title: 'Tiket baru di-assign ke Anda',
    message: 'Anda ditunjuk sebagai PIC untuk tiket IT-2026-000004 "Performa Login Lambat".',
    type: 'info',
    read: true,
    ticketId: 'IT-2026-000004',
    createdAt: '2026-07-11T10:00:00Z',
  },
  {
    id: 'n5',
    userId: 'u6',
    title: 'Tiket menunggu Manager Approval',
    message: 'Tiket IT-2026-000007 "Update Template SKK" menunggu approval Anda sebelum dapat di-deploy.',
    type: 'warning',
    read: false,
    ticketId: 'IT-2026-000007',
    createdAt: '2026-07-13T10:05:00Z',
  },
]

export const APPLICATIONS = [
  'Sistem Manajemen Polis (SMP)',
  'Sistem Manajemen Klaim (SMK)',
  'Dashboard Monitoring (DM)',
  'Notification Service (NS)',
  'Document Management System (DMS)',
  'Payment Gateway (PG)',
  'Sistem IAM (Identity Access Management)',
  'Portal Nasabah (PN)',
  'Sistem Aktuaria (SA)',
]

export const DIVISIONS = [
  'Operasional Polis',
  'Keuangan',
  'Aktuaria',
  'Legal & Compliance',
  'Audit Internal',
  'IT',
  'IT Development',
  'IT QA',
  'SDM & Umum',
  'Direksi',
]

export const SLA_RULES = [
  { priority: 'critical', hours: 24, label: 'Critical (24 jam)' },
  { priority: 'high', hours: 48, label: 'High (48 jam)' },
  { priority: 'medium', hours: 168, label: 'Medium (7 hari)' },
  { priority: 'low', hours: 336, label: 'Low (14 hari)' },
]

export const ROLE_LABELS: Record<Role, string> = {
  requester: 'Requester',
  supervisor: 'Supervisor',
  it_lead: 'IT Lead',
  pic: 'PIC / IT Member',
  qa: 'Quality Assurance',
  manager: 'IT Manager',
  executive: 'Executive',
  admin: 'Admin',
}

export const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  pending_validation: 'Menunggu Validasi',
  validated: 'Tervalidasi',
  rejected: 'Ditolak',
  revision: 'Revisi',
  triage: 'Triage',
  assigned: 'Ditugaskan',
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

export const PRIORITY_LABELS: Record<string, string> = {
  critical: 'Critical',
  high: 'High',
  medium: 'Medium',
  low: 'Low',
}

export function getStatusColor(status: string): string {
  const map: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    pending_validation: 'bg-yellow-100 text-yellow-700',
    validated: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700',
    revision: 'bg-orange-100 text-orange-700',
    triage: 'bg-purple-100 text-purple-700',
    assigned: 'bg-blue-100 text-blue-700',
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
  return map[status] || 'bg-gray-100 text-gray-600'
}

export function getPriorityColor(priority: string): string {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700 border border-red-200',
    high: 'bg-orange-100 text-orange-700 border border-orange-200',
    medium: 'bg-yellow-100 text-yellow-700 border border-yellow-200',
    low: 'bg-green-100 text-green-700 border border-green-200',
  }
  return map[priority] || 'bg-gray-100 text-gray-600'
}

export function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

export function formatDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function getSlaLabel(remaining: number, overSla: boolean): string {
  if (overSla || remaining < 0) {
    const hours = Math.abs(remaining)
    return `Terlambat ${hours} jam`
  }
  if (remaining < 4) return `${remaining} jam lagi`
  if (remaining < 24) return `${remaining} jam lagi`
  const days = Math.floor(remaining / 24)
  const hours = remaining % 24
  return hours > 0 ? `${days}h ${hours}j lagi` : `${days} hari lagi`
}

export const KPI_DATA = {
  totalTickets: 142,
  openTickets: 28,
  overSlaTickets: 5,
  closedThisMonth: 67,
  avgResolutionHours: 31.4,
  slaComplianceRate: 82.4,
  criticalOpen: 3,
  pendingValidation: 4,
}

export const CHART_DATA = {
  weeklyTickets: [
    { day: 'Sen', masuk: 8, selesai: 6 },
    { day: 'Sel', masuk: 12, selesai: 9 },
    { day: 'Rab', masuk: 7, selesai: 11 },
    { day: 'Kam', masuk: 15, selesai: 8 },
    { day: 'Jum', masuk: 10, selesai: 13 },
    { day: 'Sab', masuk: 3, selesai: 4 },
    { day: 'Min', masuk: 2, selesai: 2 },
  ],
  byStatus: [
    { name: 'In Progress', value: 12, color: '#3B82F6' },
    { name: 'UAT', value: 5, color: '#06B6D4' },
    { name: 'Internal Testing', value: 4, color: '#6366F1' },
    { name: 'Menunggu Validasi', value: 4, color: '#F59E0B' },
    { name: 'Over SLA', value: 3, color: '#EF4444' },
  ],
  byPriority: [
    { name: 'Critical', value: 8, color: '#EF4444' },
    { name: 'High', value: 18, color: '#F97316' },
    { name: 'Medium', value: 35, color: '#F59E0B' },
    { name: 'Low', value: 6, color: '#22C55E' },
  ],
  monthlyTrend: [
    { month: 'Feb', masuk: 45, selesai: 42, overSla: 3 },
    { month: 'Mar', masuk: 52, selesai: 48, overSla: 5 },
    { month: 'Apr', masuk: 38, selesai: 41, overSla: 2 },
    { month: 'Mei', masuk: 61, selesai: 55, overSla: 8 },
    { month: 'Jun', masuk: 74, selesai: 68, overSla: 6 },
    { month: 'Jul', masuk: 67, selesai: 53, overSla: 5 },
  ],
  byApp: [
    { app: 'SMP', tickets: 45 },
    { app: 'SMK', tickets: 28 },
    { app: 'DM', tickets: 19 },
    { app: 'NS', tickets: 15 },
    { app: 'DMS', tickets: 12 },
    { app: 'PG', tickets: 10 },
    { app: 'IAM', tickets: 8 },
    { app: 'Lainnya', tickets: 5 },
  ],
  divisionPerf: [
    { division: 'IT Dev', slaRate: 88, tickets: 67 },
    { division: 'IT Infra', slaRate: 76, tickets: 34 },
    { division: 'IT QA', slaRate: 95, tickets: 28 },
    { division: 'IT Support', slaRate: 71, tickets: 13 },
  ],
}
