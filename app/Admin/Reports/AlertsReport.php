<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\BroadcastEvent;
use App\Models\EmailLog;
use App\Models\SmsMessage;
use App\Models\StripeWebhookEvent;
use App\Models\WebhookEndpoint;

/** Les alertes d’exploitation. */
class AlertsReport implements AdminReport
{
    public function key(): string
    {
        return 'alerts';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Défaillances',
                'tiles' => [
                    ReportTile::make(
                        'stripe_failed',
                        'Événements Stripe en échec',
                        fn () => StripeWebhookEvent::where('status', 'failed')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'webhooks_suspended',
                        'Webhooks suspendus',
                        fn () => WebhookEndpoint::where('is_suspended', true)->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'emails_failed',
                        'Emails en échec',
                        fn () => EmailLog::where('status', 'failed')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'sms_failed',
                        'SMS en échec',
                        fn () => SmsMessage::where('status', 'failed')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'broadcasts_failed',
                        'Diffusions en échec',
                        fn () => BroadcastEvent::where('status', 'failed')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
        ];
    }
}
