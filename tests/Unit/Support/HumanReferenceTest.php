<?php

namespace Tests\Unit\Support;

use App\Models\OrderDraft;
use App\Support\HumanReference;
use PHPUnit\Framework\TestCase;

/** Une référence destinée à être DICTÉE au téléphone. */
class HumanReferenceTest extends TestCase
{
    /** Les quatre caractères ambigus n'apparaissent JAMAIS. */
    public function test_the_ambiguous_characters_never_appear(): void
    {
        $tirage = '';

        for ($i = 0; $i < 1000; $i++) {
            $tirage .= HumanReference::make(8);
        }

        // Les QUATRE caractères d'un coup : savoir que « I » sort ne dit rien de « O », et il
        // faudrait quatre exécutions pour découvrir un alphabet entièrement fautif.
        $trouves = array_values(array_filter(
            ['I', 'O', '0', '1'],
            fn (string $interdit) => str_contains($tirage, $interdit),
        ));

        $this->assertSame([], $trouves, 'Ces caractères se confondent à la lecture et ne doivent pas être tirés.');
    }

    /** La longueur demandée est la longueur rendue. */
    public function test_the_requested_length_is_respected(): void
    {
        $ecarts = [];

        foreach ([1, 5, 6, 10, 32] as $longueur) {
            $obtenue = strlen(HumanReference::make($longueur));

            if ($obtenue !== $longueur) {
                $ecarts[] = "demandé {$longueur}, obtenu {$obtenue}";
            }
        }

        $this->assertSame([], $ecarts, 'La longueur demandée n’est pas respectée.');
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

    /** Les références réellement produites par le moteur respectent la règle. */
    public function test_the_order_reference_uses_the_dictable_alphabet(): void
    {
        $tirage = '';

        for ($i = 0; $i < 300; $i++) {
            $tirage .= OrderDraft::generateReference();
        }

        // Le préfixe « CLX- » contient un L et un X, tous deux légitimes ; on ne teste donc que
        // les caractères interdits, qui n'y figurent pas.
        $trouves = array_values(array_filter(
            ['I', 'O', '0', '1'],
            fn (string $interdit) => str_contains($tirage, $interdit),
        ));

        $this->assertSame([], $trouves, 'Ces caracteres se confondent a la lecture et ne doivent pas etre tires.');
    }
}
