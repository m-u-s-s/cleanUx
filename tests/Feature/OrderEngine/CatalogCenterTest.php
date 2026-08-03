<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\QuestionInsights;
use App\Services\OrderEngine\TradeFormPublisher;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'écran catalogue, et les statistiques qui font vivre la règle des sept questions.
 *
 * Cet écran est la porte d'entrée qui manquait : sans lui, le constructeur de parcours n'était
 * atteignable par aucun lien. Et il ne se contente pas de lister — il dit ce qui bloque et ce qui
 * attend d'être publié, faute de quoi personne n'ouvrira dix écrans pour le savoir.
 */
class CatalogCenterTest extends TestCase
{
    /**
     * Le contexte géographique exigé par l'écran, créé à la demande.
     *
     * Ces tests ne portent pas sur la géographie : ils ont seulement besoin d'un couple pays/zone
     * cohérent pour monter le composant. La fabrique est paresseuse pour ne pas alourdir les tests
     * qui ne montent pas l'écran.
     *
     * @return array{country: Country, zone: ServiceZone}
     */
    private function contexteCatalogue(): array
    {
        if ($this->contexteCatalogue === null) {
            $pays = Country::factory()->create();
            $this->contexteCatalogue = [
                'country' => $pays,
                'zone' => ServiceZone::factory()->create(['country_id' => $pays->id]),
            ];
        }

        return $this->contexteCatalogue;
    }

    /** @var array{country: Country, zone: ServiceZone}|null */
    private ?array $contexteCatalogue = null;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_it_lists_the_sectors_and_their_trades(): void
    {
        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())
            ->assertOk()
            ->assertSee('Bâtiment & rénovation')
            ->assertSee('Peinture')
            ->assertSee('Espaces verts');
    }

    /** Un non-admin est refusé par le composant, pas seulement par la route. */
    public function test_a_non_admin_is_refused_by_the_component(): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())->assertForbidden();
    }

    /** Ce qui bloque la publication se voit depuis la liste, sans ouvrir le parcours. */
    public function test_a_blocked_trade_is_visible_from_the_list(): void
    {
        Trade::where('slug', 'peinture')->firstOrFail()
            ->questions()->where('code', 'etendue')->firstOrFail()
            ->options()->update(['is_default' => true]);

        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())->assertSee('publication bloquée');
    }

    /** Un parcours modifié après publication est signalé comme tel. */
    public function test_pending_changes_are_visible_from_the_list(): void
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail();
        app(TradeFormPublisher::class)->publish($trade, User::factory()->create(['role' => 'admin']));
        $trade->questions()->first()->update(['label' => 'Un libellé tout neuf']);

        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())->assertSee('modifications non publiées');
    }

    /**
     * Les métiers sans secteur sont SIGNALÉS, pas tus.
     *
     * Ils restent utilisables ailleurs mais n'apparaissent pas dans le parcours de commande : les
     * taire ferait chercher longtemps pourquoi « Toiture » est introuvable côté client.
     */
    public function test_orphan_trades_are_surfaced_rather_than_hidden(): void
    {
        $orphan = Trade::create([
            'slug' => 'orphelin', 'code' => 'ORPH', 'name' => 'Métier orphelin', 'is_active' => true,
        ]);

        $component = Livewire::test(CatalogCenter::class, $this->contexteCatalogue());

        $this->assertTrue($component->instance()->orphanTrades()->contains('id', $orphan->id));
        $component->assertSee('rattaché(s) à aucun secteur');
    }

    /** Rattacher un orphelin le fait entrer dans le parcours. */
    public function test_attaching_an_orphan_puts_it_in_the_journey(): void
    {
        $orphan = Trade::create([
            'slug' => 'orphelin', 'code' => 'ORPH', 'name' => 'Métier orphelin', 'is_active' => true,
        ]);
        $sector = Sector::where('slug', 'nettoyage')->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())->call('attachTrade', $orphan->id, $sector->id);

        $this->assertSame($sector->id, $orphan->fresh()->sector_id);
    }

    /** Une couleur mal saisie casserait le carrousel en silence : elle est validée. */
    public function test_a_malformed_accent_colour_is_refused(): void
    {
        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())
            ->call('startNewSector')
            ->set('sectorForm.name', 'Test')
            ->set('sectorForm.accent_color', 'bleu')
            ->call('saveSector')
            ->assertHasErrors('sectorForm.accent_color');
    }

    public function test_the_name_suggests_a_slug(): void
    {
        Livewire::test(CatalogCenter::class, $this->contexteCatalogue())
            ->call('startNewSector')
            ->set('sectorForm.name', 'Espaces Verts & Jardins')
            ->assertSet('sectorForm.slug', 'espaces-verts-jardins');
    }

    /** Archiver un secteur annonce son impact avant d'agir, et épargne ses métiers. */
    public function test_archiving_a_sector_announces_its_impact_first(): void
    {
        $sector = Sector::where('slug', 'nettoyage')->firstOrFail();
        $tradeIds = $sector->trades()->pluck('id');

        $component = Livewire::test(CatalogCenter::class, $this->contexteCatalogue())->call('confirmArchiveSector', $sector->id);

        $this->assertSame(3, $component->instance()->archiveImpact['children_count']);
        $this->assertNotNull(Sector::find($sector->id), 'Le secteur a été archivé avant confirmation.');

        $component->call('archiveSector');

        $this->assertNull(Sector::find($sector->id));
        $this->assertSame(3, Trade::whereIn('id', $tradeIds)->count(), 'Les métiers ont été emportés.');
    }

    // ─── Statistiques d'abandon ──────────────────────────────────────────────────────────────

    /**
     * L'ABANDON se distingue du simple non-réponse.
     *
     * Un client qui saute une question facultative puis en répond une suivante n'a rien abandonné.
     * Celui dont la dernière réponse est la question 2, sur une commande jamais confirmée, s'est
     * arrêté là — et c'est celui-là qui coûte.
     */
    public function test_skipping_a_question_is_not_dropping_out(): void
    {
        $trade = $this->peinture();

        // Ce client saute « etendue » mais continue jusqu'à « etat_support ».
        $this->draftAnswering($trade, ['surface_m2', 'etat_support']);

        $rows = app(QuestionInsights::class)->forTrade($trade)->keyBy('code');

        $this->assertSame(0, $rows['etendue']['answered']);
        $this->assertSame(0, $rows['etendue']['dropped_here'], 'Une question sautée compte comme un abandon.');
        $this->assertSame(1, $rows['etat_support']['dropped_here']);
    }

    /** Une commande confirmée n'a rien abandonné : elle a fini. */
    public function test_a_converted_order_counts_as_no_drop_out(): void
    {
        $trade = $this->peinture();
        $draft = $this->draftAnswering($trade, ['surface_m2']);
        $draft->update(['status' => OrderDraftStatus::CONVERTED]);

        $rows = app(QuestionInsights::class)->forTrade($trade)->keyBy('code');

        $this->assertSame(1, $rows['surface_m2']['answered']);
        $this->assertSame(0, $rows['surface_m2']['dropped_here']);
    }

    /**
     * Le volume compte autant que le taux.
     *
     * Un abandon sur deux commandes ne dit rien : afficher « 50 % » dessus ferait supprimer une
     * question parfaitement saine.
     */
    public function test_a_tiny_sample_never_accuses_a_question(): void
    {
        $trade = $this->peinture();
        $this->draftAnswering($trade, ['surface_m2']);

        $this->assertTrue(app(QuestionInsights::class)->worstOffenders($trade)->isEmpty());
    }

    /** Au-delà du seuil ET du volume, la question est nommée. */
    public function test_a_question_that_really_loses_clients_is_named(): void
    {
        $trade = $this->peinture();

        foreach (range(1, 25) as $i) {
            $this->draftAnswering($trade, ['surface_m2'], 'jeton-'.$i);
        }

        $worst = app(QuestionInsights::class)->worstOffenders($trade);

        $this->assertSame('surface_m2', $worst->first()['code']);
        $this->assertGreaterThan(0.9, $worst->first()['drop_rate']);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** @param  list<string>  $codes */
    private function draftAnswering(Trade $trade, array $codes, string $token = 'jeton'): OrderDraft
    {
        $draft = OrderDraft::create([
            'reference' => OrderDraft::generateReference(),
            'session_token' => $token,
            'mode' => OrderMode::SCHEDULED,
            'status' => OrderDraftStatus::DRAFT,
        ]);

        $item = OrderDraftItem::create(['order_draft_id' => $draft->id, 'trade_id' => $trade->id]);

        foreach ($codes as $code) {
            $question = $trade->questions()->where('code', $code)->firstOrFail();

            OrderDraftAnswer::create([
                'order_draft_item_id' => $item->id,
                'question_id' => $question->id,
                'question_code' => $code,
                'question_label_snapshot' => $question->label,
                'answer_label_snapshot' => 'une réponse',
            ]);
        }

        return $draft;
    }
}
