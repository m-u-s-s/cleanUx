<?php

namespace App\Listeners\Alerts;

use App\Events\BusinessAlertRaised;
use Sentry\Severity;
use Sentry\State\Scope;

/**
 * Forwards business-event alerts (payment/payout/reconciliation/etc. failures)
 * to Sentry so money-path failures actually page someone. No-op when Sentry
 * isn't bound (e.g. local/test without the DSN configured).
 *
 * Sentry API used (sentry/sentry ^3.x):
 *   - Severity::fatal() / Severity::error()  — static factory methods
 *   - app('sentry')->withScope(callable)      — scoped context isolation
 *   - $scope->setContext(string, array)       — attach structured context
 *   - $scope->setTag(string, string)          — searchable tag
 *   - app('sentry')->captureMessage(string, Severity) — send the alert
 */
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
