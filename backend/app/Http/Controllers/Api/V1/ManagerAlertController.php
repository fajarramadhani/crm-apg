<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketSlaAlertResource;
use App\Models\TicketSlaAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ManagerAlertController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('alert.manager.view');

        $user = $request->user();

        // Scope strictly to critical or breached for the manager's division, or if they are assigned a scope
        // Assuming a division_id scope or similar business scope.
        $query = TicketSlaAlert::with(['ticket', 'ticket.application', 'ticket.requester', 'ticket.pic'])
            ->whereHas('ticket', function ($q) use ($user) {
                // If they have division_id, restrict to their division. Otherwise, if IT manager, maybe broader.
                if ($user->division_id) {
                    $q->whereHas('requester', function ($rq) use ($user) {
                        $rq->where('division_id', $user->division_id);
                    });
                }
            })
            ->where(function ($q) {
                $q->where('recipient_scope', 'like', '%manager%')
                    ->orWhere('alert_level', 'critical')
                    ->orWhere('alert_level', 'breached');
            })
            ->orderBy('triggered_at', 'desc');

        $alerts = $query->paginate(min($request->get('per_page', 20), 100));

        return TicketSlaAlertResource::collection($alerts);
    }
}
