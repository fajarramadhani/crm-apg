<?php

namespace App\Services;

use App\Enums\TicketStatus;
use Illuminate\Support\Str;

final class PublicTicketStatusMapper
{
    /** @return array{code: string, label: string, description: string} */
    public function map(TicketStatus|string|null $status): array
    {
        $value = $status instanceof TicketStatus ? $status->value : Str::lower((string) $status);
        $code = match ($value) {
            'draft', 'submitted', 'pending_validation' => 'received',
            'need_revision', 'revision', 'need_info', 'waiting_external', 'on_hold' => 'waiting_for_information',
            'assigned' => 'pic_assigned',
            'validated', 'triage', 'transferred', 'under_analysis', 'analysis', 'solution_planning', 'plan_review', 'ready_for_development' => 'under_review',
            'development_in_progress', 'in_progress', 'internal_testing' => 'work_in_progress',
            'ready_for_qa', 'qa_assignment', 'qa_in_progress', 'qa_failed', 'qa_retest', 'ready_for_uat', 'uat_assignment', 'uat_in_progress', 'uat_failed', 'uat_retest' => 'testing',
            'uat_approved', 'approval_pending', 'pending_approval', 'approval_revision', 'release_preparation', 'release_ready', 'deployment_scheduled' => 'finalizing',
            'awaiting_requester_confirmation' => 'waiting_requester_confirmation',
            'deployment_in_progress', 'deployment_failed', 'rollback_in_progress', 'rolled_back', 'deployed', 'monitoring', 'post_release_issue', 'reopened' => 'delivery_and_monitoring',
            'done', 'closed' => 'completed',
            'rejected', 'cancelled' => 'closed_without_completion',
            default => $this->mapDynamic($value),
        };

        return match ($code) {
            'received' => ['code' => $code, 'label' => 'Pengajuan diterima', 'description' => 'Pengajuan Anda telah diterima oleh tim kami.'],
            'waiting_for_information' => ['code' => $code, 'label' => 'Menunggu informasi', 'description' => 'Tim memerlukan informasi tambahan atau sedang menunggu tanggapan terkait.'],
            'pic_assigned' => ['code' => $code, 'label' => 'PIC telah ditentukan', 'description' => 'Tiket telah diteruskan kepada tim yang akan menangani.'],
            'under_review' => ['code' => $code, 'label' => 'Sedang diverifikasi', 'description' => 'Pengajuan sedang ditinjau dan disiapkan untuk penanganan.'],
            'work_in_progress' => ['code' => $code, 'label' => 'Sedang dikerjakan', 'description' => 'Pengerjaan atau pengujian pengajuan sedang berlangsung.'],
            'testing' => ['code' => $code, 'label' => 'Menunggu pengujian', 'description' => 'Penyelesaian sedang melalui proses pengujian.'],
            'finalizing' => ['code' => $code, 'label' => 'Pemeriksaan akhir', 'description' => 'Pengajuan sedang melalui pemeriksaan dan persiapan akhir.'],
            'delivery_and_monitoring' => ['code' => $code, 'label' => 'Penyelesaian diberikan', 'description' => 'Penyelesaian sedang diterapkan, diverifikasi, atau dipantau.'],
            'waiting_requester_confirmation' => ['code' => $code, 'label' => 'Menunggu konfirmasi requester', 'description' => 'Tim sedang menunggu konfirmasi requester atas penyelesaian yang diberikan.'],
            'completed' => ['code' => $code, 'label' => 'Tiket selesai', 'description' => 'Pengajuan telah selesai diproses.'],
            'closed_without_completion' => ['code' => $code, 'label' => 'Pengajuan ditutup', 'description' => 'Pengajuan tidak lagi diproses.'],
            default => ['code' => 'processing', 'label' => 'Sedang diproses', 'description' => 'Pengajuan sedang diproses oleh tim kami.'],
        };
    }

    private function mapDynamic(string $value): string
    {
        return match (true) {
            Str::contains($value, ['cancel', 'reject']) => 'closed_without_completion',
            Str::contains($value, ['close', 'complete', 'done', 'finish']) => 'completed',
            Str::contains($value, ['wait', 'hold', 'revision', 'information', 'info']) => 'waiting_for_information',
            Str::contains($value, ['deploy', 'release', 'monitor', 'delivery']) => 'delivery_and_monitoring',
            Str::contains($value, ['approval', 'approve', 'final']) => 'finalizing',
            Str::contains($value, ['test', 'qa', 'uat']) => 'testing',
            Str::contains($value, ['develop', 'progress', 'work']) => 'work_in_progress',
            Str::contains($value, ['submit', 'receive', 'draft']) => 'received',
            Str::contains($value, ['review', 'triage', 'analysis', 'assign', 'validate', 'plan']) => 'under_review',
            default => 'processing',
        };
    }
}
