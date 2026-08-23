<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Raised by BusinessAlerts whenever a money-path failure is detected. A listener (e.g. */
class BusinessAlertRaised
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $level  Severity level: 'critical' | 'error' | 'warning'
     * @param  string  $key  Machine-readable alert key, e.g. 'payment_capture_failed'
     * @param  string  $message  Human-readable summary for Sentry title
     * @param  array<string, mixed>  $context  Structured context (ids, amounts, etc.)
     */
    public function __construct(
        public readonly string $level,
        public readonly string $key,
        public readonly string $message,
        public readonly array $context = [],
    ) {}
}
