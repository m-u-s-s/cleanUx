<?php

namespace App\Services\Cerveau;

/**
 * UNE RECOMMANDATION — un constat chiffré, un geste, et ce que le geste implique.
 *
 * ELLE PORTE TOUJOURS SON CHIFFRE. « Ce métier laisse 40 % des missions sans prestataire » se
 * vérifie et se conteste ; « il faudrait baisser la commission » ne se conteste pas — ça s'obéit
 * ou ça s'ignore, et les deux sont mauvais.
 *
 * LE GESTE EST FACULTATIF. Beaucoup de bons conseils n'ont pas de bouton : « regardez le délai
 * d'affectation avant de toucher au taux » est utile précisément parce qu'il demande un humain.
 */
final readonly class Recommandation
{
    public const TON_DANGER = 'danger';

    public const TON_ATTENTION = 'attention';

    public const TON_BIEN = 'bien';

    public const TON_NEUTRE = 'neutre';

    public function __construct(
        public string $domaine,
        public string $ton,
        public string $titre,
        /** Le fait mesuré, avec ses nombres. */
        public string $constat,
        /** Ce qu'il faudrait faire, et pourquoi. */
        public string $geste,
        /** La clé d'un geste applicable, quand il en existe un. */
        public ?string $gesteApplicable = null,
        /** @var array<string, mixed> Les arguments du geste. */
        public array $arguments = [],
    ) {}

    public function aUnBouton(): bool
    {
        return $this->gesteApplicable !== null;
    }
}
