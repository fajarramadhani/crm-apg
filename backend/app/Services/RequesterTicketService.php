<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketSubmitted;
use App\Exceptions\IdempotencyConflict;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\IdempotencyRecord;
use App\Models\PublicTicketSubmission;
use App\Models\Ticket;
use App\Models\TicketCategory;
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
        private PublicTicketTrackingService $publicTracking,
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

                $ticket = $this->createCore([
                    'requester_id' => $user->id,
                    'requester_name' => $user->name,
                    'requester_email' => $user->email,
                    'requester_phone' => $user->phone ?? null,
                    'submission_source' => 'authenticated_requester',
                    'division_id' => $user->division_id,
                    'branch_id' => $user->branch_id,
                    'office_id' => $user->office_id,
                    'current_division_id' => $user->division_id,
                ], $validated, $files, $user->id, $user->role?->key ?? 'requester', $disk, $dynamicWorkflow, $storedPaths);

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
    public function createPublicTicket(array $validated, array $files, string $idempotencyKey): PublicTicketCreationResult
    {
        $application = Application::query()->active()->find($validated['application_id']);
        $category = TicketCategory::query()->active()->find($validated['ticket_category_id']);
        $branch = Branch::query()->active()->find($validated['branch_id']);
        if (! $application || ! $category || ! $branch) {
            throw ValidationException::withMessages([
                'master_data' => ['One or more selected options are no longer active.'],
            ]);
        }
        $divisionId = $validated['division_id'] ?? $application->owner_division_id;
        if ($divisionId === null || ! Division::query()->active()->whereKey($divisionId)->exists()) {
            throw ValidationException::withMessages([
                'division_id' => ['A division is required because this application has no owner division.'],
            ]);
        }

        $validated['request_category'] = match ($category->type) {
            'incident' => 'error_bug',
            'request' => 'request',
            default => 'other',
        };
        $dynamicWorkflow = $this->workflowEngine->resolveWorkflowForNewTicket();
        if (config('crm.dynamic_workflow_enabled', false) && ! $dynamicWorkflow) {
            Log::error('crm.dynamic_workflow.no_active_workflow', ['actor' => 'public_requester']);
            throw ValidationException::withMessages(['workflow' => ['No active workflow is available.']]);
        }

        $disk = config('tickets.attachment_disk', 'local');
        $storedPaths = [];
        $keyHash = hash('sha256', $idempotencyKey);
        $requestHash = $this->publicRequestHash($validated, $files);
        $created = false;

        try {
            [$ticket, $trackingRecord] = DB::transaction(function () use ($validated, $files, $disk, $dynamicWorkflow, $keyHash, $requestHash, $divisionId, $branch, &$storedPaths, &$created): array {
                PublicTicketSubmission::query()->where('key_hash', $keyHash)->where('expires_at', '<=', now())->delete();
                $inserted = DB::table('public_ticket_submissions')->insertOrIgnore([
                    'key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'expires_at' => now()->addDay(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $submission = PublicTicketSubmission::query()->where('key_hash', $keyHash)->lockForUpdate()->firstOrFail();

                if (! hash_equals($submission->request_hash, $requestHash)) {
                    throw new IdempotencyConflict('payload_mismatch');
                }
                if (! $inserted) {
                    if ($submission->ticket_id === null) {
                        throw new IdempotencyConflict('in_progress');
                    }

                    if ($submission->tracking_token_id === null) {
                        throw new IdempotencyConflict('in_progress');
                    }

                    return [
                        Ticket::query()->findOrFail($submission->ticket_id),
                        $submission->trackingToken()->firstOrFail(),
                    ];
                }

                $ticket = $this->createCore([
                    'requester_id' => null,
                    'requester_name' => $validated['requester_name'],
                    'requester_email' => $validated['requester_email'] ?? null,
                    'requester_phone' => $validated['requester_phone'] ?? null,
                    'submission_source' => 'public_form',
                    'division_id' => $divisionId,
                    'branch_id' => $branch->id,
                    'office_id' => null,
                    'current_division_id' => $divisionId,
                    'ticket_category_id' => $validated['ticket_category_id'],
                ], $validated, $files, null, 'public_requester', $disk, $dynamicWorkflow, $storedPaths);

                $issued = $this->publicTracking->create($ticket);
                $submission->update([
                    'ticket_id' => $ticket->id,
                    'tracking_token_id' => $issued['record']->id,
                ]);
                $created = true;

                return [$ticket, $issued['record']];
            });

            $storedPaths = [];
            if ($created) {
                TicketSubmitted::dispatch($ticket);
            }

            $receipt = $this->publicTracking->receipt($trackingRecord);

            return new PublicTicketCreationResult(
                $ticket,
                $trackingRecord,
                $receipt['raw_token'],
                $receipt['tracking_url'],
            );
        } catch (Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $validated
     * @param  array<UploadedFile>  $files
     * @param  array<string>  $storedPaths
     */
    private function createCore(array $attributes, array $validated, array $files, ?int $actorId, string $actorRole, string $disk, $dynamicWorkflow, array &$storedPaths): Ticket
    {
        $ticket = Ticket::query()->create([...$attributes,
            'ticket_number' => $this->numberGenerator->next(),
            'request_category' => $validated['request_category'],
            'application_id' => $validated['application_id'],
            'title' => trim($validated['title']),
            'description' => $this->descriptionSanitizer->sanitize($validated['description']),
            'affected_url' => isset($validated['affected_url']) ? trim($validated['affected_url']) : null,
            'reference' => isset($validated['reference']) ? trim($validated['reference']) : null,
            'urgency' => $validated['urgency'],
            'status' => $dynamicWorkflow ? TicketStatus::Submitted : TicketStatus::PendingValidation,
            'submitted_at' => now(),
        ]);

        if ($dynamicWorkflow) {
            $this->workflowEngine->attachWorkflowToTicket($ticket, $dynamicWorkflow);
            $ticket->refresh();
        }

        foreach ($files as $file) {
            $storedName = Str::uuid()->toString();
            $path = $file->storeAs("tickets/{$ticket->id}", $storedName, $disk);
            $storedPaths[] = $path;
            $ticket->attachments()->create([
                'uploaded_by' => $actorId, 'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName, 'disk' => $disk, 'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                'category' => 'attachment', 'visibility' => 'requester',
            ]);
        }

        $metadata = ['source' => $attributes['submission_source']];
        if ($dynamicWorkflow) {
            $ticket->histories()->create([
                'from_status' => null, 'to_status' => $ticket->current_workflow_stage ?? 'submitted',
                'action' => 'created', 'actor_id' => $actorId, 'actor_role' => $actorRole,
                'metadata' => [...$metadata, 'workflow_mode' => 'dynamic', 'workflow_id' => $dynamicWorkflow->id, 'workflow_version' => $dynamicWorkflow->version],
            ]);
        } else {
            $ticket->histories()->create(['from_status' => null, 'to_status' => TicketStatus::Draft->value, 'action' => 'created', 'actor_id' => $actorId, 'actor_role' => $actorRole, 'metadata' => $metadata]);
            $ticket->histories()->create(['from_status' => TicketStatus::Draft->value, 'to_status' => TicketStatus::PendingValidation->value, 'action' => 'submitted', 'actor_id' => $actorId, 'actor_role' => $actorRole, 'metadata' => $metadata]);
        }

        return $ticket;
    }

    /** @param array<string, mixed> $validated @param array<UploadedFile> $files */
    private function publicRequestHash(array $validated, array $files): string
    {
        unset($validated['website']);
        $validated['description'] = $this->descriptionSanitizer->sanitize($validated['description']);
        $validated['attachments'] = array_map(fn (UploadedFile $file): array => [
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ], $files);
        ksort($validated);

        return hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR));
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
