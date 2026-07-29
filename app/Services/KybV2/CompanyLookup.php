<?php

namespace App\Services\KybV2;

use App\Services\KybV2\Contracts\BusinessVerificationProviderContract;
use App\Support\Validation\BusinessNumber;

/**
 * Interrogation d'un registre officiel à partir du seul numéro d'entreprise.
 *
 * Le module KYB savait déjà interroger l'INSEE et VIES, mais uniquement au sein d'une
 * vérification complète, qui exige de connaître d'avance la raison sociale — donc de la saisir.
 * Or c'est précisément ce qu'on veut éviter : le prestataire tape son numéro, et sa raison
 * sociale comme son adresse remontent du registre. Il confirme au lieu de recopier.
 *
 * Le résultat est une SUGGESTION, jamais une vérité imposée : le prestataire peut la corriger, et
 * la vérification KYB retranchera de toute façon plus tard. Un registre injoignable, un numéro
 * inconnu ou un fournisseur non configuré rendent simplement `null` — l'inscription se poursuit
 * avec une saisie manuelle plutôt que d'être bloquée par un service tiers.
 */
class CompanyLookup
{
    public function __construct(protected BusinessVerificationProviderContract $provider) {}

    /**
     * @return array{legal_name: ?string, legal_form: ?string, address: ?string, vat_id: ?string, identifier_type: string, source: string}|null
     */
    public function find(string $number): ?array
    {
        if (! BusinessNumber::isValid($number)) {
            return null;
        }

        $normalised = BusinessNumber::normalise($number);
        $type = $this->identifierTypeFor($normalised);

        if ($type === null) {
            return null;
        }

        $result = $this->provider->verifyIdentifier($type, $this->bareNumber($normalised), $this->countryFor($normalised));

        if (! $result->success) {
            return null;
        }

        return $this->normalisePayload($result, $type);
    }

    /** Le type attendu par les fournisseurs se déduit de la forme du numéro. */
    private function identifierTypeFor(string $value): ?string
    {
        $bare = $this->bareNumber($value);

        return match (true) {
            preg_match('/^\d{14}$/', $bare) === 1 => 'siret',
            preg_match('/^\d{9}$/', $bare) === 1 => 'siren',
            // BCE belge : dix chiffres, que les fournisseurs traitent comme un numéro
            // d'entreprise national.
            preg_match('/^\d{10}$/', $bare) === 1 => 'company_number',
            default => null,
        };
    }

    private function bareNumber(string $value): string
    {
        return preg_match('/^[A-Z]{2}/', $value) === 1 ? substr($value, 2) : $value;
    }

    private function countryFor(string $value): ?string
    {
        if (preg_match('/^([A-Z]{2})/', $value, $m) === 1) {
            return $m[1];
        }

        // Sans préfixe, la longueur désigne le pays : dix chiffres = BCE belge, neuf ou
        // quatorze = SIREN/SIRET français.
        return strlen($value) === 10 ? 'BE' : 'FR';
    }

    /**
     * Les fournisseurs ne parlent pas la même langue : le simulateur rend des clés déjà
     * normalisées, l'INSEE rend sa réponse brute. On lit les deux, et on préfère ne rien
     * proposer plutôt que de pré-remplir un champ avec une valeur qu'on n'a pas comprise.
     *
     * @return array{legal_name: ?string, legal_form: ?string, address: ?string, vat_id: ?string, identifier_type: string, source: string}
     */
    private function normalisePayload(VerificationResult $result, string $type): array
    {
        $payload = $result->payload;

        // Réponse INSEE brute : l'unité légale porte la dénomination, l'établissement l'adresse.
        $unit = $payload['uniteLegale'] ?? $payload['etablissement']['uniteLegale'] ?? [];
        $address = $payload['adresseEtablissement'] ?? $payload['etablissement']['adresseEtablissement'] ?? [];

        return [
            'legal_name' => $this->firstString([
                $payload['legal_name'] ?? null,
                $unit['denominationUniteLegale'] ?? null,
                trim(($unit['prenom1UniteLegale'] ?? '').' '.($unit['nomUniteLegale'] ?? '')),
            ]),
            'legal_form' => $this->firstString([
                $payload['legal_form'] ?? null,
                $unit['categorieJuridiqueUniteLegale'] ?? null,
            ]),
            'address' => $this->firstString([
                is_string($payload['registered_address'] ?? null) ? $payload['registered_address'] : null,
                $this->formatInseeAddress($address),
            ]),
            'vat_id' => $this->firstString([$payload['vat_id'] ?? null]),
            'identifier_type' => $type,
            'source' => $result->provider,
        ];
    }

    /** @param array<int, mixed> $candidates */
    private function firstString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $address */
    private function formatInseeAddress(array $address): ?string
    {
        $parts = array_filter([
            $address['numeroVoieEtablissement'] ?? null,
            $address['typeVoieEtablissement'] ?? null,
            $address['libelleVoieEtablissement'] ?? null,
            $address['codePostalEtablissement'] ?? null,
            $address['libelleCommuneEtablissement'] ?? null,
        ], static fn ($part): bool => is_string($part) && trim($part) !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }
}
