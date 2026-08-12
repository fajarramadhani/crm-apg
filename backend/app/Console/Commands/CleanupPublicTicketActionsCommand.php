<?php

namespace App\Console\Commands;

use App\Models\PublicTicketActionAccessToken;
use App\Models\PublicTicketActionChallenge;
use App\Models\PublicTicketActionIdempotency;
use Illuminate\Console\Command;

class CleanupPublicTicketActionsCommand extends Command
{
    protected $signature = 'public-actions:cleanup';

    protected $description = 'Delete expired public ticket action credentials';

    public function handle(): int
    {
        $this->deleteExpired(PublicTicketActionIdempotency::query());
        $this->deleteExpired(PublicTicketActionChallenge::query());
        $this->deleteExpired(PublicTicketActionAccessToken::query());
        $this->info('Expired public ticket action credentials were cleaned up.');

        return self::SUCCESS;
    }

    private function deleteExpired($query): void
    {
        do {
            $ids = (clone $query)->where('expires_at', '<=', now())->orderBy('id')->limit(500)->pluck('id');
            if ($ids->isNotEmpty()) {
                (clone $query)->whereKey($ids)->delete();
            }
        } while ($ids->count() === 500);
    }
}
