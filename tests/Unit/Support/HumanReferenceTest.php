<?php

namespace Tests\Unit\Support;

use App\Models\OrderDraft;
use App\Support\HumanReference;
use PHPUnit\Framework\TestCase;

/**
 * Une référence destinée à être DICTÉE au téléphone.
 *
 * Le défaut que ce test aurait attrapé : `Str::random()` n'accepte QU'UNE longueur. L'alphabet
 * qu'on croyait lui passer en second argument était silencieusement ignoré, et les références
 * contenaient donc exactement les caractères qu'on prétendait exclure — I, O, 0 et 1, les quatre
 * qui se confondent quand un client lit sa référence au support.
 *
 * Rien ne le montrait : les références restaient uniques et fonctionnelles. Seul un test sur le
 * CONTENU, et non sur la forme, pouvait le révéler.
 */
class HumanReferenceTest extends TestCase
{
    /**
     * Les quatre caractères ambigus n'apparaissent JAMAIS.
     *
     * Mille tirages : sur un alphabet de 32 caractères, si les interdits étaient présents, la
     * probabilité de n'en voir aucun serait nulle en pratique.
     */
    public function test_the_ambiguous_characters_never_appear(): void
    {
        $tirage = '';

        for ($i = 0; $i < 1000; $i++) {
            $tirage .= HumanReference::make(8);
        }

        foreach (['I', 'O', '0', '1'] as $interdit) {
            $this->assertStringNotContainsString(
                $interdit,
                $tirage,
                sprintf('Le caractère « %s » se confond à la lecture et ne doit pas apparaître.', $interdit),
            );
        }
    }

    /** La longueur demandée est la longueur rendue. */
    public function test_the_requested_length_is_respected(): void
    {
        foreach ([1, 5, 6, 10, 32] as $longueur) {
            $this->assertSame($longueur, strlen(HumanReference::make($longueur)));
        }
    }

    /** Le tirage varie : une référence constante ferait collisionner toutes les commandes. */
    public function test_two_draws_differ(): void
    {
        $vues = [];

        for ($i = 0; $i < 200; $i++) {
            $vues[HumanReference::make(8)] = true;
        }

        // Sur 32^8 possibilités, 200 tirages distincts sont attendus ; une poignée de collisions
        // signalerait un générateur dégénéré.
        $this->assertGreaterThan(190, count($vues));
    }

    /** Le préfixe est conservé tel quel — c'est lui qui identifie le type de document. */
    public function test_the_prefix_is_kept_verbatim(): void
    {
        $reference = HumanReference::prefixed('CUX-', 6);

        $this->assertStringStartsWith('CUX-', $reference);
        $this->assertSame(10, strlen($reference));
    }

    /**
     * Les références réellement produites par le moteur respectent la règle.
     *
     * On vérifie le générateur ET son emploi : un helper correct appelé nulle part ne garantit
     * rien.
     */
    public function test_the_order_reference_uses_the_dictable_alphabet(): void
    {
        $tirage = '';

        for ($i = 0; $i < 300; $i++) {
            $tirage .= OrderDraft::generateReference();
        }

        // Le préfixe « CLX- » contient un L et un X, tous deux légitimes ; on ne teste donc que
        // les caractères interdits, qui n'y figurent pas.
        foreach (['I', 'O', '0', '1'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $tirage);
        }
    }
}
