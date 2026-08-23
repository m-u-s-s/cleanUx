<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderConfirmation;
use App\Livewire\OrderEngine\OrderJourney;
use App\Livewire\OrderEngine\QuestionRenderer;
use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Models\User;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** La passe qualité du parcours : accessibilité, performance, bout en bout. */
class OrderEngineQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Accessibilité ───────────────────────────────────────────────────────────────────────

    /** Tout champ de saisie porte un nom accessible. */
    public function test_every_input_has_an_accessible_name(): void
    {
        $html = $this->journeyHtml();

        foreach ($this->tags($html, 'input|select|textarea') as $control) {
            if (str_contains($control, 'type="hidden"')) {
                continue;
            }

            // Un champ retiré de l'arbre d'accessibilité n'a pas de nom accessible à porter — la question ne se pose pas.
            if ($this->attr($control, 'aria-hidden') === 'true') {
                $this->assertSame(
                    '-1',
                    $this->attr($control, 'tabindex'),
                    'Champ masqué aux lecteurs d’écran mais atteignable au clavier : '.mb_substr($control, 0, 120),
                );

                continue;
            }

            $id = $this->attr($control, 'id');
            $hasLabel = $id !== null && str_contains($html, 'for="'.$id.'"');

            $this->assertTrue(
                $hasLabel
                    || $this->attr($control, 'aria-label') !== null
                    || $this->attr($control, 'aria-labelledby') !== null,
                'Champ sans nom accessible : '.mb_substr($control, 0, 120),
            );
        }
    }

    /** Chaque TYPE de question porte des noms accessibles — y compris ceux qu'un écran donné n'affiche pas. */
    public function test_every_question_widget_names_its_controls(): void
    {
        $question = $this->peinture()->questions()->where('code', 'surface_m2')->firstOrFail();

        // Des bornes déclarées : c'est ce qui fait apparaître le curseur plutôt qu'un champ nu.
        $question->update(['validation' => ['min' => 10, 'max' => 300, 'unit' => 'm²', 'step' => 1]]);

        $html = Livewire::test(QuestionRenderer::class, ['question' => $question->fresh()])->html();

        $this->assertStringContainsString('type="range"', $html, 'Le curseur aurait dû être rendu.');

        // TOUS les contrôles muets, pas le premier : un écran qui en compte cinq demanderait
        // sinon cinq exécutions pour être rendu accessible.
        $muets = [];

        foreach ($this->tags($html, 'input|select|textarea') as $control) {
            if (str_contains($control, 'type="hidden"')) {
                continue;
            }

            $id = $this->attr($control, 'id');

            $nomme = ($id !== null && str_contains($html, 'for="'.$id.'"'))
                || $this->attr($control, 'aria-label') !== null
                || $this->attr($control, 'aria-labelledby') !== null;

            if (! $nomme) {
                $muets[] = mb_substr($control, 0, 120);
            }
        }

        $this->assertSame([], $muets, 'Ces contrôles ont perdu leur nom accessible.');
    }

    /** Tout bouton porte un nom accessible. */
    public function test_every_button_has_an_accessible_name(): void
    {
        $muets = [];

        foreach (['parcours' => $this->journeyHtml(), 'confirmation' => $this->confirmationHtml()] as $ecran => $html) {
            foreach ($this->buttons($html) as [$open, $inner]) {
                $nomme = trim(strip_tags($inner)) !== ''
                    || $this->attr($open, 'aria-label') !== null
                    || $this->attr($open, 'aria-labelledby') !== null;

                if (! $nomme) {
                    $muets[] = $ecran.' : '.mb_substr($open, 0, 110);
                }
            }
        }

        $this->assertSame([], $muets, 'Ces boutons sont annoncés « bouton » et rien de plus.');
    }

    /** Le prix qui bouge est annoncé. */
    public function test_the_live_price_is_announced(): void
    {
        $html = $this->journeyHtml();

        $this->assertMatchesRegularExpression(
            '/aria-live="polite"[^>]*>[\s\S]{0,600}?Votre estimation|Votre estimation[\s\S]{0,600}?aria-live="polite"/u',
            $html,
            'L’estimation doit vivre dans une région annoncée.',
        );
    }

    /** Une erreur de saisie est RATTACHÉE à son champ, pas seulement affichée à côté. */
    public function test_an_error_is_tied_to_its_field(): void
    {
        $question = $this->peinture()->questions()->where('code', 'surface_m2')->firstOrFail();

        // Une valeur hors des bornes déclarées par l'administrateur : c'est un refus, pas un plantage.
        $html = Livewire::test(QuestionRenderer::class, ['question' => $question])
            ->set('value', -50)
            ->html();

        $fieldsets = array_filter(
            $this->tags($html, 'fieldset'),
            fn (string $tag) => str_contains($tag, 'aria-invalid="true"'),
        );

        $this->assertNotEmpty($fieldsets, 'La valeur refusée aurait dû marquer le champ invalide.');

        // Tous les champs en erreur d'un coup : un formulaire qui en refuse trois demanderait
        // sinon trois exécutions pour être rendu annonçable.
        $muets = [];

        foreach ($fieldsets as $fieldset) {
            $describedBy = $this->attr($fieldset, 'aria-describedby');

            if ($describedBy === null) {
                $muets[] = 'champ en erreur sans message désigné : '.mb_substr($fieldset, 0, 90);

                continue;
            }

            $cible = trim(explode(' ', $describedBy)[1] ?? '');

            if ($cible === '' || ! str_contains($html, 'id="'.$cible.'"')) {
                $muets[] = "le message désigné « {$cible} » n'existe pas dans la page";
            }
        }

        $this->assertSame([], $muets, 'Ces champs invalides ne désignent aucun message lisible.');
    }

    /** La hiérarchie des titres ne saute pas de niveau : c'est le plan de la page pour qui l'écoute. */
    public function test_the_heading_outline_does_not_skip_levels(): void
    {
        // TOUS les sauts de niveau, sur les deux écrans : un plan mal formé en compte
        // généralement plusieurs, et corriger le premier ne dit rien des suivants.
        $sauts = [];

        foreach (['parcours' => $this->journeyHtml(), 'confirmation' => $this->confirmationHtml()] as $ecran => $html) {
            preg_match_all('/<h([1-6])\b/', $html, $matches);
            $niveaux = array_map('intval', $matches[1]);

            $precedent = null;

            foreach ($niveaux as $niveau) {
                if ($precedent !== null && $niveau > $precedent + 1) {
                    $sauts[] = sprintf('%s : h%d après h%d', $ecran, $niveau, $precedent);
                }

                $precedent = $niveau;
            }
        }

        $this->assertSame([], $sauts, 'Le plan de la page saute des niveaux : illisible pour qui l’écoute.');
    }

    /** Aucun `tabindex` positif. */
    public function test_no_positive_tabindex_hijacks_the_keyboard(): void
    {
        foreach ([$this->journeyHtml(), $this->confirmationHtml()] as $html) {
            $this->assertDoesNotMatchRegularExpression('/tabindex="[1-9]/', $html);
        }
    }

    /** Le glisser-déposer n'est jamais le SEUL moyen de réordonner. */
    public function test_drag_and_drop_is_never_the_only_way(): void
    {
        $builder = file_get_contents(resource_path('views/livewire/admin/order-engine/questionnaire-builder.blade.php'));
        $bundle = file_get_contents(resource_path('views/livewire/order-engine/partials/bundle.blade.php'));
        // Le catalogue s'est ajouté à la liste : il règle l'ordre du carrousel et du dock, et c'est
        // un écran de travail quotidien — l'exclure du clavier l'excluerait d'un usage réel.
        $catalog = file_get_contents(resource_path('views/livewire/admin/order-engine/catalog-center.blade.php'));

        // Trois vues fois trois marqueurs : neuf verifications, et une assertion par tour n'en
        // rapportait qu'une. Un reordonnancement inaccessible l'est souvent sur les trois ecrans.
        $manques = [];

        foreach (['constructeur' => $builder, 'panier' => $bundle, 'catalogue' => $catalog] as $nom => $view) {
            foreach (['draggable="true"', 'aria-label="Monter"', 'aria-label="Descendre"'] as $marqueur) {
                if (! str_contains($view, $marqueur)) {
                    $manques[] = "{$nom} : {$marqueur}";
                }
            }
        }

        $this->assertSame([], $manques, 'Ces vues n offrent pas de reordonnancement accessible au clavier.');
    }

    // ─── Performance ─────────────────────────────────────────────────────────────────────────

    /** Le parcours ne part pas en requête par question. */
    public function test_the_journey_stays_within_its_query_budget(): void
    {
        $trade = $this->peinture();

        // On mesure une VRAIE requête HTTP, pas une séquence du harnais Livewire.
        $count = $this->countQueries(function () use ($trade) {
            $this->get(route('order.journey', [$trade->sector?->slug, $trade->slug]))->assertOk();
        });

        $this->assertLessThan(
            45,
            $count,
            sprintf('Le parcours a exécuté %d requêtes : une boucle interroge sans doute la base par ligne.', $count),
        );
    }

    /** Le récapitulatif non plus : c'est l'écran où le client décide, il doit s'ouvrir vite. */
    public function test_the_confirmation_screen_stays_within_its_query_budget(): void
    {
        $this->preparedBasket();

        $count = $this->countQueries(function () {
            $this->get(route('order.confirmation'))->assertOk();
        });

        $this->assertLessThan(45, $count, sprintf('Le récapitulatif a exécuté %d requêtes.', $count));
    }

    /** Ajouter une question ne doit pas ajouter de requêtes. */
    public function test_more_questions_do_not_mean_more_queries(): void
    {
        $trade = $this->peinture();

        $url = route('order.journey', [$trade->sector?->slug, $trade->slug]);
        $before = $this->countQueries(fn () => $this->get($url)->assertOk());

        // On triple le questionnaire par le chemin de production.
        foreach ($trade->questions()->get() as $question) {
            foreach (['bis', 'ter'] as $suffix) {
                $copy = $question->replicate(['code']);
                $copy->code = $question->code.'_'.$suffix;
                $copy->save();
            }
        }

        $after = $this->countQueries(fn () => $this->get($url)->assertOk());

        $this->assertLessThanOrEqual(
            $before + 5,
            $after,
            sprintf('%d requêtes pour %d questions contre %d avant : le coût suit le nombre de lignes.', $after, $trade->questions()->count(), $before),
        );
    }

    // ─── Bout en bout ────────────────────────────────────────────────────────────────────────

    /** Le parcours complet, du secteur à la réservation — sans compte jusqu'au dernier écran. */
    public function test_the_whole_journey_from_sector_to_booking(): void
    {
        $trade = $this->peinture();

        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')->andReturn(new GeocodingResult(50.8467, 4.3525, 'Bruxelles'));
        });

        // 1. Un visiteur ANONYME compose sa commande et voit son prix.
        $journey = Livewire::test(OrderJourney::class)
            ->call('selectSector', $trade->sector_id)
            ->call('selectTrade', $trade->id);

        $journey->call('recordAnswer', 'surface_m2', 40, true)
            ->call('recordAnswer', 'etendue', 'murs_plafonds', true)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles');

        $quote = $journey->get('quote');
        $this->assertNotNull($quote, 'Le prix doit exister AVANT toute identité.');
        $this->assertGreaterThan(0, $quote->minCents);
        $this->assertFalse(auth()->check(), 'Rien dans le parcours ne doit exiger un compte.');

        // 2. Le panier a survécu en base, rattaché à un jeton de session.
        $draft = OrderDraft::firstOrFail();
        $this->assertNull($draft->client_id);
        $this->assertSame(1, $draft->items()->count());

        // 3. Le client crée son compte au dernier moment, et retrouve son panier.
        $client = User::factory()->client()->create();
        $confirmation = Livewire::actingAs($client)->test(OrderConfirmation::class);
        $confirmation->assertSee('Total estimé');

        // 4. Il confirme : la commande devient une réservation.
        $confirmation->call('confirm');

        $this->assertSame(1, Booking::count());
        $booking = Booking::firstOrFail();
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame(OrderDraftStatus::CONVERTED, $draft->fresh()->status);

        // 5. Et le devis reste explicable ligne par ligne.
        $this->assertNotEmpty($booking->pricing_snapshot['lines']);
        $this->assertSame($draft->reference, $booking->pricing_snapshot['order_draft_reference']);
    }

    /** Fermer l'onglet au milieu ne perd rien : le panier revient tel quel. */
    public function test_leaving_and_coming_back_loses_nothing(): void
    {
        $trade = $this->peinture();

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->call('recordAnswer', 'surface_m2', 55, true);

        // Nouveau composant, même session : c'est le retour du client.
        $again = Livewire::test(OrderJourney::class)->call('selectTrade', $trade->id);

        $this->assertSame(55, (int) $again->get('answers')['surface_m2']);
    }

    // ─── Outillage ───────────────────────────────────────────────────────────────────────────

    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $action();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return list<string> les balises ouvrantes correspondantes */
    private function tags(string $html, string $names): array
    {
        preg_match_all('/<(?:'.$names.')\b[^>]*>/i', $html, $matches);

        return $matches[0];
    }

    /** @return list<array{0: string, 1: string}> couples (balise ouvrante, contenu) */
    private function buttons(string $html): array
    {
        preg_match_all('/(<button\b[^>]*>)([\s\S]*?)<\/button>/i', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m) => [$m[1], $m[2]], $matches);
    }

    private function attr(string $tag, string $name): ?string
    {
        return preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $m) ? $m[1] : null;
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function journeyHtml(): string
    {
        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')->andReturn(new GeocodingResult(50.8467, 4.3525, 'Bruxelles'));
        });

        return Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->call('recordAnswer', 'surface_m2', 40, true)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles')
            ->html();
    }

    private function confirmationHtml(): string
    {
        $client = $this->preparedBasket();

        return Livewire::actingAs($client)->test(OrderConfirmation::class)->html();
    }

    private function preparedBasket(): User
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-qualite');
        $draft->update([
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
            'scheduled_at' => Carbon::parse('2026-09-02 09:00:00'),
        ]);

        $trade = $this->peinture();
        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        session()->put('order_draft_token', 'jeton-qualite');

        return $client;
    }
}
