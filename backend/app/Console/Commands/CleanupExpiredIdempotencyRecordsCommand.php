<?php

namespace App\Console\Commands;

use App\Models\IdempotencyRecord;
use Illuminate\Console\Command;

class CleanupExpiredIdempotencyRecordsCommand extends Command
{
    protected $signature = 'idempotency:cleanup';

    protected $description = 'Delete expired idempotency records';

    public function handle(): int
    {
        $deleted = IdempotencyRecord::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired idempotency record(s).");

        return self::SUCCESS;
    }
}
