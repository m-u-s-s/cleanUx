<?php

namespace App\Console\Commands;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditSeedIntegrity extends Command
{
    protected $signature = 'app:audit-seed-integrity';

    protected $description = 'Vérifie rapidement les doublons logiques et la cohérence relationnelle après un migrate:fresh --seed';

    public function handle(): int
    {
        $rows = [
            ['emails utilisateurs dupliqués', $this->countDuplicateRows('users', 'email')],
            ['booking_reference dupliqués', $this->countDuplicateRows('rendez_vous', 'booking_reference', true)],
            ['slugs comptes entreprise dupliqués', $this->countDuplicateRows('organization_accounts', 'slug', true)],
            ['TVA comptes entreprise dupliqués', $this->countDuplicateRows('organization_accounts', 'tva_number', true)],
            ['slugs zones dupliqués', $this->countDuplicateRows('service_zones', 'slug')],
            ['utilisateurs entreprise sans compte', User::query()->where('role', User::ROLE_ENTREPRISE)->whereNull('organization_account_id')->count()],
            ['utilisateurs avec zone primaire inexistante', User::query()->whereNotNull('primary_service_zone_id')->whereDoesntHave('primaryServiceZone')->count()],
            ['utilisateurs avec code postal inexistant', User::query()->whereNotNull('postal_code_id')->whereDoesntHave('postalCode')->count()],
            ['sites sans compte lié', OrganizationSite::query()->whereDoesntHave('organizationAccount')->count()],
            ['sites sans zone liée', OrganizationSite::query()->whereNotNull('service_zone_id')->whereDoesntHave('serviceZone')->count()],
            ['comptes sans slug', OrganizationAccount::query()->whereNull('slug')->count()],
            ['zones sans règles', ServiceZone::query()->doesntHave('zoneServiceRules')->count()],
            ['rendez-vous sans client', RendezVous::query()->whereDoesntHave('client')->count()],
            ['rendez-vous sans employé', RendezVous::query()->whereNotNull('employe_id')->whereDoesntHave('employe')->count()],
            ['rendez-vous sans snapshots', RendezVous::query()->where(fn ($q) => $q->whereNull('zone_snapshot')->orWhereNull('pricing_snapshot'))->count()],
        ];

        $this->table(['Check', 'Count'], $rows);

        $errors = collect($rows)->sum(fn (array $row) => (int) $row[1]);

        $this->newLine();

        if ($errors === 0) {
            $this->info('Audit seed OK : aucune incohérence bloquante détectée.');
            return self::SUCCESS;
        }

        $this->warn('Audit seed terminé avec incohérences détectées.');
        return self::FAILURE;
    }

    protected function countDuplicateRows(string $table, string $column, bool $ignoreNull = false): int
    {
        $query = DB::table($table)
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');

        if ($ignoreNull) {
            $query->whereNotNull($column);
        }

        return $query->count();
    }
}
