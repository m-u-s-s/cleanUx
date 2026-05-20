<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * Health check : détecte les Mock providers actifs en production.
 *
 * En `APP_ENV=production`, chaque module v2 (SMS, Push, KYC, Insurance, Geo, FX)
 * DOIT avoir un real provider configuré sinon les Mock kicks in silently.
 *
 * Usage :
 *   php artisan ops:check-providers
 *   php artisan ops:check-providers --strict   # exit 1 si mock détecté hors test
 */
class OpsCheckProvidersCommand extends Command
{
    protected $signature = 'ops:check-providers
                            {--strict : Sortir avec exit code 1 si un Mock provider est actif hors test}';

    protected $description = 'Audit les bindings de providers (SMS/Push/KYC/Insurance/Geo/FX). Detecte les Mock en prod.';

    /**
     * Map des providers checked : interface → liste des mock class names à interdire en prod.
     */
    protected array $providerChecks = [
        'sms' => [
            'interface' => \App\Services\Sms\SmsProviderInterface::class,
            'mock_classes' => [\App\Services\Sms\Providers\SmsMockProvider::class],
            'real_hint' => 'TWILIO_ACCOUNT_SID + TWILIO_AUTH_TOKEN',
        ],
        'push' => [
            'interface' => \App\Services\Push\PushProviderInterface::class,
            'mock_classes' => [\App\Services\Push\Providers\PushMockProvider::class],
            'real_hint' => 'FCM_SERVER_KEY ou APNS_KEY_PATH + APNS_TEAM_ID + APNS_KEY_ID',
        ],
        'kyc' => [
            'interface' => \App\Services\Kyc\KycProviderInterface::class,
            'mock_classes' => [\App\Services\Kyc\Providers\KycMockProvider::class],
            'real_hint' => 'ONFIDO_API_TOKEN ou VERIFF_API_KEY',
        ],
        'kyb_sanctions' => [
            'interface' => \App\Services\KybV2\Contracts\SanctionsScreeningProviderContract::class,
            'mock_classes' => [\App\Services\KybV2\Providers\MockSanctionsScreeningProvider::class],
            'real_hint' => 'COMPLY_ADVANTAGE_API_KEY ou DOW_JONES_API_KEY',
        ],
        'insurance' => [
            'interface' => \App\Services\Insurance\InsuranceProviderInterface::class,
            'mock_classes' => [\App\Services\Insurance\Providers\InsuranceMockProvider::class],
            'real_hint' => 'HISCOX_API_KEY ou WAKAM_API_KEY',
        ],
        'geocoding' => [
            'interface' => \App\Services\GeolocationV2\Contracts\GeocodingProviderContract::class,
            'mock_classes' => [\App\Services\GeolocationV2\Providers\MockGeocodingProvider::class],
            'real_hint' => 'MAPBOX_API_TOKEN ou GOOGLE_MAPS_API_KEY',
        ],
        'fx' => [
            'interface' => \App\Services\Fx\FxProviderInterface::class,
            'mock_classes' => [\App\Services\Fx\Providers\FxMockProvider::class],
            'real_hint' => 'OPENEXCHANGERATES_APP_ID ou ECB endpoint (gratuit)',
        ],
    ];

    public function handle(): int
    {
        $isProduction = App::environment('production');
        $strict = (bool) $this->option('strict');

        $this->info('🔍 Audit des providers actifs (env: ' . app()->environment() . ')');
        $this->newLine();

        $mockDetected = [];
        $missingInterfaces = [];

        foreach ($this->providerChecks as $name => $check) {
            $interface = $check['interface'];
            if (! interface_exists($interface) && ! class_exists($interface)) {
                $missingInterfaces[] = $name;
                $this->warn("  ⚠ {$name}: interface absente ({$interface})");
                continue;
            }

            try {
                $instance = app($interface);
                $class = get_class($instance);
                $isMock = false;
                foreach ($check['mock_classes'] as $mockClass) {
                    if ($class === $mockClass || is_subclass_of($instance, $mockClass)) {
                        $isMock = true;
                        break;
                    }
                }

                if ($isMock) {
                    $mockDetected[] = ['name' => $name, 'class' => $class, 'hint' => $check['real_hint']];
                    $this->error("  ✗ {$name}: MOCK ACTIF → {$class}");
                    $this->line("        Config requise pour real provider: {$check['real_hint']}");
                } else {
                    $this->line("  <fg=green>✓</> {$name}: {$class}");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$name}: erreur binding → {$e->getMessage()}");
                $mockDetected[] = ['name' => $name, 'class' => 'BINDING_ERROR', 'hint' => $check['real_hint']];
            }
        }

        $this->newLine();

        if (! empty($mockDetected)) {
            $this->error('⚠  ' . count($mockDetected) . ' Mock providers actifs / erreurs de binding.');

            if ($isProduction) {
                $this->error('🚨 ENV=production avec Mock providers — bloque le déploiement.');
                return 1;
            }
            if ($strict) {
                return 1;
            }
            $this->warn('   (env=' . app()->environment() . ' → toléré sans --strict)');
            return 0;
        }

        $this->info('✅ Tous les providers sont des implémentations réelles.');
        return 0;
    }
}
