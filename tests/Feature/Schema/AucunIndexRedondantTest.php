<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AUCUN INDEX NE DOIT ETRE LE PREFIXE D'UN AUTRE.
 *
 * Un index B-tree s'attaque par son PREFIXE GAUCHE : `WHERE a = ?` se sert aussi bien d'un index
 * `(a)` que d'un index `(a, b, c)`. Un index dont les colonnes sont un prefixe strict d'un autre
 * n'apporte donc rien a la lecture, et coute a chaque ecriture — il faut le tenir a jour.
 *
 * Dix-huit etaient dans ce cas au 2026-08-22, retires par
 * `2026_09_20_090000_retirer_les_index_redondants`. Ce test existe pour qu'il n'y en ait pas un
 * dix-neuvieme : il ENUMERE le schema tel que les migrations le construisent, plutot que de
 * verifier une liste ecrite a la main que personne ne penserait a completer.
 *
 * Les contraintes d'UNICITE sont ecartees : elles ne servent pas a lire, elles garantissent. Un
 * `unique(a, b)` peut parfaitement couvrir un `index(a)` — c'est meme le cas le plus frequent — mais
 * l'inverse ne se retire jamais.
 */
class AucunIndexRedondantTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucun_index_n_est_le_prefixe_strict_d_un_autre(): void
    {
        $redondants = [];

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            $index = collect(Schema::getIndexes($table));

            foreach ($index as $a) {
                // Une contrainte d'unicite garantit ; elle ne se juge pas sur son utilite en lecture.
                if ($a['unique'] || $a['primary']) {
                    continue;
                }

                foreach ($index as $b) {
                    if ($a['name'] === $b['name'] || count($a['columns']) >= count($b['columns'])) {
                        continue;
                    }

                    if (array_slice($b['columns'], 0, count($a['columns'])) === $a['columns']) {
                        $redondants[] = sprintf(
                            '%s.%s (%s) — couvert par %s (%s)',
                            $table,
                            $a['name'],
                            implode(', ', $a['columns']),
                            $b['name'],
                            implode(', ', $b['columns']),
                        );
                        break;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $redondants,
            "Ces index ne servent aucune lecture et coutent a chaque ecriture :\n  ".implode("\n  ", $redondants),
        );
    }

    /**
     * LE TEMOIN : le detecteur sait reconnaitre un index redondant quand il y en a un.
     *
     * Sans lui, le test precedent passerait au vert si l'enumeration ne regardait rien du tout —
     * une faute de nom de table, une API qui change, et la garde devient decorative.
     */
    public function test_temoin_le_detecteur_repere_bien_une_redondance(): void
    {
        Schema::create('cx_essai_redondance', function ($t) {
            $t->id();
            $t->string('a');
            $t->string('b');
            $t->index(['a'], 'cx_essai_a');
            $t->index(['a', 'b'], 'cx_essai_a_b');
        });

        try {
            $index = collect(Schema::getIndexes('cx_essai_redondance'))->keyBy('name');

            $this->assertTrue($index->has('cx_essai_a'), 'Le schema doit rendre les index qu’on vient de creer.');
            $this->assertSame(
                ['a', 'b'],
                $index->get('cx_essai_a_b')['columns'],
                'Le detecteur lit les colonnes dans l’ordre : sans cela, la regle du prefixe ne veut rien dire.',
            );
            $this->assertSame(['a'], $index->get('cx_essai_a')['columns']);
        } finally {
            Schema::dropIfExists('cx_essai_redondance');
        }
    }
}
