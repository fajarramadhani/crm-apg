<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketSlaAlertResource;
use App\Models\TicketSlaAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItLeadAlertController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('alert.itlead.view');

        $query = TicketSlaAlert::with(['ticket', 'ticket.application', 'ticket.requester', 'ticket.pic'])
            ->whereIn('recipient_scope', ['it_lead', 'it_lead,manager', 'supervisor,it_lead,manager', 'supervisor,it_lead'])
            ->orWhere('alert_level', 'critical')
            ->orWhere('alert_level', 'breached')
            ->orderBy('triggered_at', 'desc');

        if ($request->filled('severity')) {
            $query->where('alert_level', $request->severity);
        }

        // Need to add inactivity filter etc. But this controller only returns SLA alerts right now based on our query. We could merge or have separate endpoint.

        $alerts = $query->paginate(min($request->get('per_page', 20), 100));

        return TicketSlaAlertResource::collection($alerts);
    }

    public function sla(Request $request)
    {
        return $this->index($request);
    }

    public function inactivity(Request $request)
    {
        Gate::authorize('alert.itlead.view');
        // We do not have a separate table for inactivity alerts right now, they are sent as notifications.
        // We could fetch notifications that are 'ticket_inactive' for this user.
        $query = $request->user()->notifications()->where('data->type', 'ticket_inactive');

        $notifications = $query->paginate(min($request->get('per_page', 20), 100));

        return response()->json($notifications);
    }
}
