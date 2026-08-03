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
        PublicRequestHistoryChallenge::query()->where('expires_at', '<=', now())->delete();
        PublicRequestHistoryAccessToken::query()->where('expires_at', '<=', now())->delete();
        $this->info('Expired public request history credentials were cleaned up.');

        return self::SUCCESS;
    }
}
