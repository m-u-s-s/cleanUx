<?php

namespace App\Services\Dispatch;

use App\Models\User;

/**
 * UN CANDIDAT, ET LES DEUX SEULES CHOSES QUI DÉPARTAGENT : la distance, puis le score.
 *
 * Un objet plutôt qu'un tableau associatif, et ce n'est pas de la cosmétique : la liste de
 * candidats traverse le moteur, le simulateur d'administration et les journaux d'audit. Une clé
 * mal orthographiée dans un tableau se lit `null` en silence — et un `null` sur `distance_m`
 * classerait le prestataire le plus lointain en tête sans que rien ne signale l'erreur.
 *
 * `distanceM` est NULLABLE parce que le planifié travaille sur les zones déclarées : on n'exige pas
 * de connaître la position de quelqu'un pour lui proposer un rendez-vous jeudi. En immédiat, elle
 * est toujours renseignée — sans elle, « le plus proche » ne veut rien dire, et la recherche ne
 * démarre même pas.
 */
final class DispatchCandidate
{
    public function __construct(
        public readonly User $user,
        public readonly ?int $distanceM,
        public readonly float $score,
    ) {}

    public function id(): int
    {
        return (int) $this->user->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->id(),
            'name' => $this->user->name,
            'distance_m' => $this->distanceM,
            'distance_km' => $this->distanceM !== null ? round($this->distanceM / 1000, 2) : null,
            'score' => $this->score,
        ];
    }
}
