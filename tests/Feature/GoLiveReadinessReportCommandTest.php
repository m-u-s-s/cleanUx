<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GoLiveReadinessReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_succeeds_when_health_and_schedule_are_ready(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.key', 'base64:test-key');
        Config::set('app.url', 'https://brio.test');
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'database');
        Config::set('mail.default', 'smtp');

        Config::set('operations.backups.enabled', true);
        Config::set('operations.backups.disk', 'local');
        Config::set('operations.backups.path', 'backups');
        Config::set('operations.backups.retention_days', 7);

        Config::set('kyc.default_provider', 'onfido');
        Config::set('kyb_v2.identity_provider', 'insee');
        Config::set('kyb_v2.vat_provider', 'vies');
        Config::set('kyb_v2.sanctions_provider', 'opensanctions');
        Config::set('sms.default_provider', 'twilio');
        Config::set('push.default_provider', 'fcm');
        Config::set('insurance.default_provider', 'hiscox');
        Config::set('fx.default_provider', 'ecb');
        Config::set('geolocation_v2.provider', 'google');
        Config::set('email_v2.provider', 'ses');
        Config::set('masked_calls.provider', 'twilio');

        // Une mise en ligne qui ne peut pas encaisser n'est pas une mise en ligne : clé de
        // production, secret de webhook et prestataire encaissable font partie du contrat.
        Config::set('cashier.secret', 'sk_live_'.str_repeat('a', 90));
        Config::set('cashier.webhook.secret', 'whsec_'.str_repeat('b', 32));
        User::factory()->create([
            'role' => 'employe',
            'stripe_connect_account_id' => 'acct_pret',
            'stripe_connect_status' => 'active',
        ]);

        Config::set('operations.monitoring.heartbeat_enabled', true);
        Cache::put(
            config('operations.monitoring.heartbeat_cache_key', 'brio:ops:heartbeat'),
            ['at' => now()->toISOString()],
            3600
        );

        $this->artisan('app:go-live-readiness-report --strict')
            ->expectsOutput('Go-live readiness report OK.')
            ->assertExitCode(0);
    }
}
