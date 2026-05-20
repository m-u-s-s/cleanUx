<?php

namespace App\Providers;

use App\Services\EmailV2\Contracts\EmailProviderContract;
use App\Services\EmailV2\Providers\MockEmailProvider;
use App\Services\EmailV2\Providers\SmtpEmailProvider;
use Illuminate\Support\ServiceProvider;

class EmailV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailProviderContract::class, function () {
            return match ((string) config('email_v2.provider', 'mock')) {
                'smtp', 'mailgun', 'ses', 'sendgrid' => new SmtpEmailProvider(),
                default => new MockEmailProvider(),
            };
        });
    }
}
