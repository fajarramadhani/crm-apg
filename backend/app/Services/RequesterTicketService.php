<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketSubmitted;
use App\Exceptions\IdempotencyConflict;
use App\Models\IdempotencyRecord;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequesterTicketService
{
    private const IDEMPOTENCY_ACTION = 'create_requester_ticket';

    private const IDEMPOTENCY_ENDPOINT = 'POST /api/v1/requester/tickets';

    public function __construct(
        private TicketNumberGenerator $numberGenerator,
        private WorkflowEngineService $workflowEngine,
        private TicketDescriptionSanitizer $descriptionSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<UploadedFile>  $files
     */
    public function createTicket(User $user, array $validated, array $files, ?string $idempotencyKey = null): Ticket
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Inactive user accounts cannot create tickets.'],
            ]);
        }

        if (! $user->division_id && ! $user->office_id) {
            throw ValidationException::withMessages([
                'organization' => ['Akun harus memiliki lokasi kantor atau divisi sebelum membuat tiket.'],
            ]);
        }

        // Resolve dynamic workflow (null = feature flag off or no active workflow)
        $dynamicWorkflow = $this->workflowEngine->resolveWorkflowForNewTicket();

        // If feature flag is ON but no active workflow found → fail safely
        if (config('crm.dynamic_workflow_enabled', false) && ! $dynamicWorkflow) {
            Log::error('crm.dynamic_workflow.no_active_workflow', [
                'actor_id' => $user->id,
                'message' => 'Feature flag CRM_DYNAMIC_WORKFLOW_ENABLED=true but no active workflow exists.',
            ]);

            throw ValidationException::withMessages([
                'workflow' => [
                    'Sistem tidak dapat membuat tiket: konfigurasi workflow aktif tidak ditemukan. '
                    .'Hubungi Administrator untuk mengaktifkan workflow yang valid.',
                ],
            ]);
        }

        $disk = config('tickets.attachment_disk', 'local');
        $storedPaths = [];
        $keyHash = $idempotencyKey === null ? null : hash('sha256', $idempotencyKey);
        $requestHash = $keyHash === null ? null : $this->requestHash($validated, $files);
        $created = false;

        try {
            $ticket = DB::transaction(function () use ($user, $validated, $files, $disk, $dynamicWorkflow, $keyHash, $requestHash, &$storedPaths, &$created): Ticket {
                $idempotencyRecord = null;

                if ($keyHash !== null) {
                    User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                    $idempotencyRecord = IdempotencyRecord::query()
                        ->where('user_id', $user->id)
                        ->where('endpoint', self::IDEMPOTENCY_ENDPOINT)
                        ->where('action', self::IDEMPOTENCY_ACTION)
                        ->where('key_hash', $keyHash)
                        ->lockForUpdate()
                        ->first();

                    if ($idempotencyRecord?->expires_at->isPast()) {
                        $idempotencyRecord->delete();
                        $idempotencyRecord = null;
                    }

                    if ($idempotencyRecord !== null) {
                        if (! hash_equals($idempotencyRecord->request_hash, $requestHash)) {
                            throw new IdempotencyConflict('payload_mismatch');
                        }

                        if ($idempotencyRecord->ticket_id === null) {
                            throw new IdempotencyConflict('in_progress');
                        }

                        return Ticket::query()->findOrFail($idempotencyRecord->ticket_id);
                    }

                    $idempotencyRecord = IdempotencyRecord::query()->create([
                        'user_id' => $user->id,
                        'endpoint' => self::IDEMPOTENCY_ENDPOINT,
                        'action' => self::IDEMPOTENCY_ACTION,
                        'key_hash' => $keyHash,
                        'request_hash' => $requestHash,
                        'expires_at' => now()->addDay(),
                    ]);
                }

                $roleKey = $user->role?->key ?? 'requester';

                // Determine initial status based on workflow mode
                $initialStatus = $dynamicWorkflow
                    ? TicketStatus::Submitted         // Dynamic: workflow's initial stage
                    : TicketStatus::PendingValidation; // Legacy: compatibility flow

                $ticket = Ticket::query()->create([
                    'ticket_number' => $this->numberGenerator->next(),
                    'requester_id' => $user->id,
                    'division_id' => $user->division_id,
                    'branch_id' => $user->branch_id,
                    'office_id' => $user->office_id,
                    'request_category' => $validated['request_category'],
                    'application_id' => $validated['application_id'],
                    'current_division_id' => $user->division_id,
                    'title' => trim($validated['title']),
                    'description' => $this->descriptionSanitizer->sanitize($validated['description']),
                    'affected_url' => isset($validated['affected_url']) ? trim($validated['affected_url']) : null,
                    'reference' => isset($validated['reference']) ? trim($validated['reference']) : null,
                    'urgency' => $validated['urgency'],
                    'status' => $initialStatus,
                    'submitted_at' => now(),
                ]);

                // Attach dynamic workflow if available
                if ($dynamicWorkflow) {
                    $this->workflowEngine->attachWorkflowToTicket($ticket, $dynamicWorkflow);
                    // Refresh ticket after workflow attachment
                    $ticket->refresh();
                }

                // Store attachments
                foreach ($files as $file) {
                    $storedName = Str::uuid()->toString();
                    $path = $file->storeAs("tickets/{$ticket->id}", $storedName, $disk);
                    $storedPaths[] = $path;

                    $ticket->attachments()->create([
                        'uploaded_by' => $user->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'disk' => $disk,
                        'path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(),
                        'category' => 'attachment',
                        'visibility' => 'requester',
                    ]);
                }

                // Status history
                if ($dynamicWorkflow) {
                    // Dynamic: single history entry from initial stage
                    $ticket->histories()->create([
                        'from_status' => null,
                        'to_status' => $ticket->current_workflow_stage ?? 'submitted',
                        'action' => 'created',
                        'actor_id' => $user->id,
                        'actor_role' => $roleKey,
                        'metadata' => [
                            'workflow_mode' => 'dynamic',
                            'workflow_id' => $dynamicWorkflow->id,
                            'workflow_version' => $dynamicWorkflow->version,
                        ],
                    ]);
                } else {
                    // Legacy: two-step history (created → submitted)
                    $ticket->histories()->create([
                        'from_status' => null,
                        'to_status' => TicketStatus::Draft->value,
                        'action' => 'created',
                        'actor_id' => $user->id,
                        'actor_role' => $roleKey,
                    ]);

                    $ticket->histories()->create([
                        'from_status' => TicketStatus::Draft->value,
                        'to_status' => TicketStatus::PendingValidation->value,
                        'action' => 'submitted',
                        'actor_id' => $user->id,
                        'actor_role' => $roleKey,
                    ]);
                }

                if ($idempotencyRecord !== null) {
                    $idempotencyRecord->update([
                        'ticket_id' => $ticket->id,
                        'response_status' => 201,
                    ]);
                }

                $created = true;

                return $ticket;
            });

            $storedPaths = [];

            if ($created) {
                TicketSubmitted::dispatch($ticket);
            }

            return $ticket;
        } catch (Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<UploadedFile>  $files
     */
    private function requestHash(array $validated, array $files): string
    {
        $attachments = array_map(function (UploadedFile $file): array {
            $checksum = hash_file('sha256', $file->getRealPath());
            if ($checksum === false) {
                throw ValidationException::withMessages([
                    'attachments' => ['An attachment could not be read for idempotency verification.'],
                ]);
            }

            return [
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'checksum' => $checksum,
            ];
        }, $files);

        $logicalPayload = [
            'request_category' => $validated['request_category'],
            'application_id' => (int) $validated['application_id'],
            'title' => trim($validated['title']),
            'description' => $this->descriptionSanitizer->sanitize($validated['description']),
            'affected_url' => isset($validated['affected_url']) ? trim($validated['affected_url']) : null,
            'reference' => isset($validated['reference']) ? trim($validated['reference']) : null,
            'urgency' => $validated['urgency'],
            'attachments' => $attachments,
        ];

        return hash('sha256', json_encode($logicalPayload, JSON_THROW_ON_ERROR));
    }
}
