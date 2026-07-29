<?php

namespace Database\Seeders;

use App\Models\WorkflowApprovalConfig;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default CRM IT workflow (draft, not active).
 *
 * This seeder is idempotent via updateOrCreate.
 * The workflow remains is_active=false and config_status='draft'.
 * Admin must validate, publish, then activate through Admin Workflow Management.
 *
 * To activate manually for testing:
 *   php artisan crm:workflow-check
 *   Then use Admin UI or API to publish & activate.
 */
class DefaultWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // ----------------------------------------------------------------
            // 1. Workflow Definition
            // ----------------------------------------------------------------
            $workflow = WorkflowDefinition::query()->updateOrCreate(
                [
                    'code' => 'crm_default',
                    'version' => 1,
                ],
                [
                    'name' => 'CRM Default IT Workflow',
                    'description' => 'Workflow dinamis minimum untuk penanganan tiket IT: 6 tahap utama + 7 kondisi khusus.',
                    'is_active' => false,
                    'config_status' => 'draft',
                    'created_by' => null,
                    'metadata' => [
                        'created_by' => 'system_seeder',
                        'default_for' => ['incident', 'request', 'change', 'problem'],
                    ],
                ]
            );

            // ----------------------------------------------------------------
            // 2. Stages
            // ----------------------------------------------------------------
            $stagesData = [
                // Main flow
                ['stage_key' => 'submitted',        'name' => 'Diajukan',              'description' => 'Tiket diajukan Pemohon.',                         'order' => 1,  'stage_type' => 'normal',   'is_initial' => true,  'is_terminal' => false],
                ['stage_key' => 'under_analysis',   'name' => 'Sedang Dianalisis',     'description' => 'Supervisor IT menganalisis dan menentukan PIC.',   'order' => 2,  'stage_type' => 'normal',   'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'assigned',         'name' => 'Telah Ditugaskan',      'description' => 'PIC Utama telah ditunjuk, siap ditangani.',        'order' => 3,  'stage_type' => 'normal',   'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'in_progress',      'name' => 'Sedang Dikerjakan',     'description' => 'PIC mengerjakan penanganan.',                      'order' => 4,  'stage_type' => 'normal',   'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'pending_approval', 'name' => 'Pemeriksaan Akhir',     'description' => 'Menunggu persetujuan Supervisor IT.',              'order' => 5,  'stage_type' => 'approval', 'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'done',             'name' => 'Selesai',               'description' => 'Tiket diselesaikan.',                              'order' => 6,  'stage_type' => 'normal',   'is_initial' => false, 'is_terminal' => true],
                // Special stages
                ['stage_key' => 'need_info',        'name' => 'Memerlukan Informasi',  'description' => 'Menunggu informasi tambahan dari Pemohon.',         'order' => 10, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'waiting_external', 'name' => 'Menunggu Pihak Ketiga', 'description' => 'Menunggu vendor atau pihak eksternal.',             'order' => 11, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'need_revision',    'name' => 'Perlu Revisi',          'description' => 'PIC melakukan revisi atas permintaan Supervisor.',  'order' => 12, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'on_hold',          'name' => 'Ditangguhkan',          'description' => 'Tiket ditangguhkan sementara.',                    'order' => 13, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => false],
                ['stage_key' => 'rejected',         'name' => 'Ditolak',               'description' => 'Tiket ditolak oleh Supervisor IT.',                'order' => 14, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => true],
                ['stage_key' => 'cancelled',        'name' => 'Dibatalkan',            'description' => 'Tiket dibatalkan.',                                'order' => 15, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => true],
                ['stage_key' => 'reopened',         'name' => 'Dibuka Kembali',        'description' => 'Tiket selesai dibuka kembali.',                    'order' => 16, 'stage_type' => 'special',  'is_initial' => false, 'is_terminal' => false],
            ];

            $stageModels = [];
            foreach ($stagesData as $stageData) {
                $stageModels[$stageData['stage_key']] = WorkflowStage::query()->updateOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'stage_key' => $stageData['stage_key'],
                    ],
                    array_merge($stageData, ['workflow_id' => $workflow->id])
                );
            }

            // ----------------------------------------------------------------
            // 3. Transitions
            // Format: [from, to, action_key, name, requires_notes, [permissions], [notifications]]
            // permissions: [['role_key' => ...], ...]
            // ----------------------------------------------------------------
            $transitionsData = [
                // Intake: submitted → under_analysis
                ['from' => 'submitted',        'to' => 'under_analysis',   'action_key' => 'start_analysis',    'name' => 'Mulai Analisis',              'requires_notes' => false,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.analysis_started']]],

                // Analysis → Assigned
                ['from' => 'under_analysis',   'to' => 'assigned',         'action_key' => 'assign',            'name' => 'Tugaskan PIC',                'requires_notes' => false,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'primary_pic', 'channel' => 'database', 'template_code' => 'ticket.assigned']]],

                // Start Work: assigned → in_progress
                ['from' => 'assigned',         'to' => 'in_progress',      'action_key' => 'start_work',        'name' => 'Mulai Pengerjaan',            'requires_notes' => false,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'supervisor_it', 'channel' => 'database', 'template_code' => 'ticket.work_started']]],

                // Request Info: in_progress → need_info
                ['from' => 'in_progress',      'to' => 'need_info',        'action_key' => 'request_info',      'name' => 'Minta Informasi',             'requires_notes' => true,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.info_requested']]],

                // Requester Reply: need_info → in_progress
                ['from' => 'need_info',        'to' => 'in_progress',      'action_key' => 'requester_reply',   'name' => 'Balas Informasi',             'requires_notes' => false,
                    'permissions' => [['role_key' => 'requester']],
                    'notifications' => [['recipient_type' => 'primary_pic', 'channel' => 'database', 'template_code' => 'ticket.requester_replied']]],

                // Wait External: in_progress → waiting_external
                ['from' => 'in_progress',      'to' => 'waiting_external', 'action_key' => 'wait_external',     'name' => 'Tunggu Pihak Ketiga',         'requires_notes' => true,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'supervisor_it', 'channel' => 'database', 'template_code' => 'ticket.waiting_external']]],

                // Resume from External: waiting_external → in_progress
                ['from' => 'waiting_external', 'to' => 'in_progress',      'action_key' => 'resume_work',       'name' => 'Lanjutkan Pengerjaan',        'requires_notes' => false,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']]],

                // On Hold: under_analysis → on_hold
                ['from' => 'under_analysis',   'to' => 'on_hold',          'action_key' => 'put_on_hold',       'name' => 'Tangguhkan Tiket',            'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // On Hold: assigned → on_hold
                ['from' => 'assigned',         'to' => 'on_hold',          'action_key' => 'put_on_hold',       'name' => 'Tangguhkan Tiket',            'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // On Hold: in_progress → on_hold
                ['from' => 'in_progress',      'to' => 'on_hold',          'action_key' => 'put_on_hold',       'name' => 'Tangguhkan Tiket',            'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // On Hold: pending_approval → on_hold
                ['from' => 'pending_approval', 'to' => 'on_hold',          'action_key' => 'put_on_hold',       'name' => 'Tangguhkan Tiket',            'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Resume from Hold: on_hold → in_progress (engine resolves previous_stage)
                ['from' => 'on_hold',          'to' => 'in_progress',      'action_key' => 'resume',            'name' => 'Lanjutkan dari Penangguhan',  'requires_notes' => false,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Submit for Approval: in_progress → pending_approval
                ['from' => 'in_progress',      'to' => 'pending_approval', 'action_key' => 'submit_for_approval', 'name' => 'Kirim untuk Pemeriksaan',  'requires_notes' => false,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'supervisor_it', 'channel' => 'database', 'template_code' => 'ticket.pending_approval']]],

                // Revision: pending_approval → need_revision
                ['from' => 'pending_approval', 'to' => 'need_revision',    'action_key' => 'request_revision',  'name' => 'Minta Revisi',                'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'primary_pic', 'channel' => 'database', 'template_code' => 'ticket.revision_requested']]],

                // Resume Revision: need_revision → in_progress
                ['from' => 'need_revision',    'to' => 'in_progress',      'action_key' => 'resume_revision',   'name' => 'Lanjutkan Revisi',            'requires_notes' => false,
                    'permissions' => [['role_key' => 'pic_it_support'], ['role_key' => 'pic_it_develop'], ['role_key' => 'supervisor_it']]],

                // Approve: pending_approval → done
                ['from' => 'pending_approval', 'to' => 'done',             'action_key' => 'approve',           'name' => 'Setujui dan Selesaikan',      'requires_notes' => false,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [
                        ['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.done'],
                        ['recipient_type' => 'primary_pic', 'channel' => 'database', 'template_code' => 'ticket.done_pic'],
                    ]],

                // Reject: submitted → rejected
                ['from' => 'submitted',        'to' => 'rejected',         'action_key' => 'reject',            'name' => 'Tolak Tiket',                 'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.rejected']]],

                // Reject: under_analysis → rejected
                ['from' => 'under_analysis',   'to' => 'rejected',         'action_key' => 'reject',            'name' => 'Tolak Tiket',                 'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.rejected']]],

                // Reject: pending_approval → rejected
                ['from' => 'pending_approval', 'to' => 'rejected',         'action_key' => 'reject',            'name' => 'Tolak Tiket',                 'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.rejected']]],

                // Cancel: submitted → cancelled
                ['from' => 'submitted',        'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it'], ['role_key' => 'requester']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.cancelled']]],

                // Cancel: under_analysis → cancelled
                ['from' => 'under_analysis',   'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'requester', 'channel' => 'database', 'template_code' => 'ticket.cancelled']]],

                // Cancel: assigned → cancelled
                ['from' => 'assigned',         'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: in_progress → cancelled
                ['from' => 'in_progress',      'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: need_info → cancelled
                ['from' => 'need_info',        'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: waiting_external → cancelled
                ['from' => 'waiting_external', 'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: need_revision → cancelled
                ['from' => 'need_revision',    'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: on_hold → cancelled
                ['from' => 'on_hold',          'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Cancel: pending_approval → cancelled
                ['from' => 'pending_approval', 'to' => 'cancelled',        'action_key' => 'cancel',            'name' => 'Batalkan Tiket',              'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']]],

                // Reopen: done → reopened
                ['from' => 'done',             'to' => 'reopened',         'action_key' => 'reopen',            'name' => 'Buka Kembali',                'requires_notes' => true,
                    'permissions' => [['role_key' => 'supervisor_it']],
                    'notifications' => [['recipient_type' => 'primary_pic', 'channel' => 'database', 'template_code' => 'ticket.reopened']]],

                // Reassign from Reopened: reopened → assigned
                ['from' => 'reopened',         'to' => 'assigned',         'action_key' => 'reassign',          'name' => 'Tunjuk Ulang PIC',            'requires_notes' => false,
                    'permissions' => [['role_key' => 'supervisor_it']]],
            ];

            foreach ($transitionsData as $trans) {
                $transition = WorkflowTransition::query()->updateOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'from_stage_id' => $stageModels[$trans['from']]->id,
                        'to_stage_id' => $stageModels[$trans['to']]->id,
                        'action_key' => $trans['action_key'],
                    ],
                    [
                        'name' => $trans['name'],
                        'requires_notes' => $trans['requires_notes'],
                    ]
                );

                // Sync permissions (delete then re-create for idempotency)
                $transition->permissions()->delete();
                foreach ($trans['permissions'] as $perm) {
                    $transition->permissions()->create([
                        'role_key' => $perm['role_key'] ?? null,
                        'permission_code' => $perm['permission_code'] ?? null,
                    ]);
                }

                // Sync notifications
                $transition->notifications()->delete();
                foreach ($trans['notifications'] ?? [] as $notif) {
                    $transition->notifications()->create([
                        'recipient_type' => $notif['recipient_type'],
                        'channel' => $notif['channel'] ?? 'database',
                        'template_code' => $notif['template_code'] ?? null,
                    ]);
                }
            }

            // ----------------------------------------------------------------
            // 4. Approval config for pending_approval stage
            // ----------------------------------------------------------------
            $pendingApprovalStage = $stageModels['pending_approval'];
            $approvalConfig = WorkflowApprovalConfig::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'stage_id' => $pendingApprovalStage->id,
                ],
                [
                    'approval_type' => 'single',
                ]
            );

            $approvalConfig->steps()->updateOrCreate(
                ['step_order' => 1],
                ['approver_role_key' => 'supervisor_it']
            );
        });
    }
}
