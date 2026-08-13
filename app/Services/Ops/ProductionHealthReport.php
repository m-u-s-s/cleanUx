<?php

namespace App\Services\Ops;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionHealthReport
{
    public function __construct(
        protected Filesystem $files,
        protected CacheRepository $cache,
        protected ConnectionInterface $db,
    ) {}

    /**
     * @return array{checks: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function build(): array
    {
        $appEnv = (string) config('app.env', 'production');
        $isProduction = $appEnv === 'production';
        $appUrl = (string) config('app.url', '');
        $queueDefault = (string) config('queue.default');
        $cacheDefault = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $mailDefault = (string) config('mail.default');
        $checks = [];

        $heartbeat = $this->heartbeatSnapshot();
        $jobsTableExists = $this->safeHasTable('jobs');
        $failedJobsTableExists = $this->safeHasTable('failed_jobs');
        $sessionsTableExists = $this->safeHasTable('sessions');
        $cacheTableExists = $this->safeHasTable('cache');
        $queueBacklog = $jobsTableExists ? $this->safeTableCount('jobs') : null;
        $failedJobsCount = $failedJobsTableExists ? $this->safeTableCount('failed_jobs') : null;
        $queueBacklogThreshold = (int) config('operations.monitoring.queue_backlog_warning_threshold', 50);
        $failedJobsThreshold = (int) config('operations.monitoring.failed_jobs_warning_threshold', 1);

        $this->pushCheck($checks, 'APP key définie', filled(config('app.key')), 'error', filled(config('app.key')) ? 'OK' : 'Missing');
        $this->pushCheck($checks, 'APP debug désactivé en production', ! $isProduction || ! (bool) config('app.debug'), 'error', config('app.debug') ? 'true' : 'false');
        $this->pushCheck($checks, 'APP URL en HTTPS en production', ! $isProduction || ! config('operations.deployment.require_https_app_url', true) || str_starts_with($appUrl, 'https://'), 'error', $appUrl ?: 'missing');
        $this->pushCheck($checks, 'Queue non synchrone en production', ! $isProduction || $queueDefault !== 'sync', 'error', $queueDefault);
        $this->pushCheck($checks, 'Jobs queue présents si driver database', $queueDefault !== 'database' || $jobsTableExists, 'error', $jobsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Failed jobs présents si queue asynchrone', in_array($queueDefault, ['sync', 'null'], true) || $failedJobsTableExists, 'warning', $failedJobsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Backlog queue sous seuil', $queueBacklog === null || $queueBacklog <= $queueBacklogThreshold, 'warning', $queueBacklog === null ? 'n/a' : (string) $queueBacklog);
        $this->pushCheck($checks, 'Failed jobs sous seuil', $failedJobsCount === null || $failedJobsCount <= $failedJobsThreshold, 'warning', $failedJobsCount === null ? 'n/a' : (string) $failedJobsCount);
        $this->pushCheck($checks, 'Cache store adapté à la prod', ! $isProduction || ! in_array($cacheDefault, ['array', 'null'], true), 'warning', $cacheDefault);
        $this->pushCheck($checks, 'Cache table présente si driver database', $cacheDefault !== 'database' || $cacheTableExists, 'warning', $cacheTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Session driver adapté à la prod', ! $isProduction || ! in_array($sessionDriver, ['array'], true), 'warning', $sessionDriver);
        $this->pushCheck($checks, 'Sessions table présente si driver database', $sessionDriver !== 'database' || $sessionsTableExists, 'warning', $sessionsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Cookie de session sécurisé en production HTTPS', ! $isProduction || ! str_starts_with($appUrl, 'https://') || config('session.secure') !== false, 'warning', var_export(config('session.secure'), true));
        $this->pushCheck($checks, 'Mailer configuré hors array/log en production', ! $isProduction || ! in_array($mailDefault, ['array', 'log'], true), 'warning', $mailDefault);
        $this->pushCheck($checks, 'Storage public lié', $this->storageLinkExists(), 'warning', $this->storageLinkExists() ? 'linked' : 'missing');
        $this->pushCheck($checks, 'Storage et bootstrap cache inscriptibles', $this->writablePathsOk(), 'warning', $this->writablePathsOk() ? 'writable' : 'check permissions');
        $this->pushCheck($checks, 'Heartbeat monitoring activé', (bool) config('operations.monitoring.heartbeat_enabled', true), 'warning', config('operations.monitoring.heartbeat_enabled') ? 'enabled' : 'disabled');
        $this->pushCheck($checks, 'Heartbeat récent', ! config('operations.monitoring.heartbeat_enabled', true) || ($heartbeat['exists'] && ($heartbeat['age_seconds'] ?? PHP_INT_MAX) <= (int) config('operations.monitoring.heartbeat_max_age_seconds', 900)), 'error', $heartbeat['exists'] ? (string) ($heartbeat['age_seconds'] ?? 'unknown') : 'missing');
        $this->pushCheck($checks, 'Email de monitoring configuré', ! config('operations.monitoring.heartbeat_enabled', true) || filled(config('operations.monitoring.notify_email')), 'warning', (string) (config('operations.monitoring.notify_email') ?: 'missing'));
        $this->pushCheck($checks, 'Backups activés et configurés', (bool) config('operations.backups.enabled', false) && $this->backupConfigOk(), 'error', config('operations.backups.enabled') ? 'configured' : 'disabled');
        $this->pushCheck($checks, 'Rétention backups positive', ! config('operations.backups.enabled', false) || (int) config('operations.backups.retention_days', 0) > 0, 'error', (string) config('operations.backups.retention_days', 0));

        $pending = $this->pendingMigrationsCount();
        $this->pushCheck($checks, 'Toutes les migrations appliquées', $pending === null || $pending === 0, 'error', $pending === null ? 'unknown' : (string) $pending);

        $mock = $this->mockProviders();
        $this->pushCheck($checks, 'Aucun provider en mode mock', count($mock) === 0, 'error', count($mock) === 0 ? 'none' : implode(', ', $mock));

        /*
         * LE CHEMIN DE L'ARGENT, QUE CE RAPPORT NE REGARDAIT PAS.
         *
         * Il vérifiait que le SMS ou le KYC ne sont pas en mode simulé, mais rien sur Stripe : une
         * plateforme pouvait donc être déclarée prête tout en étant incapable d'encaisser un seul
         * euro. Constaté le 2026-08-13 sur la base de démonstration — clé de gabarit, aucun
         * prestataire onboardé, zéro empreinte bancaire sur huit réservations, zéro versement,
         * zéro écriture de portefeuille. Rien ne le signalait.
         *
         * Les quatre conditions ci-dessous sont celles SANS LESQUELLES AUCUN PAIEMENT N'EST
         * POSSIBLE, et chacune échoue aujourd'hui en silence :
         *   — sans clé exploitable, `PaymentIntent::create` lève une erreur d'authentification ;
         *   — une clé de test en production encaisse dans le vide ;
         *   — sans secret de webhook, aucune capture ni aucun remboursement ne revient jamais ;
         *   — sans prestataire encaissable, `authorize()` refuse toute réservation, la plateforme
         *     ne pouvant pas reverser ce qu'elle prélèverait.
         */
        $stripe = $this->stripeSnapshot();
        $webhookSecret = filled(config('cashier.webhook.secret'));

        $this->pushCheck($checks, 'Clé Stripe exploitable', $stripe['cle_exploitable'], 'error', $stripe['cle_etat']);
        $this->pushCheck($checks, 'Clé Stripe non-test en production', ! $isProduction || ! $stripe['cle_test'], 'error', $stripe['cle_test'] ? 'clé de TEST' : 'OK');
        $this->pushCheck($checks, 'Secret de webhook Stripe défini', $webhookSecret, 'error', $webhookSecret ? 'OK' : 'missing');
        $this->pushCheck($checks, 'Au moins un prestataire encaissable', ($stripe['prestataires_encaissables'] ?? 0) > 0, 'error', $stripe['prestataires_encaissables'] === null ? 'unknown' : (string) $stripe['prestataires_encaissables']);

        $metrics = [
            'app_env' => $appEnv,
            'app_url' => $appUrl,
            'queue' => $queueDefault,
            'queue_backlog' => $queueBacklog,
            'failed_jobs_count' => $failedJobsCount,
            'cache' => $cacheDefault,
            'session' => $sessionDriver,
            'mail' => $mailDefault,
            'filesystem' => (string) config('filesystems.default'),
            'heartbeat_cache_key' => (string) config('operations.monitoring.heartbeat_cache_key'),
            'heartbeat_age_seconds' => $heartbeat['age_seconds'],
            'heartbeat_source' => $heartbeat['source'],
            'backups_enabled' => (bool) config('operations.backups.enabled', false),
            'pending_migrations' => $pending,
            'mock_providers' => $mock,
            'mock_providers_count' => count($mock),
            // JAMAIS LA CLÉ ELLE-MÊME : ces métriques partent en journal et en supervision. On
            // expose son ÉTAT, ce qui suffit à diagnostiquer sans jamais divulguer un secret.
            'stripe_key_state' => $stripe['cle_etat'],
            'stripe_key_is_test' => $stripe['cle_test'],
            'stripe_webhook_secret' => $webhookSecret,
            'stripe_payable_providers' => $stripe['prestataires_encaissables'],
        ];

        return [
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    public function errorCount(array $report): int
    {
        return collect($report['checks'] ?? [])->where('severity', 'ERROR')->where('ok', false)->count();
    }

    public function warningCount(array $report): int
    {
        return collect($report['checks'] ?? [])->where('severity', 'WARNING')->where('ok', false)->count();
    }

    protected function pushCheck(array &$checks, string $label, bool $ok, string $severity, mixed $value = null): void
    {
        $checks[] = [
            'status' => $ok ? 'OK' : 'FAIL',
            'ok' => $ok,
            'severity' => strtoupper($severity),
            'label' => $label,
            'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
        ];
    }

    protected function storageLinkExists(): bool
    {
        $publicStorage = public_path('storage');
        $expectedStorage = storage_path('app/public');

        if (! file_exists($publicStorage) || ! file_exists($expectedStorage)) {
            return false;
        }

        $publicRealPath = realpath($publicStorage);
        $expectedRealPath = realpath($expectedStorage);

        return $publicRealPath !== false
            && $expectedRealPath !== false
            && $publicRealPath === $expectedRealPath;
    }

    protected function writablePathsOk(): bool
    {
        return is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
    }

    protected function backupConfigOk(): bool
    {
        if (! config('operations.backups.enabled', false)) {
            return true;
        }

        $disk = (string) config('operations.backups.disk', 'local');
        $path = trim((string) config('operations.backups.path', 'backups'));

        if ($path === '') {
            return false;
        }

        return Arr::has(config('filesystems.disks', []), $disk);
    }

    /**
     * L'état du chemin de l'argent, sans jamais appeler Stripe.
     *
     * Un rapport de santé doit pouvoir être lu quand le réseau est coupé ou la clé absente —
     * c'est-à-dire précisément dans les situations qu'il sert à détecter.
     *
     * @return array<string, mixed>
     */
    protected function stripeSnapshot(): array
    {
        $cle = (string) config('cashier.secret');

        /*
         * LE PRÉFIXE NE SUFFIT PAS, et c'est tout l'intérêt de ce contrôle. Le gabarit livré par
         * `.env.example` porte le bon préfixe `sk_test_` : ne vérifier que lui laisserait passer
         * une plateforme incapable d'encaisser. Une vraie clé Stripe dépasse la centaine de
         * caractères ; le seuil est volontairement bas pour ne jamais rejeter une clé valide.
         */
        $cleExploitable = strlen($cle) >= 40
            && (str_starts_with($cle, 'sk_') || str_starts_with($cle, 'rk_'));

        return [
            'cle_exploitable' => $cleExploitable,
            'cle_test' => str_starts_with($cle, 'sk_test_') || str_starts_with($cle, 'rk_test_'),
            'cle_etat' => match (true) {
                $cle === '' => 'absente',
                ! $cleExploitable => 'gabarit ('.strlen($cle).' caractères)',
                default => 'définie',
            },
            'prestataires_encaissables' => $this->prestatairesEncaissables(),
        ];
    }

    /**
     * Combien de prestataires peuvent RÉELLEMENT recevoir des fonds.
     *
     * Même contrat que `User::canReceiveStripeConnectPayments()` : un identifiant de compte NE
     * SUFFIT PAS, il naît dès le premier écran du parcours Stripe. Seul le statut `active`
     * atteste qu'un virement aboutira.
     *
     * Le compte est fait sur les deux porteurs possibles — l'utilisateur et son profil — sans
     * doublon, un même prestataire pouvant renseigner l'un, l'autre, ou les deux.
     */
    protected function prestatairesEncaissables(): ?int
    {
        try {
            $identifiants = [];

            foreach (['users' => 'id', 'provider_profiles' => 'user_id'] as $table => $colonne) {
                if (! $this->safeHasTable($table) || ! Schema::hasColumn($table, 'stripe_connect_account_id')) {
                    continue;
                }

                $identifiants = array_merge($identifiants, $this->db->table($table)
                    ->whereNotNull('stripe_connect_account_id')
                    ->where('stripe_connect_status', 'active')
                    ->pluck($colonne)
                    ->all());
            }

            return count(array_unique(array_filter($identifiants)));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    protected function mockProviders(): array
    {
        $keys = [
            'KYC' => 'kyc.default_provider',
            'KYB identity' => 'kyb_v2.identity_provider',
            'KYB VAT' => 'kyb_v2.vat_provider',
            'KYB sanctions' => 'kyb_v2.sanctions_provider',
            'SMS' => 'sms.default_provider',
            'Push' => 'push.default_provider',
            'Insurance' => 'insurance.default_provider',
            'FX' => 'fx.default_provider',
            'Geolocation' => 'geolocation_v2.provider',
            'Email v2' => 'email_v2.provider',
            'Masked calls' => 'masked_calls.provider',
        ];
        $mock = [];
        foreach ($keys as $label => $key) {
            if (strtolower((string) config($key)) === 'mock') {
                $mock[] = $label;
            }
        }

        return $mock;
    }

    protected function pendingMigrationsCount(): ?int
    {
        try {
            $migrator = app('migrator');
            if (! $migrator->getRepository()->repositoryExists()) {
                return null;
            }
            $paths = array_merge([database_path('migrations')], $migrator->paths());
            $files = $migrator->getMigrationFiles($paths);
            $ran = $migrator->getRepository()->getRan();

            return count(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return null;
        }
    }

    protected function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function safeTableCount(string $table): ?int
    {
        try {
            return (int) $this->db->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{exists: bool, age_seconds: ?int, source: string}
     */
    protected function heartbeatSnapshot(): array
    {
        $cacheKey = (string) config('operations.monitoring.heartbeat_cache_key', 'brio:ops:heartbeat');
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && ! empty($cached['at'])) {
            return [
                'exists' => true,
                'age_seconds' => Carbon::parse($cached['at'])->diffInSeconds(now()),
                'source' => 'cache',
            ];
        }

        $disk = (string) config('operations.monitoring.heartbeat_disk', 'local');
        $path = (string) config('operations.monitoring.heartbeat_path', 'ops/heartbeat.json');

        if (Storage::disk($disk)->exists($path)) {
            $payload = json_decode((string) Storage::disk($disk)->get($path), true);

            if (is_array($payload) && ! empty($payload['at'])) {
                return [
                    'exists' => true,
                    'age_seconds' => Carbon::parse($payload['at'])->diffInSeconds(now()),
                    'source' => 'disk',
                ];
            }

            return [
                'exists' => true,
                'age_seconds' => null,
                'source' => 'disk',
            ];
        }

        return [
            'exists' => false,
            'age_seconds' => null,
            'source' => 'missing',
        ];
    }
}
