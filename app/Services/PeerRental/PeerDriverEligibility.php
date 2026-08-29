<?php

namespace App\Services\PeerRental;

use App\Models\PeerVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * QUI A LE DROIT DE LOUER.
 *
 * Le permis se depose et se fait valider AVANT la premiere reservation. Le circuit existe
 * deja — `ProviderOnboardingDocument` porte le type `driving_license` et le cycle en
 * attente / valide / refuse. Son nom dit « Provider » ; sa table est rattachee a un COMPTE,
 * et la reutiliser evite deux files de revue a l'administration pour une meme piece.
 */
class PeerDriverEligibility
{
    /** @var list<string> les pieces exigees d'un locataire */
    public const PIECES_REQUISES = [
        ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
        ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
    ];

    /**
     * POURQUOI IL NE PEUT PAS RESERVER — pour le lui dire, pas seulement le lui refuser.
     *
     * @return string|null null si le locataire est en regle
     */
    public function motifDeRefus(User $locataire, ?PeerVehicle $vehicule = null): ?string
    {
        $manquantes = $this->piecesManquantes($locataire);

        if ($manquantes !== []) {
            return __('Votre permis de conduire doit être validé avant de réserver.');
        }

        if ($vehicule === null) {
            return null;
        }

        if ($vehicule->owner_id === $locataire->id) {
            return __('Vous ne pouvez pas louer votre propre véhicule.');
        }

        $age = $this->ageDuConducteur($locataire);

        if ($age !== null && $age < $vehicule->min_driver_age) {
            return __('Ce véhicule demande :n ans minimum.', ['n' => $vehicule->min_driver_age]);
        }

        $anciennete = $this->anneesDePermis($locataire);

        if ($anciennete !== null && $anciennete < $vehicule->min_license_years) {
            return __('Ce véhicule demande :n an(s) de permis.', ['n' => $vehicule->min_license_years]);
        }

        return null;
    }

    public function peutReserver(User $locataire, ?PeerVehicle $vehicule = null): bool
    {
        return $this->motifDeRefus($locataire, $vehicule) === null;
    }

    /**
     * LES PIECES QUI MANQUENT OU QUI NE SONT PAS VALIDEES.
     *
     * Une piece perimee ne vaut pas mieux qu'une piece absente : c'est justement le cas
     * qu'un controle « le document existe » laisserait passer.
     *
     * @return list<string>
     */
    public function piecesManquantes(User $locataire): array
    {
        $valides = ProviderOnboardingDocument::query()
            ->where('user_id', $locataire->id)
            ->whereIn('document_type', self::PIECES_REQUISES)
            ->where('status', ProviderOnboardingDocument::STATUS_APPROVED)
            ->where(function ($requete): void {
                $requete->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->pluck('document_type')
            ->all();

        // La piece d'identite accepte trois formes : passeport et titre de sejour valent carte.
        if (! in_array(ProviderOnboardingDocument::TYPE_IDENTITY_CARD, $valides, true)) {
            $equivalente = ProviderOnboardingDocument::query()
                ->where('user_id', $locataire->id)
                ->whereIn('document_type', [
                    ProviderOnboardingDocument::TYPE_PASSPORT,
                    ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT,
                ])
                ->where('status', ProviderOnboardingDocument::STATUS_APPROVED)
                ->exists();

            if ($equivalente) {
                $valides[] = ProviderOnboardingDocument::TYPE_IDENTITY_CARD;
            }
        }

        return array_values(array_diff(self::PIECES_REQUISES, $valides));
    }

    /**
     * L'AGE ET L'ANCIENNETE SE LISENT SUR LE PERMIS VALIDE.
     *
     * `users` ne porte pas de date de naissance : l'inventer en colonne pour ce seul module
     * aurait cree une seconde source de verite face a la piece que l'administration a revue.
     * Absentes des metadonnees, ces deux conditions ne bloquent pas — elles ne se devinent pas.
     */
    private function ageDuConducteur(User $locataire): ?int
    {
        return $this->anneesDepuis($this->permisValide($locataire)?->metadata['birthdate'] ?? null);
    }

    private function anneesDePermis(User $locataire): ?int
    {
        return $this->anneesDepuis($this->permisValide($locataire)?->metadata['issued_at'] ?? null);
    }

    private function permisValide(User $locataire): ?ProviderOnboardingDocument
    {
        return ProviderOnboardingDocument::query()
            ->where('user_id', $locataire->id)
            ->where('document_type', ProviderOnboardingDocument::TYPE_DRIVING_LICENSE)
            ->where('status', ProviderOnboardingDocument::STATUS_APPROVED)
            ->latest('reviewed_at')
            ->first();
    }

    private function anneesDepuis(mixed $date): ?int
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        return (int) Carbon::parse($date)->diffInYears(now());
    }
}
