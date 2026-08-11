<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\OrganizationSiteBudget;
use App\Services\Enterprise\InternalApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
         */
        $existante = Booking::query()
            ->where('customer_organization_id', $societe->id)
            ->where('status', InternalApprovalService::STATUT_EN_ATTENTE)
            ->exists();

        if (! $existante) {
            $aRequalifier = Booking::query()
                ->where('customer_organization_id', $societe->id)
                ->whereNotIn('status', ['annule', 'cancelled', 'completed', 'termine'])
                ->orderByDesc('id')
                ->first();

            if ($aRequalifier) {
                $aRequalifier->forceFill([
                    'status' => InternalApprovalService::STATUT_EN_ATTENTE,
                ])->save();
            } else {
                /*
                 * AUCUNE RÉSERVATION REQUALIFIABLE — le cas réel sur une base fraîche, où la seule
                 * réservation de la société est annulée. On en CRÉE une plutôt que de requalifier
                 * une annulation : ressusciter une annulation pour remplir un écran donnerait une
                 * base de démonstration qui ment sur ce qui s'est passé.
                 */
                $demandeur = DB::table('organization_members')
                    ->where('organization_account_id', $societe->id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->value('user_id');

                if ($demandeur) {
                    $demande = Booking::factory()->create([
                        'client_id' => $demandeur,
                        'customer_user_id' => $demandeur,
                        'customer_organization_id' => $societe->id,
                        'organization_site_id' => $local?->id,
                        'scheduled_at' => Carbon::now()->addDays(4)->setTime(9, 0),
                        'devis_estime' => 180.0,
                    ]);

                    /*
                     * LE STATUT SE POSE APRÈS LA CRÉATION, et pas dans la fabrique : le cycle de vie
                     * d'une réservation normalise le statut à l'écriture, si bien qu'un
                     * `pending_approval` passé en attribut ressortait en `pending`. Le seeder
                     * paraissait fonctionner et ne peuplait rien.
                     */
                    $demande->forceFill([
                        'status' => InternalApprovalService::STATUT_EN_ATTENTE,
                    ])->save();
                }
            }
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
