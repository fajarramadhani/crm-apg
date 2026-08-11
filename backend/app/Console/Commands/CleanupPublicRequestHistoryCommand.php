<?php

namespace App\Console\Commands;

use App\Models\PublicRequestHistoryAccessToken;
use App\Models\PublicRequestHistoryChallenge;
use Illuminate\Console\Command;

class CleanupPublicRequestHistoryCommand extends Command
{
    protected $signature = 'public-history:cleanup';

    protected $description = 'Delete expired public request history credentials';

    public function handle(): int
    {
        $this->deleteExpired(PublicRequestHistoryChallenge::query());
        $this->deleteExpired(PublicRequestHistoryAccessToken::query());
        $this->info('Expired public request history credentials were cleaned up.');

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
