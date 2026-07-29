<?php

namespace App\Support\Validation;

/**
 * Numéro d'entreprise : normalisation et contrôle de clé.
 *
 * `vat_number` était accepté en `nullable|max:32`, sans le moindre contrôle : « abc » passait.
 * Or ce numéro n'est pas décoratif — c'est lui que la vérification KYB soumettra à l'INSEE et à
 * VIES. Un numéro mal saisi n'échoue donc pas à l'inscription mais plusieurs jours plus tard, à
 * la revue du dossier, quand le prestataire n'est plus devant son téléphone.
 *
 * Le numéro s'auto-identifie : soit il porte son préfixe pays (BE…, FR…), soit sa longueur le
 * désigne sans ambiguïté (10 chiffres = BCE belge, 9 = SIREN, 14 = SIRET). Aucun champ « pays »
 * supplémentaire n'est donc demandé à la saisie.
 *
 * Les pays hors BE/FR sont admis sur leur seule forme (2 lettres + 2 à 12 alphanumériques), sans
 * contrôle de clé : mieux vaut laisser passer un numéro luxembourgeois que le rejeter à tort. Le
 * marché visé est BE/FR, ce sont les deux schémas modélisés ici.
 */
final class BusinessNumber
{
    /** Séparateurs usuels : « BE 0123.456.749 », « 123 456 789 », « 0123-456-749 ». */
    public static function normalise(string $raw): string
    {
        return strtoupper((string) preg_replace('/[\s.\-\/]/', '', trim($raw)));
    }

    public static function isValid(string $raw): bool
    {
        $value = self::normalise($raw);

        if ($value === '') {
            return false;
        }

        // Un préfixe BE/FR engage le schéma du pays : c'est lui, et non le repli générique du
        // bas de méthode, qui tranche. Sans cette sortie anticipée, « BE0202239951X » — trop long
        // pour le motif belge — retombait sur le repli et passait pour valide.
        if (str_starts_with($value, 'BE')) {
            return preg_match('/^BE(\d{10})$/', $value, $m) === 1
                && self::isValidBelgianEnterpriseNumber($m[1]);
        }

        if (str_starts_with($value, 'FR')) {
            return preg_match('/^FR([0-9A-Z]{2})(\d{9})$/', $value, $m) === 1
                && self::isValidFrenchVatKey($m[1], $m[2])
                && self::isLuhnValid($m[2]);
        }

        // Numéro national sans préfixe : la longueur suffit à le désigner.
        if (preg_match('/^\d{10}$/', $value) === 1) {
            return self::isValidBelgianEnterpriseNumber($value);
        }

        if (preg_match('/^\d{9}$/', $value) === 1) {
            return self::isLuhnValid($value);
        }

        if (preg_match('/^\d{14}$/', $value) === 1) {
            return self::isValidSiret($value);
        }

        // Autre pays de l'Union : forme plausible, clé non vérifiée (voir docblock).
        return preg_match('/^[A-Z]{2}[0-9A-Z]{2,12}$/', $value) === 1;
    }

    /**
     * BCE/KBO : dix chiffres dont les deux derniers sont la clé, `97 - (base mod 97)`.
     * Le numéro commence par 0 (entreprises) ou 1 (numéros attribués depuis 2020).
     */
    private static function isValidBelgianEnterpriseNumber(string $digits): bool
    {
        if (! in_array($digits[0], ['0', '1'], true)) {
            return false;
        }

        $base = (int) substr($digits, 0, 8);
        $check = (int) substr($digits, 8, 2);

        return (97 - ($base % 97)) === $check;
    }

    /**
     * TVA française : deux caractères de clé puis le SIREN. Pour une clé numérique, elle vaut
     * `(12 + 3 × (SIREN mod 97)) mod 97`. Les clés alphabétiques (numéros anciens) ne suivent pas
     * cette règle : on se contente alors de leur forme, le SIREN restant contrôlé par Luhn.
     */
    private static function isValidFrenchVatKey(string $key, string $siren): bool
    {
        if (preg_match('/^\d{2}$/', $key) !== 1) {
            return true;
        }

        return ((12 + 3 * ((int) $siren % 97)) % 97) === (int) $key;
    }

    /**
     * SIRET : SIREN (9) + NIC (5), l'ensemble validé par Luhn.
     *
     * La Poste fait exception — ses SIRET commencent par 356000000 et ne satisfont pas Luhn ;
     * la règle officielle veut que la somme de leurs chiffres soit un multiple de 5.
     */
    private static function isValidSiret(string $digits): bool
    {
        if (str_starts_with($digits, '356000000')) {
            return array_sum(str_split($digits)) % 5 === 0;
        }

        return self::isLuhnValid($digits) && self::isLuhnValid(substr($digits, 0, 9));
    }

    private static function isLuhnValid(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
