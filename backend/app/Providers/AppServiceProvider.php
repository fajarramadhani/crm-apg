<?php

namespace App\Providers;

use App\Contracts\PublicHistoryOtpDelivery;
use App\Contracts\WhatsAppGateway;
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
use App\Listeners\WhatsAppNotificationSubscriber;
use App\Services\InMemoryPublicHistoryOtpDelivery;
use App\Services\MailPublicHistoryOtpDelivery;
use App\Services\PublicRequestHistoryService;
use App\Services\PublicTicketActionService;
use App\Services\PublicTicketTrackingKeyRing;
use App\Services\WhatsApp\FonnteWhatsAppGateway;
use App\Services\WhatsAppNotificationService;
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

        $this->app->bind(WhatsAppGateway::class, function () {
            $provider = config('whatsapp.provider');
            if ($provider !== 'fonnte') {
                throw new \InvalidArgumentException("Unsupported WhatsApp provider '{$provider}'.");
            }

            return new FonnteWhatsAppGateway(config('whatsapp.fonnte'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            $unsafe = config('app.debug')
                || blank(config('app.key'))
                || ! config('session.secure')
                || Str::contains((string) config('app.url'), ['localhost', '127.0.0.1'])
                || config('public_history.driver') !== 'mail'
                || in_array(config('mail.default'), ['log', 'array'], true)
                || $historySecrets->contains(fn ($secret) => ! is_string($secret) || strlen($secret) < 32)
                || $historyDurations->contains(fn (string $key) => ! is_int(config("public_history.{$key}")) || config("public_history.{$key}") < 1);

            if ($unsafe) {
                throw new \RuntimeException('Unsafe production environment configuration.');
            }

            if (config('whatsapp.enabled')) {
                if (config('whatsapp.provider') !== 'fonnte'
                    || blank(config('whatsapp.fonnte.token'))
                    || strlen((string) config('whatsapp.fonnte.webhook_secret')) < 32
                    || WhatsAppNotificationService::normalizePhoneNumber(config('whatsapp.fonnte.it_support_number')) === null) {
                    throw new \RuntimeException('WhatsApp enabled in production but mandatory Fonnte configuration is missing.');
                }
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
        Event::subscribe(WhatsAppNotificationSubscriber::class);

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
            Limit::perMinute(10)->by('phv-ch:'.hash('sha256', (string) $request->input('challenge_token'))),
        ]);
        RateLimiter::for('public-history-access', fn (Request $request): array => [
            Limit::perMinute(60)->by('pha-ip:'.$request->ip()),
            Limit::perMinute(60)->by('pha-token:'.hash('sha256', (string) $request->bearerToken())),
        ]);
        RateLimiter::for('public-ticket-action-challenge', function (Request $request): array {
            $trackingHash = hash('sha256', (string) $request->route('token'));
            $identity = app(PublicTicketActionService::class)->rateFingerprint(strtolower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute(10)->by('pta-ch-ip:'.$request->ip()),
                Limit::perMinutes(15, 5)->by('pta-ch-track:'.$trackingHash),
                Limit::perMinutes(15, 5)->by('pta-ch-id:'.$identity),
            ];
        });
        RateLimiter::for('public-ticket-action-verify', fn (Request $request): array => [
            Limit::perMinute(20)->by('pta-v-ip:'.$request->ip()),
            Limit::perMinute(10)->by('pta-v-track:'.hash('sha256', (string) $request->route('token'))),
            Limit::perMinute(10)->by('pta-v-ch:'.hash('sha256', (string) $request->input('challenge_token'))),
        ]);
        RateLimiter::for('public-ticket-actions', fn (Request $request): array => [
            Limit::perMinute(30)->by('pta-ip:'.$request->ip()),
            Limit::perMinute(30)->by('pta-track:'.hash('sha256', (string) $request->route('token'))),
            Limit::perMinute(30)->by('pta-access:'.hash('sha256', (string) ($request->header('X-Public-Action-Token') ?: $request->bearerToken()))),
        ]);

        Gate::before(function ($user, $ability) {
            if ((Str::startsWith($ability, 'report.') || Str::startsWith($ability, 'notification.') || Str::startsWith($ability, 'alert.') || Str::startsWith($ability, 'sla_escalation_policy.') || Str::startsWith($ability, 'knowledge_base.') || Str::startsWith($ability, 'workflow.')) && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });
    }
}
