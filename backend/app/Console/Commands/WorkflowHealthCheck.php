<?php

namespace App\Console\Commands;

use App\Models\WorkflowDefinition;
use App\Services\WorkflowSnapshotBuilder;
use App\Services\WorkflowValidatorService;
use Illuminate\Console\Command;

/**
 * Health check command that must pass before enabling CRM_DYNAMIC_WORKFLOW_ENABLED.
 *
 * Checks:
 *  - Active workflow exists.
 *  - Exactly one initial stage.
 *  - At least one terminal stage.
 *  - All stages reachable.
 *  - No transition pointing to invalid stage.
 *  - All transitions have permissions.
 *  - Workflow has a version.
 *  - Snapshot can be built.
 */
class WorkflowHealthCheck extends Command
{
    protected $signature = 'crm:workflow-check {--all : Check all published workflows, not just the active one}';

    protected $description = 'Verify that a valid active workflow exists before enabling the dynamic workflow feature flag.';

    public function __construct(
        private WorkflowValidatorService $validator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('=== CRM Dynamic Workflow Health Check ===');
        $this->newLine();

        $flag = config('crm.dynamic_workflow_enabled', false);
        $this->line('Feature flag CRM_DYNAMIC_WORKFLOW_ENABLED: <comment>'.($flag ? 'true' : 'false').'</comment>');
        $this->newLine();

        // 1. Find active workflow
        $activeWorkflows = WorkflowDefinition::query()
            ->where('config_status', 'active')
            ->get();

        if ($activeWorkflows->count() !== 1) {
            if ($activeWorkflows->isEmpty()) {
                $this->error('[FAIL] Tidak ada workflow dengan config_status=active.');
                $this->line('       Buat dan publish workflow, lalu aktifkan melalui Admin Workflow Management.');
            } else {
                $this->error('[FAIL] Ditemukan lebih dari satu workflow dengan config_status=active.');
            }

            return self::FAILURE;
        }

        $activeWorkflow = $activeWorkflows->first();

        if (! $activeWorkflow->is_active) {
            $this->error('[FAIL] Workflow dengan config_status=active harus memiliki is_active=true.');

            return self::FAILURE;
        }

        $this->info("[PASS] Active workflow ditemukan: {$activeWorkflow->code} v{$activeWorkflow->version} ({$activeWorkflow->name})");

        if (! $this->checkWorkflow($activeWorkflow, true)) {
            return self::FAILURE;
        }

        // 4. Optionally check all published workflows
        if ($this->option('all')) {
            $this->newLine();
            $this->line('--- Checking all published workflows ---');

            $published = WorkflowDefinition::query()->published()->get();
            $allValid = true;
            foreach ($published as $wf) {
                if ($wf->is($activeWorkflow)) {
                    continue;
                }

                $this->line("Checking {$wf->code} v{$wf->version} ({$wf->config_status})");
                $allValid = $this->checkWorkflow($wf) && $allValid;
            }

            if (! $allValid) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('=== All checks PASSED ===');
        $this->newLine();

        if (! $flag) {
            $this->warn('Feature flag CRM_DYNAMIC_WORKFLOW_ENABLED masih false.');
            $this->line('Aktifkan dengan menambahkan CRM_DYNAMIC_WORKFLOW_ENABLED=true ke .env setelah memverifikasi lingkungan aman.');
        } else {
            $this->info('Feature flag aktif. Tiket baru akan menggunakan dynamic workflow.');
        }

        return self::SUCCESS;
    }

    private function checkWorkflow(WorkflowDefinition $workflow, bool $showSnapshotDetails = false): bool
    {
        $errors = $this->validator->validate($workflow);

        if (! empty($errors)) {
            $this->error('[FAIL] Validasi workflow gagal:');
            foreach ($errors as $error) {
                $this->line("       • {$error}");
            }

            return false;
        }

        $this->info('[PASS] Validasi integritas workflow: OK');

        try {
            $snapshot = app(WorkflowSnapshotBuilder::class)->build($workflow);
        } catch (\Throwable $e) {
            $this->error('[FAIL] Snapshot gagal dibangun: '.$e->getMessage());

            return false;
        }

        $this->info('[PASS] Snapshot dapat dibangun.');
        if ($showSnapshotDetails) {
            $this->line("       Initial stage : <comment>{$snapshot['initial_stage']}</comment>");
            $this->line('       Terminal stages: <comment>'.implode(', ', $snapshot['terminal_stages']).'</comment>');
            $this->line('       Total stages   : <comment>'.count($snapshot['stages']).'</comment>');

            $totalTransitions = collect($snapshot['stages'])->sum(fn (array $stage): int => count($stage['transitions']));
            $this->line("       Total transitions: <comment>{$totalTransitions}</comment>");
        }

        return true;
    }
}
