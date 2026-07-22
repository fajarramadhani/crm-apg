<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationDetailResource;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('notification.view_own');

        $query = $request->user()->notifications();

        // Filters
        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->status === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        if ($request->filled('type')) {
            $query->where('data->type', $request->type);
        }

        if ($request->filled('severity')) {
            $query->where('data->severity', $request->severity);
        }

        // Exclude archived
        $query->whereNull('archived_at');

        $notifications = $query->paginate(min($request->get('per_page', 20), 100));

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request)
    {
        Gate::authorize('notification.view_own');

        $count = $request->user()->unreadNotifications()->whereNull('archived_at')->count();

        return response()->json(['count' => $count]);
    }

    public function show(Request $request, string $id)
    {
        Gate::authorize('notification.view_own');

        $notification = $request->user()->notifications()->whereNull('archived_at')->findOrFail($id);

        return new NotificationDetailResource($notification);
    }

    public function markAsRead(Request $request, string $id)
    {
        Gate::authorize('notification.manage_own');

        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAsUnread(Request $request, string $id)
    {
        Gate::authorize('notification.manage_own');

        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->read_at = null;
        $notification->save();

        return response()->json(['message' => 'Marked as unread']);
    }

    public function markAllAsRead(Request $request)
    {
        Gate::authorize('notification.manage_own');

        $request->user()->unreadNotifications()->whereNull('archived_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read']);
    }

    public function archive(Request $request, string $id)
    {
        Gate::authorize('notification.manage_own');

        $notification = $request->user()->notifications()->findOrFail($id);
        if (method_exists($notification, 'archive')) {
            $notification->archive();
        } else {
            $notification->update(['archived_at' => now()]);
        }

        return response()->json(['message' => 'Archived']);
    }

    public function archiveRead(Request $request)
    {
        Gate::authorize('notification.manage_own');

        $request->user()->readNotifications()->whereNull('archived_at')->update(['archived_at' => now()]);

        return response()->json(['message' => 'Archived read notifications']);
    }
}
