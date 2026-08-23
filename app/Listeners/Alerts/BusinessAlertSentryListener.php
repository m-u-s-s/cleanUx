<?php

namespace App\Listeners\Alerts;

use App\Events\BusinessAlertRaised;
use Sentry\Severity;
use Sentry\State\Scope;

/** Forwards business-event alerts (payment/payout/reconciliation/etc. */
class BusinessAlertSentryListener
{
    public function handle(BusinessAlertRaised $event): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        $level = $event->level === 'critical' ? Severity::fatal() : Severity::error();

        app('sentry')->withScope(function (Scope $scope) use ($event, $level): void {
            $scope->setContext('alert', $event->context);
            $scope->setTag('alert_key', $event->key);
            app('sentry')->captureMessage("[{$event->key}] {$event->message}", $level);
        });
    }
}
