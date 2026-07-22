<?php

namespace App\Console\Commands;

use App\Services\TicketInactivityReminderService;
use Illuminate\Console\Command;

class ScanTicketInactivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:scan-inactivity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan tickets for inactivity and trigger reminders';

    /**
     * Execute the console command.
     */
    public function handle(TicketInactivityReminderService $service)
    {
        $this->info('Starting Inactivity scan...');

        $stats = $service->scanAndAlert();

        $this->info('Scan completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tickets Scanned', $stats['tickets_scanned']],
                ['Alerts Sent', $stats['alerts_sent']],
                ['Failures', $stats['failures']],
            ]
        );

        return $stats['failures'] > 0 ? static::FAILURE : static::SUCCESS;
    }
}
