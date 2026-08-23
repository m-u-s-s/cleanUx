<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\OrganizationSiteBudget;
use App\Services\Enterprise\InternalApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** LE PILOTAGE D'UNE ENTREPRISE CLIENTE, SUR LA BASE DE DÉMONSTRATION (E7, E8). */
class PilotageEntrepriseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $societe = OrganizationAccount::query()
            ->where('type', 'client_company')
            ->orderBy('id')
            ->first();

        if (! $societe) {
            $this->command?->warn('⚠️ Aucune entreprise cliente : pilotage ignoré.');

            return;
        }

        $local = OrganizationSite::query()
            ->where('organization_account_id', $societe->id)
            ->orderBy('id')
            ->first();

        $debut = Carbon::now()->startOfMonth();

        // Le budget de TOUTE la société — celui que la plupart posent en premier.
        $this->poserLeBudget($societe->id, null, $debut, 800000, 80);

        if ($local) {
            // CELUI-CI EST VOLONTAIREMENT SERRÉ.
            $this->poserLeBudget($societe->id, $local->id, $debut, 60000, 70);
        }

        // UNE DEMANDE EN ATTENTE D'APPROBATION.
        $existante = Booking::query()
            ->where('customer_organization_id', $societe->id)
            ->where('metadata->demo_approval', true)
            ->exists();

        if (! $existante) {
            // La PLUS RÉCENTE, quel que soit son statut : un choix déterministe, qui donne la même
            // base à chaque exécution.
            $aRequalifier = Booking::query()
                ->where('customer_organization_id', $societe->id)
                ->orderByDesc('id')
                ->first();

            $aRequalifier?->forceFill([
                'status' => InternalApprovalService::STATUT_EN_ATTENTE,
                'organization_site_id' => $aRequalifier->organization_site_id ?? $local?->id,
                'scheduled_at' => Carbon::now()->addDays(4)->setTime(9, 0),
                'devis_estime' => 180.0,
                'metadata' => array_merge((array) $aRequalifier->metadata, ['demo_approval' => true]),
            ])->save();
        }

        $this->command?->info(sprintf(
            '✅ Pilotage entreprise : budgets et file d’approbation pour « %s ».',
            $societe->name ?? 'la société',
        ));
    }

    /** Poser un budget, de façon REJOUABLE. */
    private function poserLeBudget(
        int $organisationId,
        ?int $localId,
        Carbon $debut,
        int $plafondCents,
        int $seuil,
    ): void {
        $existant = OrganizationSiteBudget::query()
            ->where('organization_account_id', $organisationId)
            ->where('organization_site_id', $localId)
            ->where('period', OrganizationSiteBudget::PERIOD_MONTHLY)
            ->whereDate('period_start', $debut->toDateString())
            ->first();

        if ($existant) {
            $existant->forceFill([
                'limit_cents' => $plafondCents,
                'alert_threshold_percent' => $seuil,
            ])->save();

            return;
        }

        OrganizationSiteBudget::query()->create([
            'organization_account_id' => $organisationId,
            'organization_site_id' => $localId,
            'period' => OrganizationSiteBudget::PERIOD_MONTHLY,
            'period_start' => $debut->toDateString(),
            'limit_cents' => $plafondCents,
            'alert_threshold_percent' => $seuil,
        ]);
    }
}
