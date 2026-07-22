<?php

namespace App\Console\Commands;

use App\Services\TicketSlaEscalationService;
use Illuminate\Console\Command;

class ScanSlaAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:scan-sla-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active tickets for SLA approaching and breach conditions';

    /**
     * Execute the console command.
     */
    public function handle(TicketSlaEscalationService $service)
    {
        $this->info('Starting SLA scan...');

        $stats = $service->scanAndAlert();

        $this->info('Scan completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tickets Scanned', $stats['tickets_scanned']],
                ['Approaching Alerts', $stats['approaching_alerts']],
                ['Critical Alerts', $stats['critical_alerts']],
                ['Breach Alerts', $stats['breach_alerts']],
                ['Notifications Created', $stats['notifications_created']],
                ['Notifications Skipped', $stats['notifications_skipped']],
                ['Failures', $stats['failures']],
            ]
        );

        return $stats['failures'] > 0 ? static::FAILURE : static::SUCCESS;
    }
}
