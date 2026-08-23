<?php

namespace Tests\Feature\OrderEngine;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Étape 1 du moteur de commande — le schéma. */
class OrderEngineSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** LA garantie du catalogue : on a ÉTENDU les métiers, on n'en a pas créé une seconde table. */
    public function test_the_engine_extends_the_existing_trades_table(): void
    {
        $this->assertTrue(Schema::hasTable('trades'));

        // Les huit colonnes verifiees ensemble : une migration incomplete en laisse plusieurs de
        // cote, et le moteur echoue alors sur la premiere qu'il touche, pas sur la liste.
        $absentes = array_values(array_filter([
            'sector_id', 'base_price_cents', 'pricing_unit',
            'estimated_duration_min', 'min_duration_min',
            'allows_scheduled', 'allows_asap', 'allows_bundle',
        ], fn (string $c) => ! Schema::hasColumn('trades', $c)));

        $this->assertSame(
            [],
            $absentes,
            'Ces colonnes manquent a `trades` : le moteur de commande doit ETENDRE les metiers existants, pas les dupliquer.',
        );
    }

    /** Les métiers déjà en base doivent survivre à l'extension sans reprise de données. */
    public function test_an_existing_style_trade_still_inserts_without_the_new_columns(): void
    {
        $id = DB::table('trades')->insertGetId([
            'slug' => 'metier-legacy',
            'code' => 'LEGACY',
            'name' => 'Métier hérité',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trade = DB::table('trades')->find($id);

        $this->assertNotNull($trade);
        // Le défaut est délibérément asymétrique : un métier n'est PAS immédiat par défaut.
        $this->assertEquals(1, $trade->allows_scheduled);
        $this->assertEquals(0, $trade->allows_asap);
    }

    /** La loi 1 rendue structurelle : un panier sans compte doit pouvoir exister. */
    public function test_an_order_draft_exists_without_any_account(): void
    {
        $id = DB::table('order_drafts')->insertGetId([
            'reference' => 'CLX-ANON1',
            'client_id' => null,
            'session_token' => 'jeton-de-session',
            'mode' => 'scheduled',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draft = DB::table('order_drafts')->find($id);

        $this->assertNull($draft->client_id);
        $this->assertSame('jeton-de-session', $draft->session_token);
    }

    /** L'instantané est ce qui rend un devis opposable. Il ne peut pas être facultatif. */
    public function test_an_answer_cannot_be_written_without_its_snapshot(): void
    {
        [$draftId, $itemId] = $this->draftWithItem();

        $this->expectException(QueryException::class);

        DB::table('order_draft_answers')->insert([
            'order_draft_item_id' => $itemId,
            'question_code' => 'surface_m2',
            // 'question_label_snapshot' volontairement absent
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Une même question ne peut pas être répondue deux fois sur la même ligne de commande. */
    public function test_a_question_is_answered_once_per_item(): void
    {
        [, $itemId] = $this->draftWithItem();

        $answer = [
            'order_draft_item_id' => $itemId,
            'question_code' => 'surface_m2',
            'question_label_snapshot' => 'Quelle surface à peindre ?',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('order_draft_answers')->insert($answer);

        $this->expectException(QueryException::class);
        DB::table('order_draft_answers')->insert($answer);
    }

    /** Archiver une question ne doit pas emporter les réponses déjà données. */
    public function test_deleting_a_question_detaches_the_answer_instead_of_destroying_it(): void
    {
        [, $itemId] = $this->draftWithItem();

        $tradeId = DB::table('trades')->insertGetId([
            'slug' => 'peinture-test', 'code' => 'PEINT-T', 'name' => 'Peinture',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $questionId = DB::table('questions')->insertGetId([
            'trade_id' => $tradeId, 'code' => 'surface_m2', 'label' => 'Quelle surface à peindre ?',
            'type' => 'surface', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('order_draft_answers')->insert([
            'order_draft_item_id' => $itemId,
            'question_id' => $questionId,
            'question_code' => 'surface_m2',
            'question_label_snapshot' => 'Quelle surface à peindre ?',
            'answer_label_snapshot' => '42 m²',
            'price_impact_cents' => 8400,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('questions')->where('id', $questionId)->delete();

        $answer = DB::table('order_draft_answers')->where('question_code', 'surface_m2')->first();

        $this->assertNotNull($answer, 'La réponse a disparu avec la question : le devis n’est plus opposable.');
        $this->assertNull($answer->question_id);
        $this->assertSame('Quelle surface à peindre ?', $answer->question_label_snapshot);
        $this->assertSame('42 m²', $answer->answer_label_snapshot);
        $this->assertSame(8400, (int) $answer->price_impact_cents);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function draftWithItem(): array
    {
        $draftId = DB::table('order_drafts')->insertGetId([
            'reference' => 'CLX-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'mode' => 'scheduled', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tradeId = DB::table('trades')->insertGetId([
            'slug' => 'trade-'.uniqid(), 'code' => strtoupper(uniqid()), 'name' => 'Métier',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $itemId = DB::table('order_draft_items')->insertGetId([
            'order_draft_id' => $draftId, 'trade_id' => $tradeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$draftId, $itemId];
    }
}
