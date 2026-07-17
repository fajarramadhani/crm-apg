<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'uploaded_by', 'original_name', 'stored_name', 'disk', 'path', 'mime_type', 'size', 'category', 'visibility', 'defect_id', 'uat_finding_id'])]
class TicketAttachment extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(TicketQaDefect::class, 'defect_id');
    }

    public function uatFinding(): BelongsTo
    {
        return $this->belongsTo(TicketUatFinding::class, 'uat_finding_id');
    }
}
