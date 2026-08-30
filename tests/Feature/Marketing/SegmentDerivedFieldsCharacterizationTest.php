<?php

namespace Tests\Feature\Marketing;

use App\Services\Marketing\SegmentEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CE QUE FONT LES CHAMPS DERIVES AUJOURD'HUI — ils plantent.
 *
 * `buildQuery` enveloppe l'arbre dans `where(function ($q) {...})`, et la jointure du champ
 * derive est posee sur ce constructeur imbrique : elle n'est jamais compilee.
 */
class SegmentDerivedFieldsCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public static function champsDerives(): array
    {
        return [
            'bookings_count' => ['bookings_count', 'gt', 2],
            'last_booking_at' => ['last_booking_at', 'is_not_null', null],
            'total_spent_cents' => ['total_spent_cents', 'gte', 100],
        ];
    }

    #[DataProvider('champsDerives')]
    public function test_un_champ_derive_leve_une_erreur_de_colonne_inconnue(string $champ, string $op, mixed $valeur): void
    {
        $this->expectException(QueryException::class);

        app(SegmentEngine::class)->preview([
            'field' => $champ,
            'op' => $op,
            'value' => $valeur,
        ]);
    }

    /** TEMOIN — un champ simple, lui, repond. Sans lui, le test ci-dessus passerait au vert
     *  sur un moteur entierement casse. */
    public function test_temoin_un_champ_simple_repond_normalement(): void
    {
        $resultat = app(SegmentEngine::class)->preview([
            'field' => 'role',
            'op' => 'eq',
            'value' => 'client',
        ]);

        $this->assertIsArray($resultat);
    }
}
