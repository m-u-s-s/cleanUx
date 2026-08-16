<?php

namespace App\Services\FaceCheck;

use App\Models\Booking;
use App\Models\User;
use App\Services\Modules\PlatformModuleResolver;
use Illuminate\Support\Facades\DB;

/**
 * QUI EST SOUMIS AU CONTRÔLE FACIAL — et c'est le seul endroit qui répond.
 *
 * ATTENTION : il y a DEUX questions ici, et les confondre serait exactement le défaut dominant de
 * ce dépôt — deux notions distinctes réunies sous un seul nom.
 *
 *   NOTION A — « ce PRESTATAIRE est-il soumis ? »  → `appliesToProvider()`
 *       Vrai si au moins un des métiers qu'il a déclarés exige le contrôle, ET si le module est
 *       actif pour au moins une de ses zones. C'est elle qui gouverne l'enrôlement, la cadence,
 *       le blocage, et la porte « passer en ligne ».
 *
 *   NOTION B — « cette RÉSERVATION exige-t-elle un prestataire contrôlé ? » → `appliesToBooking()`
 *       Vrai si LE métier de cette réservation l'exige, ET si le module est actif dans LA zone de
 *       cette réservation. C'est elle qui gouverne le filtre de dispatch et l'acceptation.
 *
 * Un peintre qui fait aussi du babysitting est soumis (notion A) même quand il reçoit une mission
 * de peinture (notion B fausse pour cette mission-là). L'inverse existe aussi : un prestataire
 * hors zone couverte n'est pas soumis, mais une réservation dans une zone couverte exige quand
 * même un intervenant contrôlé — et il en sera donc écarté.
 */
class FaceCheckRequirement
{
    /** @var list<int>|null */
    private ?array $metiersSoumis = null;

    public function __construct(
        private readonly PlatformModuleResolver $resolver,
    ) {}

    /**
     * NOTION A. Ce prestataire doit-il enrôler son visage et se soumettre aux contrôles ?
     */
    public function appliesToProvider(User $provider): bool
    {
        if (! $this->moduleAllume()) {
            return false;
        }

        // Un compte sans profil prestataire n'a rien à faire ici : clients, admins, sociétés.
        if ($provider->providerProfile === null) {
            return false;
        }

        $metiers = $this->metiersDuPrestataire($provider);

        if (array_intersect($metiers, $this->tradeIdsRequiringCheck()) === []) {
            return false;
        }

        return $this->moduleActifPour($provider, $this->zonesDuPrestataire($provider));
    }

    /**
     * NOTION B. Cette réservation exige-t-elle un intervenant contrôlé ?
     */
    public function appliesToBooking(Booking $booking): bool
    {
        if (! $this->moduleAllume()) {
            return false;
        }

        $metier = $booking->resolveTradeId();

        if ($metier === null || ! in_array((int) $metier, $this->tradeIdsRequiringCheck(), true)) {
            return false;
        }

        $zone = $booking->service_zone_id !== null ? [(int) $booking->service_zone_id] : [];

        return $this->moduleActifPour(null, $zone);
    }

    /**
     * Les métiers qui exigent le contrôle. Mémoïsé : le dispatch appelle en boucle.
     *
     * @return list<int>
     */
    public function tradeIdsRequiringCheck(): array
    {
        if ($this->metiersSoumis === null) {
            $this->metiersSoumis = DB::table('trades')
                ->where('requires_face_check', true)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->metiersSoumis;
    }

    public function forget(): void
    {
        $this->metiersSoumis = null;
    }

    /**
     * @return list<int>
     */
    public function zonesDuPrestataire(User $provider): array
    {
        $maintenant = now();

        /*
         * Les MÊMES deux sources que `CandidateFinder::scheduled()` : la zone principale portée par
         * l'utilisateur, et les affectations actives et en fenêtre. Lire seulement la première —
         * ce que fait `PlatformModuleResolver::contextFor()` — laisserait hors périmètre tout
         * prestataire multi-zones dont la zone principale n'est pas couverte.
         */
        $affectees = DB::table('employee_zone_assignments')
            ->where('user_id', $provider->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $maintenant))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $maintenant))
            ->pluck('service_zone_id')
            ->all();

        return collect($affectees)
            ->push($provider->primary_service_zone_id)
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function moduleAllume(): bool
    {
        return (bool) config('face_check.enabled', true);
    }

    /**
     * @param  list<int>  $zoneIds
     */
    private function moduleActifPour(?User $user, array $zoneIds): bool
    {
        return $this->resolver->isEnabledFor(
            (string) config('face_check.module_key', 'security.face_check'),
            $user,
            ['zone_ids' => $zoneIds],
        );
    }

    /**
     * @return list<int>
     */
    private function metiersDuPrestataire(User $provider): array
    {
        return DB::table('trade_user')
            ->where('user_id', $provider->id)
            ->pluck('trade_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
