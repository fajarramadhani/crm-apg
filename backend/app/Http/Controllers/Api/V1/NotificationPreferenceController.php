<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('notification.preference.manage_own');

        $preferences = NotificationPreference::where('user_id', $request->user()->id)->get();

        return NotificationPreferenceResource::collection($preferences);
    }

    public function update(Request $request, string $notificationType)
    {
        Gate::authorize('notification.preference.manage_own');

        if (! NotificationType::tryFrom($notificationType)) {
            return response()->json(['message' => 'Invalid notification type.'], 422);
        }

        // List of critical types that cannot be muted
        $criticalTypes = [
            NotificationType::SlaBreached->value,
            NotificationType::DeploymentFailed->value,
            NotificationType::RollbackRequired->value,
            NotificationType::TicketEscalated->value,
        ];

        if (in_array($notificationType, $criticalTypes)) {
            return response()->json(['message' => 'Critical notifications cannot be disabled or muted.'], 422);
        }

        $validated = $request->validate([
            'in_app_enabled' => 'required|boolean',
            'muted_until' => 'nullable|date|after_or_equal:today|before_or_equal:+30 days',
        ]);

        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'notification_type' => $notificationType],
            $validated
        );

        return new NotificationPreferenceResource($preference);
    }
}
