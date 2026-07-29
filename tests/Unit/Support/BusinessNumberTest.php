<?php

namespace Tests\Unit\Support;

use App\Support\Validation\BusinessNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contrôle de clé du numéro d'entreprise.
 *
 * Les numéros valides employés ici sont de vraies entreprises publiquement enregistrées : un
 * contrôle de clé ne se teste utilement que sur des numéros réellement émis, des valeurs
 * inventées passant ou échouant par hasard.
 */
class BusinessNumberTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function validNumbers(): array
    {
        return [
            'BCE avec préfixe et séparateurs' => ['BE 0202.239.951'],   // Proximus
            'BCE nue' => ['0202239951'],
            'BCE Colruyt' => ['BE0400378485'],
            'TVA française' => ['FR64443061841'],                       // Google France
            'SIREN nu' => ['443061841'],
            'SIRET' => ['44306184100047'],
            'TVA luxembourgeoise (forme seule)' => ['LU12345678'],
            'TVA néerlandaise (forme seule)' => ['NL123456789B01'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'texte libre' => ['abc'],
            'vide' => [''],
            'clé BCE fausse' => ['0202239952'],
            'clé TVA française fausse' => ['FR61443061841'],
            'SIREN ne satisfaisant pas Luhn' => ['123456789'],
            // Le repli générique « 2 lettres + alphanumériques » rattrapait ce numéro belge
            // malformé : un préfixe connu doit engager le schéma du pays, sans repli.
            'BCE trop longue' => ['BE0202239951X'],
            'préfixe français mal formé' => ['FRABC443061841'],
            'chiffres en trop' => ['4430618410004712'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_it_accepts_real_numbers(string $number): void
    {
        $this->assertTrue(BusinessNumber::isValid($number), "{$number} devrait être accepté");
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_malformed_numbers(string $number): void
    {
        $this->assertFalse(BusinessNumber::isValid($number), "{$number} devrait être rejeté");
    }

    public function test_it_strips_the_usual_separators(): void
    {
        $this->assertSame('BE0202239951', BusinessNumber::normalise(' be 0202.239-951 '));
    }
}
