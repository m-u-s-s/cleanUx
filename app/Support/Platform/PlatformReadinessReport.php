<?php

namespace App\Support\Platform;

use App\Models\Country;
use App\Models\EmployeeZoneAssignment;
use App\Models\Feedback;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use Illuminate\Support\Facades\DB;

class PlatformReadinessReport
{
    public function build(): array
    {
        $profile = $this->resolveProfile();

        $checks = collect([
            $this->makeCheck(
                key: 'missing_reference_countries',
                label: 'Aucun pays référencé',
                count: Country::query()->count() > 0 ? 0 : 1,
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'missing_reference_postal_codes',
                label: 'Aucun code postal référencé',
                count: PostalCode::query()->count() > 0 ? 0 : 1,
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'missing_reference_service_catalogs',
                label: 'Aucun service catalogue référencé',
                count: ServiceCatalog::query()->count() > 0 ? 0 : 1,
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'missing_reference_service_zones',
                label: 'Aucune zone de service référencée',
                count: ServiceZone::query()->count() > 0 ? 0 : 1,
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'entreprise_users_without_account',
                label: 'Utilisateurs entreprise sans compte organisation',
                count: User::query()->where('role', User::ROLE_ENTREPRISE)->whereNull('organization_account_id')->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'employees_without_active_zone_assignment',
                label: 'Employés sans affectation de zone active',
                count: User::query()
                    ->where('role', User::ROLE_EMPLOYE)
                    ->whereDoesntHave('zoneAssignments', fn ($query) => $query->where('is_active', true))
                    ->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'organization_sites_without_coverage',
                label: 'Sites entreprise sans code postal ou zone',
                count: OrganizationSite::query()
                    ->where(function ($query) {
                        $query->whereNull('postal_code_id')->orWhereNull('service_zone_id');
                    })
                    ->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'service_zones_without_rules',
                label: 'Zones sans règles de service',
                count: ServiceZone::query()->doesntHave('zoneServiceRules')->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'invalid_zone_rules',
                label: 'Règles zone/service orphelines',
                count: ZoneServiceRule::query()
                    ->whereDoesntHave('serviceZone')
                    ->orWhereDoesntHave('serviceCatalog')
                    ->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'rendezvous_missing_structured_refs',
                label: 'Rendez-vous sans références structurées',
                count: RendezVous::query()
                    ->where(function ($query) {
                        $query
                            ->whereNull('service_zone_id')
                            ->orWhereNull('postal_code_id')
                            ->orWhereNull('service_catalog_id');
                    })
                    ->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'rendezvous_missing_snapshots',
                label: 'Rendez-vous sans snapshots zone/pricing',
                count: RendezVous::query()
                    ->where(function ($query) {
                        $query->whereNull('zone_snapshot')->orWhereNull('pricing_snapshot');
                    })
                    ->count(),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'duplicate_user_emails',
                label: 'Emails utilisateur dupliqués',
                count: $this->countDuplicateGroups('users', 'email'),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'duplicate_user_tva_numbers',
                label: 'TVA utilisateurs dupliquées',
                count: $this->countDuplicateGroups('users', 'tva_number', true),
                severity: 'warning'
            ),
            $this->makeCheck(
                key: 'duplicate_org_slugs',
                label: 'Slugs organisations dupliqués',
                count: $this->countDuplicateGroups('organization_accounts', 'slug', true),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'duplicate_org_tva_numbers',
                label: 'TVA organisations dupliquées',
                count: $this->countDuplicateGroups('organization_accounts', 'tva_number', true),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'duplicate_zone_slugs',
                label: 'Slugs zones dupliqués',
                count: $this->countDuplicateGroups('service_zones', 'slug', true),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'duplicate_booking_references',
                label: 'Références booking dupliquées',
                count: $this->countDuplicateGroups('rendez_vous', 'booking_reference', true),
                severity: 'error'
            ),
            $this->makeCheck(
                key: 'missing_demo_admin',
                label: 'Admin démo principal absent',
                count: $profile === 'demo' && ! User::query()->where('email', 'admin@cleanux.test')->exists() ? 1 : 0,
                severity: 'warning'
            ),
            $this->makeCheck(
                key: 'no_feedbacks_seeded',
                label: 'Aucun feedback généré',
                count: $profile === 'demo' && ! Feedback::query()->exists() ? 1 : 0,
                severity: 'warning'
            ),
            $this->makeCheck(
                key: 'demo_artifacts_in_non_demo_profile',
                label: 'Données démo présentes hors profil demo',
                count: $profile === 'demo' ? 0 : $this->countDemoArtifacts(),
                severity: 'error'
            ),
        ]);

        $metrics = [
            'countries_total' => Country::query()->count(),
            'postal_codes_total' => PostalCode::query()->count(),
            'users_total' => User::query()->count(),
            'clients_total' => User::query()->whereIn('role', [User::ROLE_CLIENT, User::ROLE_ENTREPRISE])->count(),
            'employees_total' => User::query()->where('role', User::ROLE_EMPLOYE)->count(),
            'admins_total' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'organization_accounts_total' => OrganizationAccount::query()->count(),
            'organization_sites_total' => OrganizationSite::query()->count(),
            'service_zones_total' => ServiceZone::query()->count(),
            'zone_rules_total' => ZoneServiceRule::query()->count(),
            'service_catalogs_total' => ServiceCatalog::query()->count(),
            'employee_zone_assignments_total' => EmployeeZoneAssignment::query()->where('is_active', true)->count(),
            'rendezvous_total' => RendezVous::query()->count(),
            'feedbacks_total' => Feedback::query()->count(),
        ];

        $errorCount = $checks->where('severity', 'error')->sum('count');
        $warningCount = $checks->where('severity', 'warning')->sum('count');
        $referenceReady = $metrics['countries_total'] > 0
            && $metrics['postal_codes_total'] > 0
            && $metrics['service_zones_total'] > 0
            && $metrics['service_catalogs_total'] > 0;

        return [
            'profile' => $profile,
            'metrics' => $metrics,
            'checks' => $checks->values()->all(),
            'summary' => [
                'errors' => $errorCount,
                'warnings' => $warningCount,
                'blocking_issues' => $checks->where('severity', 'error')->where('count', '>', 0)->count(),
                'non_blocking_issues' => $checks->where('severity', 'warning')->where('count', '>', 0)->count(),
                'seed_ready' => $errorCount === 0,
                'reference_ready' => $referenceReady,
            ],
        ];
    }

    protected function resolveProfile(): string
    {
        $explicitProfile = config('cleanux.seed.profile');
        $defaultProfile = config('cleanux.seed.default_profile', app()->environment('production') ? 'production' : 'demo');

        return strtolower((string) ($explicitProfile ?: $defaultProfile));
    }

    protected function countDemoArtifacts(): int
    {
        $count = 0;

        $count += User::query()->where('email', 'like', '%@cleanux.test')->count();
        $count += OrganizationAccount::query()->where('email', 'like', '%@atlasfacilities.test')->count();
        $count += OrganizationSite::query()->where('email', 'like', '%@atlasfacilities.test')->count();
        $count += RendezVous::query()->count();
        $count += Feedback::query()->count();

        return $count;
    }

    protected function makeCheck(string $key, string $label, int $count, string $severity = 'error'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'severity' => $severity,
            'status' => $count === 0 ? 'ok' : ($severity === 'error' ? 'error' : 'warning'),
        ];
    }

    protected function countDuplicateGroups(string $table, string $column, bool $ignoreNull = false): int
    {
        $query = DB::table($table)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');

        if ($ignoreNull) {
            $query->whereNotNull($column)->where($column, '!=', '');
        }

        return DB::query()->fromSub($query, 'duplicates')->count();
    }
}
