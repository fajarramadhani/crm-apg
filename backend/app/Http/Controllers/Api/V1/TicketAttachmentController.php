<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadTicketAttachmentRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketAttachmentController extends Controller
{
    public function store(UploadTicketAttachmentRequest $request, Ticket $ticket)
    {
        $file = $request->file('file');
        $disk = config('tickets.attachment_disk', 'local');
        $stored = Str::uuid()->toString();
        $path = $file->storeAs("tickets/{$ticket->id}", $stored, $disk);
        $attachment = $ticket->attachments()->create(['uploaded_by' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'stored_name' => $stored, 'disk' => $disk, 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(), 'category' => $request->input('category', 'other')]);
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => 'attachment_uploaded', 'actor_id' => $request->user()->id, 'actor_role' => $request->user()->role?->key ?? 'requester', 'metadata' => ['attachment_id' => $attachment->id, 'mime_type' => $attachment->mime_type, 'size' => $attachment->size]]);

        return ApiResponse::success($request, 'Attachment uploaded', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        Gate::authorize('view', $ticket);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);
        Gate::authorize('manageAttachment', $ticket);
        abort_unless($attachment->uploaded_by === $request->user()->id, 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $id = $attachment->id;
        $attachment->delete();
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => 'attachment_removed', 'actor_id' => $request->user()->id, 'actor_role' => $request->user()->role?->key ?? 'requester', 'metadata' => ['attachment_id' => $id]]);

        return ApiResponse::success($request,'Attachment removed');
    }
}
