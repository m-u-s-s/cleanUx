<?php

namespace App\Providers;

use App\Listeners\LogNotificationMailFailed;
use App\Listeners\LogNotificationMailSent;
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
        NotificationSent::class => [
            LogNotificationMailSent::class,
        ],
        NotificationFailed::class => [
            LogNotificationMailFailed::class,
        ],
        \App\Events\Kyc\KycCompleted::class => [
            \App\Listeners\Kyc\EmitKycApprovedWebhook::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        \App\Services\Analytics\AnalyticsAutoTracker::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
