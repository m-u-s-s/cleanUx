<?php

namespace App\Services\Cerveau;

use App\Models\User;
use App\Services\Commission\ConseillerDeCommission;
use DomainException;

/**
 * LE CERVEAU — il lit toute la plateforme, il propose, il n'applique jamais seul.
 *
 * Il rassemble des analyses qui, prises séparément, ne disent pas grand-chose : un code promo
 * coûteux et un métier sans prestataire sont deux faits ; ensemble, ils racontent où va l'argent.
 *
 * SANS INTELLIGENCE ARTIFICIELLE, ET C'EST UN CHOIX. Chaque constat est une soustraction qu'on
 * peut refaire à la main. Un avis vérifiable se conteste ; un avis opaque s'obéit ou s'ignore, et
 * les deux sont mauvais quand il s'agit d'argent ou d'accuser quelqu'un.
 *
 * IL NE SORT JAMAIS D'ARGENT. Aucun remboursement, aucun virement dans son registre de gestes :
 * une automatisation qui déplace de l'argent finit par le déplacer une fois de trop.
 */
class Cerveau
{
    /** @var array<string, string> */
    public const DOMAINES = [
        'commission' => 'Commissions',
        'marketing' => 'Marketing',
        'fraude' => 'Fraude et abus',
    ];

    /**
     * TOUT CE QUE LE CERVEAU A VU, le plus grave en tête.
     *
     * @return list<Recommandation>
     */
    public function recommandations(?string $domaine = null): array
    {
        $tout = [
            ...$this->depuisLesCommissions(),
            ...app(AnalyseDuMarketing::class)->recommandations(),
            ...app(AnalyseDeLaFraude::class)->recommandations(),
        ];

        if ($domaine !== null) {
            $tout = array_values(array_filter($tout, fn (Recommandation $r): bool => $r->domaine === $domaine));
        }

        // LE PLUS GRAVE EN TÊTE. Un écran qui noie une alerte rouge sous douze remarques neutres
        // ne sert à rien : personne ne lit jusqu'en bas.
        $rang = [
            Recommandation::TON_DANGER => 0,
            Recommandation::TON_ATTENTION => 1,
            Recommandation::TON_BIEN => 2,
            Recommandation::TON_NEUTRE => 3,
        ];

        usort($tout, fn (Recommandation $a, Recommandation $b): int => ($rang[$a->ton] ?? 9) <=> ($rang[$b->ton] ?? 9));

        return $tout;
    }

    /** @return array<string, int> */
    public function compteurs(): array
    {
        $compteurs = array_fill_keys(array_keys(self::DOMAINES), 0);

        foreach ($this->recommandations() as $recommandation) {
            $compteurs[$recommandation->domaine] = ($compteurs[$recommandation->domaine] ?? 0) + 1;
        }

        return $compteurs;
    }

    /**
     * APPLIQUER UN GESTE PROPOSÉ.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws DomainException
     */
    public function appliquer(User $acteur, string $cle, array $arguments = []): string
    {
        return app(RegistreDesGestes::class)->appliquer($acteur, $cle, $arguments);
    }

    /**
     * LES CONSEILS SUR LES COMMISSIONS, dans le même format que les autres.
     *
     * Le conseiller de commission existait avant le cerveau et rend un tableau : on le traduit
     * ici plutôt que de le réécrire, pour que les deux écrans continuent de le lire.
     *
     * @return list<Recommandation>
     */
    private function depuisLesCommissions(): array
    {
        return array_map(
            fn (array $c): Recommandation => new Recommandation(
                domaine: 'commission',
                ton: $c['ton'],
                titre: $c['titre'],
                constat: $c['constat'],
                geste: $c['geste'],
            ),
            app(ConseillerDeCommission::class)->conseils(),
        );
    }
}
