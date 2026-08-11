<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadTicketAttachmentRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function store(UploadTicketAttachmentRequest $request, Ticket $ticket, TicketAttachmentService $attachments)
    {
        $file = $request->file('file');
        $attachment = $attachments->store($ticket, $file, $request->user()->id, $request->input('category', 'other'), 'requester', afterCreate: function (TicketAttachment $attachment) use ($request, $ticket): void {
            $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => 'attachment_uploaded', 'actor_id' => $request->user()->id, 'actor_role' => $request->user()->role?->key ?? 'requester', 'metadata' => ['attachment_id' => $attachment->id, 'mime_type' => $attachment->mime_type, 'size' => $attachment->size]]);
        });

        return ApiResponse::success($request, 'Attachment uploaded', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        Gate::authorize('view', $ticket);
        abort_if($request->user()->hasRole('requester') && ($attachment->visibility ?? 'internal') !== 'requester', 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment, TicketAttachmentService $attachments)
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        Gate::authorize('manageAttachment', $ticket);
        abort_unless($attachment->uploaded_by === $request->user()->id, 403);
        $attachments->delete($attachment, function (TicketAttachment $attachment) use ($request, $ticket): void {
            $id = $attachment->id;
            $attachment->delete();
            $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => 'attachment_removed', 'actor_id' => $request->user()->id, 'actor_role' => $request->user()->role?->key ?? 'requester', 'metadata' => ['attachment_id' => $id]]);
        });

        return ApiResponse::success($request, 'Attachment removed');
    }
}
