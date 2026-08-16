<?php

namespace App\Services\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Carbon;

/**
 * MODIFIER LA DISPONIBILITÉ D'UN PRESTATAIRE — un seul écrivain, deux appelants.
 *
 * L'écran du prestataire et celui de l'administration font exactement les mêmes gestes sur les
 * mêmes tables. Écrits deux fois, ils divergent : la règle de chevauchement se durcit d'un côté,
 * la fermeture d'un jour se met à supprimer des créneaux de l'autre, et plus personne ne sait
 * lequel a raison. Ce dépôt en a déjà fait l'expérience — sur les métiers et les zones
 * (`ProviderCoverageWriter`), et sur la disponibilité par défaut
 * (`DefaultAvailabilityProvisioner`).
 *
 * LE PRESTATAIRE CONCERNÉ EST UN PARAMÈTRE, PAS `Auth::user()`. C'est ce qui permet à
 * l'administration d'agir pour quelqu'un d'autre sans qu'aucune règle ne change. Le contrôle
 * d'accès reste à l'appelant : ce service ne sait pas qui a le droit, il sait ce qui est valide.
 */
class AvailabilityEditor
{
    /** Renvoyé quand le créneau demandé en recouvre un autre le même jour. */
    public const CHEVAUCHEMENT = 'chevauchement';

    /**
     * Ajoute ou met à jour un créneau récurrent.
     *
     * @return AvailabilitySlot|string le créneau écrit, ou `self::CHEVAUCHEMENT`
     */
    public function saveSlot(
        User $provider,
        int $weekday,
        string $debut,
        string $fin,
        ?int $slotId = null,
    ): AvailabilitySlot|string {
        /*
         * LE CHEVAUCHEMENT SE VÉRIFIE AUSSI À LA MODIFICATION.
         *
         * L'ancienne page ne le testait qu'à la création : éditer un créneau pour le faire
         * recouvrir un autre passait sans un mot. Le créneau édité s'exclut lui-même de la
         * comparaison, sinon il se chevaucherait toujours.
         */
        $chevauche = AvailabilitySlot::query()
            ->where('provider_user_id', $provider->id)
            ->where('weekday', $weekday)
            ->when($slotId, fn ($q) => $q->whereKeyNot($slotId))
            // Deux intervalles se recouvrent si chacun commence avant que l'autre ne finisse.
            ->where('start_time', '<', $fin.':00')
            ->where('end_time', '>', $debut.':00')
            ->exists();

        if ($chevauche) {
            return self::CHEVAUCHEMENT;
        }

        $donnees = [
            'weekday' => $weekday,
            'start_time' => $debut.':00',
            'end_time' => $fin.':00',
        ];

        if ($slotId) {
            $slot = AvailabilitySlot::where('provider_user_id', $provider->id)->findOrFail($slotId);
            $slot->update($donnees);
            ActivityLogger::log('disponibilite_modifiee', $slot, $donnees + ['provider_user_id' => $provider->id]);

            return $slot;
        }

        $slot = AvailabilitySlot::create($donnees + [
            'provider_user_id' => $provider->id,
            'timezone' => config('availability.default_timezone', config('app.timezone')),
            'is_active' => true,
        ]);

        ActivityLogger::log('disponibilite_creee', $slot, $donnees + ['provider_user_id' => $provider->id]);

        return $slot;
    }

    public function deleteSlot(User $provider, int $slotId): void
    {
        $slot = AvailabilitySlot::where('provider_user_id', $provider->id)->findOrFail($slotId);

        ActivityLogger::log('disponibilite_supprimee', $slot, [
            'provider_user_id' => $provider->id,
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
        ]);

        $slot->delete();
    }

    /**
     * FERMER UN JOUR, C'EST POSER UNE EXCEPTION — pas effacer la semaine.
     *
     * Les créneaux sont RÉCURRENTS : les supprimer pour fermer une date fermerait tous les mardis
     * à venir. C'est ce que faisait l'ancien bouton « Bloquer », sans confirmation ni retour.
     */
    public function closeDay(User $provider, string $date, ?string $motif = null): AvailabilityException
    {
        $jour = Carbon::parse($date)->toDateString();

        /*
         * `whereDate`, PAS une égalité sur la date.
         *
         * `date` est casté sur le modèle : la colonne porte `2026-08-18 00:00:00` quand la
         * recherche compare `2026-08-18`. L'égalité échoue toujours, et chaque clic crée une
         * exception de plus — constaté au test.
         */
        $existante = AvailabilityException::query()
            ->where('provider_user_id', $provider->id)
            ->where('exception_type', AvailabilityException::TYPE_CLOSED)
            ->whereDate('date', $jour)
            ->first();

        if ($existante) {
            return $existante;
        }

        $exception = AvailabilityException::create([
            'provider_user_id' => $provider->id,
            'date' => $jour,
            'exception_type' => AvailabilityException::TYPE_CLOSED,
            'reason' => $motif !== '' ? $motif : null,
        ]);

        ActivityLogger::log('disponibilite_jour_ferme', $exception, [
            'provider_user_id' => $provider->id,
            'date' => $jour,
        ]);

        return $exception;
    }

    public function reopenDay(User $provider, int $exceptionId): void
    {
        $exception = AvailabilityException::where('provider_user_id', $provider->id)->findOrFail($exceptionId);

        ActivityLogger::log('disponibilite_jour_rouvert', $exception, [
            'provider_user_id' => $provider->id,
            'date' => $exception->date->format('Y-m-d'),
        ]);

        $exception->delete();
    }
}
