<?php

namespace App\Support\Validation;

/** Numéro d'entreprise : normalisation et contrôle de clé. */
final class BusinessNumber
{
    /** Séparateurs usuels : « BE 0123.456.749 », « 123 456 789 », « 0123-456-749 ». */
    public static function normalise(string $raw): string
    {
        return strtoupper((string) preg_replace('/[\s.\-\/]/', '', trim($raw)));
    }

    /** Pays émetteur du numéro : son préfixe s'il en porte un, sa longueur sinon. */
    public static function countryFor(string $raw): ?string
    {
        $value = self::normalise($raw);

        if (preg_match('/^([A-Z]{2})/', $value, $m) === 1) {
            return $m[1];
        }

        return match (true) {
            preg_match('/^\d{10}$/', $value) === 1 => 'BE',
            preg_match('/^\d{9}$/', $value) === 1, preg_match('/^\d{14}$/', $value) === 1 => 'FR',
            default => null,
        };
    }

    /** Type d'identifiant au vocabulaire du module de vérification d'entreprise. */
    public static function identifierType(string $raw): ?string
    {
        $value = self::normalise($raw);
        $bare = preg_match('/^[A-Z]{2}/', $value) === 1 ? substr($value, 2) : $value;

        return match (true) {
            preg_match('/^\d{14}$/', $bare) === 1 => 'siret',
            preg_match('/^\d{9}$/', $bare) === 1 => 'siren',
            preg_match('/^\d{10}$/', $bare) === 1 => 'kbo',
            default => null,
        };
    }

    /** Numéro sans son préfixe pays, tel que l'attendent les registres. */
    public static function bareNumber(string $raw): string
    {
        $value = self::normalise($raw);

        return preg_match('/^[A-Z]{2}/', $value) === 1 ? substr($value, 2) : $value;
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

    /** BCE/KBO : dix chiffres dont les deux derniers sont la clé, `97 - (base mod 97)`. */
    private static function isValidBelgianEnterpriseNumber(string $digits): bool
    {
        if (! in_array($digits[0], ['0', '1'], true)) {
            return false;
        }

        $base = (int) substr($digits, 0, 8);
        $check = (int) substr($digits, 8, 2);

        return (97 - ($base % 97)) === $check;
    }

    /** TVA française : deux caractères de clé puis le SIREN. */
    private static function isValidFrenchVatKey(string $key, string $siren): bool
    {
        if (preg_match('/^\d{2}$/', $key) !== 1) {
            return true;
        }

        return ((12 + 3 * ((int) $siren % 97)) % 97) === (int) $key;
    }

    /** SIRET : SIREN (9) + NIC (5), l'ensemble validé par Luhn. */
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
