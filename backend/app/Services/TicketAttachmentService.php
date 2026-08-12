<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class TicketAttachmentService
{
    public function disk(): string
    {
        $disk = (string) config('tickets.attachment_disk', 'local');
        $configuration = config("filesystems.disks.{$disk}");

        if (! is_array($configuration)) {
            throw new RuntimeException("Ticket attachment disk [{$disk}] is not configured.");
        }

        if ($disk === 'public' || ($configuration['visibility'] ?? null) === 'public') {
            throw new RuntimeException('Ticket attachments must use a private filesystem disk.');
        }

        return $disk;
    }

    /** @param array<string, mixed> $attributes */
    public function store(
        Ticket $ticket,
        UploadedFile $file,
        ?int $uploadedBy,
        string $category,
        string $visibility,
        array $attributes = [],
        ?string $directory = null,
        ?Closure $afterCreate = null,
    ): TicketAttachment {
        $disk = $this->disk();
        $this->assertSafeOriginalName($file);
        $storedName = Str::uuid()->toString();
        $path = $file->storeAs($directory ?? "tickets/{$ticket->id}", $storedName, $disk);

        if (! is_string($path)) {
            throw new RuntimeException('Attachment could not be stored.');
        }

        try {
            return DB::transaction(function () use ($ticket, $file, $uploadedBy, $category, $visibility, $attributes, $disk, $storedName, $path, $afterCreate): TicketAttachment {
                $attachment = $ticket->attachments()->create([
                    'uploaded_by' => $uploadedBy,
                    'original_name' => $this->safeOriginalName($file),
                    'stored_name' => $storedName,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'category' => $category,
                    'visibility' => $visibility === 'requester_visible' ? 'requester' : $visibility,
                    ...$attributes,
                ]);

                $afterCreate?->__invoke($attachment);

                return $attachment;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function delete(TicketAttachment $attachment, Closure $deleteMetadata): void
    {
        $storage = Storage::disk($attachment->disk);
        $quarantine = 'attachment-deletion-quarantine/'.Str::uuid()->toString();
        $moved = false;

        if ($storage->exists($attachment->path)) {
            if (! $storage->move($attachment->path, $quarantine)) {
                throw new RuntimeException('Attachment file could not be quarantined for deletion.');
            }
            $moved = true;
        }

        try {
            DB::transaction(fn () => $deleteMetadata($attachment));
        } catch (Throwable $exception) {
            if ($moved && ! $storage->move($quarantine, $attachment->path)) {
                report(new RuntimeException("Attachment {$attachment->id} could not be restored after metadata rollback."));
            }

            throw $exception;
        }

        if ($moved && ! $storage->delete($quarantine)) {
            report(new RuntimeException("Attachment {$attachment->id} deletion quarantine could not be cleaned."));
        }
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));

        return $name !== '' ? mb_substr($name, 0, 255) : 'attachment';
    }

    private function assertSafeOriginalName(UploadedFile $file): void
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (preg_match('/\.(?:exe|com|bat|cmd|msi|ps1|php|phtml|phar|js|jar|sh|svg|html?|xhtml)(?:\.|$)/i', $name)) {
            throw ValidationException::withMessages(['file' => ['Executable or active-content filenames are not allowed.']]);
        }
    }
}
