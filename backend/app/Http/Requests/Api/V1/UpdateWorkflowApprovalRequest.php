<?php

namespace App\Http\Requests\Api\V1;

use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWorkflowApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow instanceof WorkflowDefinition
            && $this->user()?->can('updateApproval', $workflow) === true;
    }

    public function rules(): array
    {
        /** @var WorkflowDefinition $workflow */
        $workflow = $this->route('workflow');

        return [
            'stage_id' => [
                'required',
                'integer',
                Rule::exists('workflow_stages', 'id')->where(fn ($query) => $query
                    ->where('workflow_id', $workflow->id)
                    ->where('stage_type', 'approval')),
            ],
            'label' => ['required', 'string', 'max:150'],
            'notes_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'approval_type' => ['prohibited'],
            'approver_role_key' => ['prohibited'],
            'steps' => ['prohibited'],
            'parallel' => ['prohibited'],
            'query' => ['prohibited'],
        ];
    }
}
