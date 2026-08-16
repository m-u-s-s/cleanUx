<?php

namespace App\Console\Commands;

use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Availability\DefaultAvailabilityProvisioner;
use Illuminate\Console\Command;

/**
 * Le rattrapage des prestataires inscrits AVANT que le défaut existe.
 *
 * Ils sont sortis de l'inscription sans un seul créneau, donc invisibles à la planification. Cette
 * commande leur donne la même semaine que les nouveaux — et RIEN à ceux qui ont déjà choisi : le
 * provisionneur s'arrête au premier créneau existant, actif ou non. Un horaire délibérément fermé
 * n'est jamais rouvert dans le dos de son propriétaire.
 *
 * `--dry-run` d'abord : sur une base de production, savoir COMBIEN de comptes vont bouger avant de
 * les faire bouger n'est pas un luxe.
 */
class ProvisionDefaultAvailability extends Command
{
    protected $signature = 'availability:provision-defaults
                            {--dry-run : Compte les prestataires concernés sans rien écrire}
                            {--user= : Un seul compte, par identifiant ou courriel}';

    protected $description = 'Dote de la semaine par défaut (08:00–17:00, 7 jours) les prestataires sans aucun créneau.';

    public function handle(DefaultAvailabilityProvisioner $provisioner): int
    {
        $cible = $this->option('user');

        /*
         * LA SÉLECTION DOIT ÉPOUSER `isEmploye()`, PAS LE DEVINER.
         *
         * Un premier jet filtrait sur `whereHas('providerProfile')` seul. Or `isEmploye()` — le
         * test qu'applique le provisionneur — retient AUSSI la colonne héritée `role = 'employe'`
         * tant que tous les comptes n'ont pas de profil. Deux définitions de « prestataire » pour
         * une seule question : la commande annonçait « aucun prestataire à doter » pendant que le
         * service, lui, en aurait doté. Constaté au test.
         *
         * Le SQL présélectionne largement ; `provision()` reste le seul juge, et les comptes
         * écartés sont comptés puis signalés plus bas.
         */
        $requete = User::query()->where(function ($q) {
            $q->whereHas('providerProfile')->orWhere('role', 'employe');
        });

        if ($cible) {
            $requete->where(fn ($q) => $q->where('id', (int) $cible)->orWhere('email', $cible));
        }

        /*
         * Le filtre porte sur l'ABSENCE de créneau, pas sur une date d'inscription : c'est la
         * seule formulation qui reste juste si la commande est rejouée, ou si un prestataire
         * supprime tout et veut repartir du défaut.
         */
        $requete->whereNotExists(function ($sous) {
            $sous->selectRaw('1')
                ->from('availability_slots')
                ->whereColumn('availability_slots.provider_user_id', 'users.id');
        });

        $prestataires = $requete->get();

        if ($prestataires->isEmpty()) {
            $this->info('Aucun prestataire sans créneau. Rien à faire.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%d prestataire(s) sans aucun créneau :', $prestataires->count()));

        foreach ($prestataires as $prestataire) {
            $this->line(sprintf('  #%d  %s', $prestataire->id, $prestataire->email));
        }

        if ($this->option('dry-run')) {
            $this->comment(sprintf(
                'Essai à blanc — %d × %d créneaux seraient créés. Relancez sans --dry-run pour écrire.',
                $prestataires->count(),
                count(DefaultAvailabilityProvisioner::DEFAULT_WEEKDAYS),
            ));

            return self::SUCCESS;
        }

        $total = 0;
        $comptes = 0;

        foreach ($prestataires as $prestataire) {
            $crees = $provisioner->provision($prestataire);

            if ($crees > 0) {
                $total += $crees;
                $comptes++;
            }
        }

        $this->info(sprintf('%d créneaux créés pour %d prestataire(s).', $total, $comptes));

        /*
         * Le garde-fou de la mesure : si le compte de départ et le compte d'arrivée divergent,
         * c'est qu'un prestataire a été écarté silencieusement — un profil sans rôle `employe`,
         * par exemple. Le dire vaut mieux que de rendre un total qui a l'air complet.
         */
        if ($comptes !== $prestataires->count()) {
            $this->warn(sprintf(
                '%d compte(s) sélectionné(s) n\'ont rien reçu — vérifiez leur rôle prestataire.',
                $prestataires->count() - $comptes,
            ));
        }

        $this->line(sprintf('Total en base : %d créneaux.', AvailabilitySlot::query()->count()));

        return self::SUCCESS;
    }
}
