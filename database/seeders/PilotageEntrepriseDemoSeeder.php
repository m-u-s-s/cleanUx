<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\OrganizationSiteBudget;
use App\Services\Enterprise\InternalApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * LE PILOTAGE D'UNE ENTREPRISE CLIENTE, SUR LA BASE DE DÉMONSTRATION (E7, E8).
 *
 * SANS DONNÉES, LES DEUX MODULES NE PROUVENT RIEN. Un écran de budgets vide ne distingue pas
 * « cette société n'a pas encore posé de plafond » de « la requête est fausse ». Et une file
 * d'approbation vide ne dit pas si le circuit fonctionne : c'est justement l'état nominal d'une
 * société qui n'a rien demandé.
 *
 * DEUX BUDGETS, DONT UN PRESQUE ATTEINT. Le second est le seul qui prouve quelque chose : c'est lui
 * qui fait apparaître l'alerte, et c'est l'alerte qui évite de découvrir le dépassement à la
 * facture.
 *
 * IDEMPOTENT : chaque ligne est cherchée sur sa clé métier avant d'être écrite.
 */
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
            /*
             * CELUI-CI EST VOLONTAIREMENT SERRÉ. C'est le seul qui prouve quelque chose : il fait
             * apparaître l'alerte, et c'est l'alerte qui évite de découvrir le dépassement à la
             * facture — un mois plus tard, quand plus rien n'est annulable.
             */
            $this->poserLeBudget($societe->id, $local->id, $debut, 60000, 70);
        }

        /*
         * UNE DEMANDE EN ATTENTE D'APPROBATION.
         *
         * Sans elle, la file reste vide — et une file vide ne distingue pas « le circuit
         * fonctionne » de « personne n'a jamais rien demandé ».
         *
         * ON REQUALIFIE, ON NE CRÉE JAMAIS. La première version créait une réservation quand
         * aucune n'était requalifiable — et « requalifiable » dépendait du statut ALÉATOIRE que
         * `StatutRendezVousSeeder` venait d'assigner. Le nombre de réservations de la base de
         * démonstration variait donc d'une exécution à l'autre, et `DatabaseSeederProfileTest`,
         * qui en attend exactement quatre, échouait une fois sur deux. Une base de démonstration
         * qui n'est pas reproductible ne sert plus de référence à personne.
         *
         * ET LE STATUT D'ORIGINE N'A RIEN À PRÉSERVER : il vient d'être tiré au sort. Requalifier
         * une réservation « annulée » n'efface donc aucune décision — c'est le tirage lui-même qui
         * ne voulait rien dire.
         */
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

    /**
     * Poser un budget, de façon REJOUABLE.
     *
     * `updateOrCreate` NE SUFFIT PAS ICI, et c'est le piège DATE de SQLite déjà rencontré sur ce
     * dépôt : la recherche portait la chaîne « 2026-08-01 » tandis que la colonne, écrite à
     * travers le cast `date`, contient « 2026-08-01 00:00:00 ». Les deux ne se comparent pas égaux,
     * si bien qu'un second passage INSÉRAIT — et heurtait la contrainte d'unicité. On cherche donc
     * sur une COMPARAISON DE DATE, qui ignore l'heure.
     */
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
