<?php

namespace App\Providers;

use App\Events\TicketAnalysisCompleted;
use App\Events\TicketAnalysisStarted;
use App\Events\TicketDevelopmentProgressUpdated;
use App\Events\TicketDevelopmentStarted;
use App\Events\TicketInternalTestingFailed;
use App\Events\TicketInternalTestingStarted;
use App\Events\TicketReadyForQa;
use App\Events\TicketRejected;
use App\Events\TicketResubmitted;
use App\Events\TicketRevisionRequested;
use App\Events\TicketSolutionPlanApproved;
use App\Events\TicketSolutionPlanRevisionRequested;
use App\Events\TicketSolutionPlanSubmitted;
use App\Events\TicketSubmitted;
use App\Events\TicketTransferred;
use App\Events\TicketValidated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen([
            TicketSubmitted::class, TicketRevisionRequested::class,
            TicketResubmitted::class, TicketValidated::class,
            TicketRejected::class, TicketTransferred::class,
            TicketAnalysisStarted::class, TicketAnalysisCompleted::class,
            TicketSolutionPlanSubmitted::class, TicketSolutionPlanRevisionRequested::class,
            TicketSolutionPlanApproved::class,
            TicketDevelopmentStarted::class, TicketDevelopmentProgressUpdated::class,
            TicketInternalTestingStarted::class, TicketInternalTestingFailed::class, TicketReadyForQa::class,
        ], function (object $event): void {
            Log::info('Ticket domain event', ['event' => $event::class, 'ticket_id' => $event->ticket->id]);
        });
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
