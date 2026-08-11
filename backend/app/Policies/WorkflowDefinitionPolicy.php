<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowDefinition;

final class WorkflowDefinitionPolicy
{
    public function viewApproval(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->hasPermission('workflow.approval.manage');
    }

    public function updateApproval(User $user, WorkflowDefinition $workflow): bool
    {
        return $user->hasPermission('workflow.approval.manage');
    }
}
