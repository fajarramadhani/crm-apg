<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SlaEscalationPolicyResource;
use App\Models\SlaEscalationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminSlaEscalationPolicyController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('sla_escalation_policy.view');

        $policies = SlaEscalationPolicy::orderBy('id', 'desc')->paginate(min($request->get('per_page', 20), 100));

        return SlaEscalationPolicyResource::collection($policies);
    }

    public function store(Request $request)
    {
        Gate::authorize('sla_escalation_policy.manage');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'nullable|string',
            'sla_type' => 'required|in:response,resolution',
            'warning_threshold_percent' => 'required|integer|min:1|max:99',
            'critical_threshold_percent' => 'required|integer|min:1|max:100|gte:warning_threshold_percent',
            'inactivity_threshold_minutes' => 'nullable|integer|min:1',
            'escalate_to_it_lead' => 'boolean',
            'escalate_to_manager' => 'boolean',
            'escalate_to_supervisor' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->id;

        $policy = SlaEscalationPolicy::create($validated);

        return new SlaEscalationPolicyResource($policy);
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('sla_escalation_policy.manage');

        $policy = SlaEscalationPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'priority' => 'nullable|string',
            'sla_type' => 'sometimes|required|in:response,resolution',
            'warning_threshold_percent' => 'sometimes|required|integer|min:1|max:99',
            'critical_threshold_percent' => 'sometimes|required|integer|min:1|max:100|gte:warning_threshold_percent',
            'inactivity_threshold_minutes' => 'nullable|integer|min:1',
            'escalate_to_it_lead' => 'boolean',
            'escalate_to_manager' => 'boolean',
            'escalate_to_supervisor' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = $request->user()->id;

        $policy->update($validated);

        return new SlaEscalationPolicyResource($policy);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('sla_escalation_policy.manage');

        $policy = SlaEscalationPolicy::findOrFail($id);

        // Deactivate instead of hard delete if used, but for simplicity here we just deactivate
        $policy->update(['is_active' => false, 'updated_by' => $request->user()->id]);

        return response()->noContent();
    }
}
