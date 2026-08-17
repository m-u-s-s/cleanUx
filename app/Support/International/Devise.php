<?php

namespace App\Support\International;

use App\Services\International\CountryMarketResolver;

/**
 * UNE SEULE FAÇON DE RÉPONDRE « DANS QUELLE MONNAIE ? ».
 *
 * `'EUR'` était écrit en dur dans une quinzaine d'endroits, et `?? 'EUR'` dans une trentaine
 * d'autres. Chacun était raisonnable isolément — c'est bien la devise de presque tout le trafic —
 * et c'est précisément ce qui rend la chose dangereuse : une valeur juste vingt fois sur
 * vingt-cinq ne se relit plus. Sur le marché marocain, chacune de ces lignes devient un mensonge
 * silencieux, et aucune ne lève d'erreur.
 *
 * ── CE QUE CETTE CLASSE FAIT, ET CE QU'ELLE NE FAIT PAS ──────────────────────────────────────
 *
 * Elle ne DEVINE pas la devise d'une commande : c'est le travail de
 * {@see CountryMarketResolver::deviseAttendue()}, qui part de la
 * POSITION. Elle sert aux objets d'AVAL — pourboire, devis, facture, versement, ligne de
 * portefeuille — dont la devise est déjà décidée par la réservation dont ils dépendent. Leur seul
 * travail est de la recopier sans la perdre.
 *
 * Le repli sur la devise de base de la plateforme reste possible, mais il est ÉCRIT et unique :
 * quand il change, il change partout, et l'endroit où le lire est évident.
 */
final class Devise
{
    /**
     * La devise de base de la plateforme.
     *
     * Elle vient du module FX, jamais d'un littéral : c'est la même valeur que consultent le
     * calcul de commission et la conversion de taux. En avoir deux les ferait diverger.
     */
    public static function plateforme(): string
    {
        return self::normaliser(config('fx.base_currency')) ?? 'EUR';
    }

    /**
     * La première devise réellement renseignée parmi les candidats, sinon celle de la plateforme.
     *
     * ON PARCOURT PLUTÔT QU'ON N'ENCHAÎNE DES `?:`, et la différence n'est pas cosmétique : `?:`
     * saute aussi les chaînes vides ET les zéros, alors que ce qu'on veut écarter ici est
     * précisément « non renseigné ». Un code ISO n'est jamais `0`, mais la même habitude appliquée
     * à un taux a déjà coûté cher dans ce dépôt — autant écrire ce qu'on veut dire.
     *
     * L'ordre des candidats porte l'intention de l'appelant : du plus proche de l'objet au plus
     * général. On donne d'abord la devise de la ligne, puis celle de sa réservation.
     */
    public static function premiereRenseignee(?string ...$candidats): string
    {
        foreach ($candidats as $candidat) {
            $devise = self::normaliser($candidat);

            if ($devise !== null) {
                return $devise;
            }
        }

        return self::plateforme();
    }

    /**
     * Le même choix, en minuscules — la forme qu'attend Stripe.
     *
     * Une méthode dédiée plutôt qu'un `strtolower()` chez l'appelant : c'est là que les erreurs se
     * logent. Stripe refuse `EUR` sur certains points d'entrée et l'accepte sur d'autres, ce qui
     * donne une panne intermittente au lieu d'une panne franche.
     */
    public static function pourStripe(?string ...$candidats): string
    {
        return strtolower(self::premiereRenseignee(...$candidats));
    }

    /** Un code ISO 4217 propre, ou `null` si rien d'exploitable n'a été fourni. */
    public static function normaliser(mixed $valeur): ?string
    {
        if (! is_string($valeur)) {
            return null;
        }

        $devise = strtoupper(trim($valeur));

        return strlen($devise) === 3 ? $devise : null;
    }
}
