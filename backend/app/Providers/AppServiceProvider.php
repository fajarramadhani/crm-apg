<?php

namespace App\Providers;

use App\Contracts\PublicHistoryOtpDelivery;
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
use App\Listeners\KnowledgeBaseNotificationSubscriber;
use App\Listeners\TicketNotificationSubscriber;
use App\Services\InMemoryPublicHistoryOtpDelivery;
use App\Services\MailPublicHistoryOtpDelivery;
use App\Services\PublicRequestHistoryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->app->singleton(InMemoryPublicHistoryOtpDelivery::class);
        $this->app->bind(PublicHistoryOtpDelivery::class, function ($app) {
            return match (config('public_history.driver')) {
                'mail' => $app->make(MailPublicHistoryOtpDelivery::class),
                'fake' => $app->make(InMemoryPublicHistoryOtpDelivery::class),
                default => throw new \RuntimeException('Unsupported public history delivery driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->runningInConsole() && app()->environment('production')) {
            $trackingKey = config('public_tracking.key');
            $trackingSecret = is_string($trackingKey) && Str::startsWith($trackingKey, 'base64:')
                ? base64_decode(Str::after($trackingKey, 'base64:'), true)
                : $trackingKey;
            $historySecrets = collect(['identity_key', 'otp_pepper'])->map(function (string $key) {
                $value = config("public_history.{$key}");

                return is_string($value) && Str::startsWith($value, 'base64:')
                    ? base64_decode(Str::after($value, 'base64:'), true) : $value;
            });
            $historyDurations = collect(['otp_expiry_minutes', 'max_attempts', 'resend_cooldown_seconds', 'access_ttl_minutes']);
            $unsafe = config('app.debug')
                || blank(config('app.key'))
                || ! config('session.secure')
                || Str::contains((string) config('app.url'), ['localhost', '127.0.0.1'])
                || ! is_string($trackingSecret)
                || strlen($trackingSecret) < 32
                || config('public_history.driver') !== 'mail'
                || in_array(config('mail.default'), ['log', 'array'], true)
                || $historySecrets->contains(fn ($secret) => ! is_string($secret) || strlen($secret) < 32)
                || $historyDurations->contains(fn (string $key) => ! is_int(config("public_history.{$key}")) || config("public_history.{$key}") < 1);

            if ($unsafe) {
                throw new \RuntimeException('Unsafe production environment configuration.');
            }
        }

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

        Event::subscribe(TicketNotificationSubscriber::class);
        Event::subscribe(KnowledgeBaseNotificationSubscriber::class);

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(20)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('account:'.$email),
                Limit::perMinute(5)->by('pair:'.$email.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('export', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('admin-mutation', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('mutation', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()?->id ?? $request->ip()));
        RateLimiter::for('public-ticket-submissions', fn (Request $request): Limit => Limit::perMinutes(15, 5)
            ->by('public-ticket-ip:'.$request->ip()));
        RateLimiter::for('public-ticket-tracking', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('public-ticket-tracking-ip:'.$request->ip()));
        RateLimiter::for('public-history-challenge', function (Request $request): array {
            $fingerprint = app(PublicRequestHistoryService::class)->rateFingerprint(
                strtolower(trim((string) $request->input('email'))).'|'.(string) $request->input('branch_id')
            );

            return [Limit::perMinute(10)->by('phc-ip:'.$request->ip()), Limit::perMinutes(15, 5)->by('phc-id:'.$fingerprint)];
        });
        RateLimiter::for('public-history-verify', fn (Request $request): array => [
            Limit::perMinute(20)->by('phv-ip:'.$request->ip()),
            Limit::perMinute(10)->by('phv-ch:'.hash('sha256', (string) $request->route('challengeToken'))),
        ]);
        RateLimiter::for('public-history-access', fn (Request $request): array => [
            Limit::perMinute(60)->by('pha-ip:'.$request->ip()),
            Limit::perMinute(60)->by('pha-token:'.hash('sha256', (string) $request->bearerToken())),
        ]);

        Gate::before(function ($user, $ability) {
            if ((Str::startsWith($ability, 'report.') || Str::startsWith($ability, 'notification.') || Str::startsWith($ability, 'alert.') || Str::startsWith($ability, 'sla_escalation_policy.') || Str::startsWith($ability, 'knowledge_base.') || Str::startsWith($ability, 'workflow.')) && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });
    }
}
