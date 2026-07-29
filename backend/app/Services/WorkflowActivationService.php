<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use Illuminate\Support\Facades\DB;

final class WorkflowActivationService
{
    public function __construct(private WorkflowValidatorService $validator) {}

    /** @return array{0: string, 1: string, 2?: array<string, mixed>}|null */
    public function activate(WorkflowDefinition $workflow): ?array
    {
        return DB::transaction(function () use ($workflow): ?array {
            WorkflowDefinition::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $lockedWorkflow = WorkflowDefinition::query()->findOrFail($workflow->id);

            if (! $lockedWorkflow->isPublished()) {
                return ['Hanya workflow yang sudah dipublikasikan yang dapat diaktifkan.', 'WORKFLOW_NOT_PUBLISHED'];
            }

            if ($lockedWorkflow->isActive()) {
                return ['Workflow sudah aktif.', 'WORKFLOW_ALREADY_ACTIVE'];
            }

            $errors = $this->validator->validate($lockedWorkflow);
            if ($errors !== []) {
                return ['Workflow tidak valid dan tidak dapat diaktifkan.', 'WORKFLOW_INVALID', ['validation_errors' => $errors]];
            }

            WorkflowDefinition::query()
                ->where('config_status', 'active')
                ->where('id', '!=', $lockedWorkflow->id)
                ->update(['config_status' => 'inactive', 'is_active' => false]);

            $lockedWorkflow->update(['config_status' => 'active', 'is_active' => true]);

            return null;
        });
    }
}
