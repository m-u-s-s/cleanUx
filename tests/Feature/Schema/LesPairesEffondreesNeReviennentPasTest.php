<?php

namespace Tests\Feature\Schema;

use App\Models\Concerns\HasLegacyBookingAliases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Une paire FR/EN effondrée ne revient pas.
 *
 * `bookings` portait quinze paires disant chacune la même chose deux fois. Chacune coûte deux
 * balayages complets de la table à CHAQUE `save()`. Celles qui sont effondrées sont listées ici :
 * la colonne française doit avoir disparu du schéma ET de la liste du trait.
 */
class LesPairesEffondreesNeReviennentPasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Colonne française supprimée => colonne anglaise qui la remplace.
     *
     * @var array<string, string>
     */
    private const EFFONDREES = [
        'type_lieu' => 'place_type',
        'frequence' => 'frequency',
        'commentaire_client' => 'customer_comment',
        'telephone_client' => 'contact_phone',
        'duree_estimee' => 'estimated_duration_minutes',
    ];

    /**
     * TÉMOIN — le schéma répond, et une colonne encore vivante est bien vue.
     * Sans lui, un `hasColumn` qui rendrait toujours `false` ferait passer le test sur du vide.
     */
    public function test_temoin_le_schema_repond(): void
    {
        $this->assertTrue(Schema::hasTable('bookings'));
        $this->assertTrue(Schema::hasColumn('bookings', 'place_type'), 'La colonne de remplacement a disparu.');
        $this->assertFalse(Schema::hasColumn('bookings', 'colonne_qui_n_existe_pas'));
    }

    public function test_aucune_colonne_effondree_n_est_revenue(): void
    {
        $revenues = [];

        foreach (self::EFFONDREES as $francaise => $anglaise) {
            if (Schema::hasColumn('bookings', $francaise)) {
                $revenues[] = "bookings.{$francaise} est revenue — {$anglaise} lui suffit";
            }

            if (! Schema::hasColumn('bookings', $anglaise)) {
                $revenues[] = "bookings.{$anglaise} a disparu — c'est elle qui porte la notion";
            }
        }

        $this->assertSame([], $revenues);
    }

    /**
     * Le trait ne doit plus synchroniser une paire effondrée : il balaierait la table
     * pour une colonne qui n'existe plus.
     */
    public function test_le_trait_ne_synchronise_plus_une_paire_effondree(): void
    {
        $lecture = new \ReflectionClass(HasLegacyBookingAliases::class);
        $paires = $lecture->getStaticPropertyValue('legacyAliasPairs', []);

        // TÉMOIN — la liste est bien lue, et elle contient encore de vraies paires.
        $this->assertGreaterThan(5, count($paires), 'La liste des paires est vide : la lecture a échoué.');

        $restantes = [];

        foreach ($paires as [$francaise, $anglaise]) {
            if (array_key_exists($francaise, self::EFFONDREES)) {
                $restantes[] = "{$francaise} / {$anglaise} : la colonne est supprimée, la paire doit sortir de la liste";
            }
        }

        $this->assertSame([], $restantes);
    }
}
