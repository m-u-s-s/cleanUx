<?php

namespace App\Providers;

use App\Events\BusinessAlertRaised;
use App\Events\Disputes\DisputeOpened;
use App\Events\Kyc\KycCompleted;
use App\Events\Kyc\KycRejected;
use App\Events\Rating\RatingSubmitted;
use App\Listeners\Alerts\BusinessAlertSentryListener;
use App\Listeners\Disputes\NotifyOnDisputeOpened;
use App\Listeners\Kyc\EmitKycApprovedWebhook;
use App\Listeners\Kyc\ReevaluateProviderApproval;
use App\Listeners\LogNotificationMailFailed;
use App\Listeners\LogNotificationMailSent;
use App\Listeners\Rating\NotifyProviderOnRating;
use App\Services\Analytics\AnalyticsAutoTracker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        BusinessAlertRaised::class => [
            BusinessAlertSentryListener::class,
        ],
        NotificationSent::class => [
            LogNotificationMailSent::class,
        ],
        NotificationFailed::class => [
            LogNotificationMailFailed::class,
        ],
        KycCompleted::class => [
            EmitKycApprovedWebhook::class,
            ReevaluateProviderApproval::class,
        ],
        // Un refus d'identite n'annule pas l'inscription : il l'oriente vers un examen humain.
        KycRejected::class => [
            ReevaluateProviderApproval::class,
        ],
        DisputeOpened::class => [
            NotifyOnDisputeOpened::class,
        ],
        RatingSubmitted::class => [
            NotifyProviderOnRating::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        AnalyticsAutoTracker::class,
    ];

    /** Register any events for your application. */
    public function boot(): void
    {
        //
    }

    /** Determine if events and listeners should be automatically discovered. */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
