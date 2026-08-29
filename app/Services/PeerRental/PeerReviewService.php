<?php

namespace App\Services\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerReview;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * LES AVIS CROISES, REVELES A L'AVEUGLE.
 *
 * Tant que les deux n'ont pas depose, aucun n'est visible. Le second se calquerait sinon sur
 * le premier — c'est le meme choix que sur les avis de mission, et pour la meme raison.
 */
class PeerReviewService
{
    public function deposer(PeerRental $location, User $auteur, int $note, ?string $commentaire = null): PeerReview
    {
        if (! in_array($location->status, [PeerRental::STATUT_RENDUE, PeerRental::STATUT_TERMINEE, PeerRental::STATUT_LITIGE], true)) {
            throw new RuntimeException('Un avis se dépose après le retour du véhicule.');
        }

        $role = match ($auteur->id) {
            $location->owner_id => PeerReview::ROLE_PROPRIETAIRE,
            $location->renter_id => PeerReview::ROLE_LOCATAIRE,
            default => throw new RuntimeException('Vous n’êtes pas partie à cette location.'),
        };

        if ($note < 1 || $note > 5) {
            throw new RuntimeException('La note va de 1 à 5.');
        }

        if ($location->reviews()->where('author_id', $auteur->id)->exists()) {
            throw new RuntimeException('Vous avez déjà donné votre avis.');
        }

        $avis = PeerReview::create([
            'peer_rental_id' => $location->id,
            'author_id' => $auteur->id,
            'target_id' => $role === PeerReview::ROLE_PROPRIETAIRE ? $location->renter_id : $location->owner_id,
            'author_role' => $role,
            'rating' => $note,
            'comment' => $commentaire,
            'submitted_at' => now(),
        ]);

        $this->revelerSiLesDeuxOntDepose($location);

        return $avis->refresh();
    }

    /** LES DEUX ONT PARLE : on ouvre les deux enveloppes en meme temps. */
    public function revelerSiLesDeuxOntDepose(PeerRental $location): bool
    {
        $avis = $location->reviews()->whereNotNull('submitted_at')->get();

        if ($avis->count() < 2) {
            return false;
        }

        $this->reveler($avis);

        return true;
    }

    /**
     * LE DELAI PASSE, L'AVIS DEPOSE SE REVELE SEUL.
     *
     * Sans cela, un locataire qui ne repond jamais effacerait l'avis du proprietaire — et
     * la note d'un compte se construirait sur les seuls echanges ou les deux ont joue le jeu.
     *
     * @return int le nombre d'avis reveles
     */
    public function revelerLesAvisEnAttente(): int
    {
        $limite = now()->subDays(PeerReview::JOURS_AVANT_REVELATION);

        $avis = PeerReview::query()
            ->whereNull('revealed_at')
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $limite)
            ->get();

        $this->reveler($avis);

        return $avis->count();
    }

    /** La note d'un compte, sur ses avis REVELES seulement. */
    public function noteMoyenne(User $membre, ?string $role = null): ?float
    {
        $requete = PeerReview::query()->reveles()->where('target_id', $membre->id);

        if ($role !== null) {
            // Le role est celui de l'AUTEUR : « note recue en tant que locataire » se lit
            // sur les avis ecrits par des proprietaires.
            $requete->where('author_role', $role === PeerReview::ROLE_LOCATAIRE
                ? PeerReview::ROLE_PROPRIETAIRE
                : PeerReview::ROLE_LOCATAIRE);
        }

        $moyenne = $requete->avg('rating');

        return $moyenne === null ? null : round((float) $moyenne, 2);
    }

    /** @param  Collection<int, PeerReview>  $avis */
    private function reveler(Collection $avis): void
    {
        foreach ($avis as $un) {
            if ($un->revealed_at === null) {
                $un->forceFill(['revealed_at' => now()])->save();
            }
        }
    }
}
