<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Ops\ProductionHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionHealthReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_flags_http_app_url_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.key', 'base64:test-key');
        Config::set('app.url', 'http://brio.test');
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'database');
        Config::set('mail.default', 'smtp');

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $httpsCheck = collect($report['checks'])->firstWhere('label', 'APP URL en HTTPS en production');

        $this->assertNotNull($httpsCheck);
        $this->assertFalse($httpsCheck['ok']);
        $this->assertSame('ERROR', $httpsCheck['severity']);
    }

    /** LE GABARIT DE CLÉ PORTE LE BON PRÉFIXE, et c'est tout le piège. */
    public function test_le_gabarit_de_cle_stripe_est_signale(): void
    {
        Config::set('cashier.secret', 'sk_test_xxx');

        $report = app(ProductionHealthReport::class)->build();
        $check = collect($report['checks'])->firstWhere('label', 'Clé Stripe exploitable');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok'], 'Une clé de 11 caractères ne peut pas encaisser.');
        $this->assertSame('ERROR', $check['severity']);
        $this->assertStringContainsString('gabarit', $report['metrics']['stripe_key_state']);
    }

    public function test_une_vraie_cle_passe_le_controle(): void
    {
        Config::set('cashier.secret', 'sk_test_'.str_repeat('a', 90));

        $report = app(ProductionHealthReport::class)->build();
        $check = collect($report['checks'])->firstWhere('label', 'Clé Stripe exploitable');

        $this->assertTrue($check['ok']);
        $this->assertSame('définie', $report['metrics']['stripe_key_state']);
    }

    /** Une clé de TEST en production encaisse dans le vide. */
    public function test_une_cle_de_test_est_refusee_en_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('cashier.secret', 'sk_test_'.str_repeat('a', 90));

        $report = app(ProductionHealthReport::class)->build();
        $check = collect($report['checks'])->firstWhere('label', 'Clé Stripe non-test en production');

        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }

    /** SANS PRESTATAIRE ENCAISSABLE, AUCUNE RÉSERVATION NE PEUT ÊTRE PAYÉE. */
    public function test_aucun_prestataire_encaissable_est_une_erreur(): void
    {
        $report = app(ProductionHealthReport::class)->build();
        $check = collect($report['checks'])->firstWhere('label', 'Au moins un prestataire encaissable');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
        $this->assertSame(0, $report['metrics']['stripe_payable_providers']);
    }

    /** Un identifiant de compte NE SUFFIT PAS : il naît dès le premier écran du parcours Stripe. */
    public function test_un_compte_connect_non_actif_ne_compte_pas(): void
    {
        User::factory()->create([
            'role' => 'employe',
            'stripe_connect_account_id' => 'acct_en_cours',
            'stripe_connect_status' => 'pending',
        ]);

        $report = app(ProductionHealthReport::class)->build();

        $this->assertSame(0, $report['metrics']['stripe_payable_providers']);

        User::factory()->create([
            'role' => 'employe',
            'stripe_connect_account_id' => 'acct_pret',
            'stripe_connect_status' => 'active',
        ]);

        $report = app(ProductionHealthReport::class)->build();

        $this->assertSame(1, $report['metrics']['stripe_payable_providers']);
    }

    public function test_report_flags_mock_providers_as_error(): void
    {
        Config::set('kyc.default_provider', 'mock');

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Aucun provider en mode mock');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }

    public function test_report_flags_disabled_backups_as_error(): void
    {
        Config::set('operations.backups.enabled', false);

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Backups activés et configurés');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }

    public function test_report_flags_stale_heartbeat_as_error(): void
    {
        Config::set('operations.monitoring.heartbeat_enabled', true);
        Cache::forget(config('operations.monitoring.heartbeat_cache_key', 'brio:ops:heartbeat'));

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Heartbeat récent');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }
}
