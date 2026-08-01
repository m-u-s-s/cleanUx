<?php

namespace App\Support\Domain;

/**
 * Cycle de vie d'un panier.
 *
 * `CONVERTED` est terminal : le brouillon a produit une réservation ou un lot. Il n'est jamais
 * purgé — il porte les réponses horodatées qui rendent le devis explicable et opposable.
 */
final class OrderDraftStatus
{
    /** En cours de construction, potentiellement sans compte. */
    public const DRAFT = 'draft';

    /** Le client a confirmé ; la conversion n'a pas encore eu lieu. */
    public const SUBMITTED = 'submitted';

    /** Devenu une réservation ou un lot multi-métiers. */
    public const CONVERTED = 'converted';

    /** Laissé sans suite. Conservé pour mesurer où le parcours perd ses clients. */
    public const ABANDONED = 'abandoned';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::SUBMITTED, self::CONVERTED, self::ABANDONED];
    }
}
