<?php

namespace App\Console\Commands;

use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MigratePublicTicketAttachmentsCommand extends Command
{
    protected $signature = 'tickets:migrate-public-attachments
        {--dry-run : Report candidates without changing files or metadata}
        {--limit=500 : Maximum records to process}
        {--ticket= : Process only one ticket ID}
        {--rollback : Restore previously migrated records to the public disk}';

    protected $description = 'Migrate legacy public ticket attachments to the configured private disk with verified rollback backups';

    public function handle(TicketAttachmentService $attachments): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $ticketId = $this->option('ticket');
        if ($limit === false || ($ticketId !== null && filter_var($ticketId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $this->error('--limit must be between 1 and 10000 and --ticket must be a positive integer.');

            return self::INVALID;
        }

        $targetDisk = $attachments->disk();
        $rollback = (bool) $this->option('rollback');
        $query = TicketAttachment::query()
            ->where('disk', $rollback ? $targetDisk : 'public')
            ->when($ticketId !== null, fn ($builder) => $builder->where('ticket_id', (int) $ticketId))
            ->orderBy('id')
            ->limit($limit);
        $candidates = $query->get();
        $processed = 0;
        $failed = 0;
        $inventory = $rollback ? null : $this->inventory($candidates, $ticketId);

        foreach ($candidates as $attachment) {
            if ($rollback && ! Storage::disk($targetDisk)->exists($this->backupPath($attachment))) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line('Would '.($rollback ? 'rollback' : 'migrate')." attachment {$attachment->id} for ticket {$attachment->ticket_id}.");
                $processed++;

                continue;
            }

            try {
                $rollback ? $this->rollback($attachment, $targetDisk) : $this->migrate($attachment, $targetDisk);
                $processed++;
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Attachment {$attachment->id} failed: {$exception->getMessage()}");
                $failed++;
            }
        }

        if ($inventory !== null) {
            $this->line(json_encode(['attachment_inventory' => $inventory], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        $this->info(($rollback ? 'Rollback' : 'Migration')." complete: {$processed} processed, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function migrate(TicketAttachment $attachment, string $targetDisk): void
    {
        $source = Storage::disk('public');
        $target = Storage::disk($targetDisk);
        if (! $source->exists($attachment->path)) {
            throw new RuntimeException('Source file is missing.');
        }

        $contents = $source->get($attachment->path);
        $checksum = hash('sha256', $contents);
        $backupPath = $this->backupPath($attachment);
        $this->writeVerified($targetDisk, $attachment->path, $contents, $checksum);
        $this->writeVerified($targetDisk, $backupPath, $contents, $checksum);

        try {
            DB::transaction(function () use ($attachment, $targetDisk): void {
                $locked = TicketAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
                if ($locked->disk !== 'public' || $locked->path !== $attachment->path) {
                    throw new RuntimeException('Attachment metadata changed during migration.');
                }
                $locked->update(['disk' => $targetDisk]);
            });

            if (! $source->delete($attachment->path)) {
                throw new RuntimeException('Public source could not be removed.');
            }
        } catch (Throwable $exception) {
            TicketAttachment::query()->whereKey($attachment->id)->where('disk', $targetDisk)->update(['disk' => 'public']);
            $target->delete([$attachment->path, $backupPath]);

            throw $exception;
        }
    }

    private function rollback(TicketAttachment $attachment, string $targetDisk): void
    {
        $target = Storage::disk($targetDisk);
        $backupPath = $this->backupPath($attachment);
        $contents = $target->get($backupPath);
        $checksum = hash('sha256', $contents);
        $this->writeVerified('public', $attachment->path, $contents, $checksum);

        try {
            DB::transaction(function () use ($attachment, $targetDisk): void {
                $locked = TicketAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
                if ($locked->disk !== $targetDisk || $locked->path !== $attachment->path) {
                    throw new RuntimeException('Attachment metadata changed during rollback.');
                }
                $locked->update(['disk' => 'public']);
            });
            $target->delete($attachment->path);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($attachment->path);

            throw $exception;
        }
    }

    private function writeVerified(string $disk, string $path, string $contents, string $checksum): void
    {
        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            if (hash('sha256', $storage->get($path)) !== $checksum) {
                throw new RuntimeException("A different file already exists at [{$disk}:{$path}].");
            }

            return;
        }
        if (! $storage->put($path, $contents) || hash('sha256', $storage->get($path)) !== $checksum) {
            $storage->delete($path);
            throw new RuntimeException("Checksum verification failed on disk [{$disk}].");
        }
    }

    private function backupPath(TicketAttachment $attachment): string
    {
        return "attachment-migration-backups/public/{$attachment->id}";
    }

    /**
     * @param  iterable<TicketAttachment>  $candidates
     * @return array<string, int>
     */
    private function inventory(iterable $candidates, mixed $ticketId): array
    {
        $storage = Storage::disk('public');
        $available = 0;
        $missing = 0;
        $inconsistent = 0;
        $suspicious = 0;
        $bytes = 0;
        $tickets = [];

        foreach ($candidates as $attachment) {
            $tickets[$attachment->ticket_id] = true;
            $unsafePath = $attachment->path === '' || str_starts_with($attachment->path, '/') || str_contains($attachment->path, '..');
            $unsafeName = preg_match('/\.(?:exe|com|bat|cmd|msi|ps1|php|phtml|phar|js|jar|sh|svg|html?|xhtml)(?:\.|$)/i', $attachment->original_name) === 1;
            if ($unsafeName) {
                $suspicious++;
            }
            if ($unsafePath || $attachment->stored_name === '') {
                $inconsistent++;
            }
            if (! $storage->exists($attachment->path)) {
                $missing++;

                continue;
            }

            $available++;
            $actualSize = $storage->size($attachment->path);
            $bytes += $actualSize;
            if ((int) $attachment->size !== $actualSize) {
                $inconsistent++;
            }
        }

        return [
            'public_records_total' => TicketAttachment::query()->where('disk', 'public')
                ->when($ticketId !== null, fn ($query) => $query->where('ticket_id', (int) $ticketId))->count(),
            'selected_records' => count($candidates),
            'available_files' => $available,
            'missing_files' => $missing,
            'inconsistent_metadata' => $inconsistent,
            'available_bytes' => $bytes,
            'impacted_tickets' => count($tickets),
            'suspicious_names' => $suspicious,
        ];
    }
}
