<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retrouver son panier quand le cookie a disparu.
 *
 * Le panier vit en base, retrouvé par un jeton de session. Effacer ses cookies, ou une session
 * expirée, le rendait introuvable — alors qu'il est toujours là, avec ses réponses et son prix.
 *
 * CE QU'ON NE MET PAS DANS `localStorage`. Le jeton de session ouvre un panier contenant l'adresse
 * du domicile de quelqu'un, et le cookie qui le porte est `httpOnly` : aucune XSS ne le lit. Le
 * recopier le rendrait lisible par n'importe quel script injecté, pour toujours.
 *
 * La clé de rattrapage a donc trois limites que le jeton n'a pas : hachée au repos, tournante à
 * chaque usage, et expirante. Ces trois-là sont testées ici — sans elles, ce serait le jeton de
 * session sous un autre nom.
 */
class DraftRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_a_key_brings_the_basket_back(): void
    {
        [$draft, $key] = $this->basketWithKey();

        $recovered = app(OrderDraftManager::class)->recoverByKey($key);

        $this->assertNotNull($recovered, 'Le panier n’a pas été retrouvé.');
        $this->assertSame($draft->id, $recovered->id);
    }

    /**
     * LA CLÉ EST HACHÉE. Une fuite de la base ne donne aucune clé utilisable.
     */
    public function test_the_key_is_never_stored_in_the_clear(): void
    {
        [$draft, $key] = $this->basketWithKey();

        $stored = $draft->fresh()->recovery_key_hash;

        $this->assertNotSame($key, $stored);
        $this->assertSame(hash('sha256', $key), $stored);
    }

    /**
     * ELLE TOURNE À CHAQUE USAGE.
     *
     * Une clé volée ne sert donc qu'une fois. C'est ce qui distingue ce rattrapage d'un second
     * jeton de session permanent posé à la portée de toute XSS.
     */
    public function test_the_key_rotates_and_the_old_one_dies(): void
    {
        [, $key] = $this->basketWithKey();
        $manager = app(OrderDraftManager::class);

        $manager->recoverByKey($key);

        $this->assertNull(
            $manager->recoverByKey($key),
            'L’ancienne clé fonctionne encore : une clé volée servirait indéfiniment.',
        );
    }

    /** Et la rotation rend une clé NEUVE, sinon le rattrapage ne marcherait qu'une fois. */
    public function test_recovery_hands_back_a_fresh_key(): void
    {
        [, $key] = $this->basketWithKey();
        $manager = app(OrderDraftManager::class);

        $manager->recoverByKey($key);
        $next = $manager->lastIssuedKey();

        $this->assertNotNull($next);
        $this->assertNotSame($key, $next);
        $this->assertNotNull($manager->recoverByKey($next));
    }

    /** ELLE EXPIRE : passé le délai, le panier reste en base mais n'est plus rattrapable ainsi. */
    public function test_an_expired_key_no_longer_works(): void
    {
        [$draft, $key] = $this->basketWithKey();
        $draft->update(['recovery_key_expires_at' => Carbon::now()->subMinute()]);

        $this->assertNull(app(OrderDraftManager::class)->recoverByKey($key));
    }

    /**
     * Une commande DÉJÀ PASSÉE ne se rouvre pas.
     *
     * Sans cette borne, une clé oubliée dans un navigateur rouvrirait indéfiniment une commande
     * payée, avec l'adresse qu'elle porte.
     */
    public function test_a_converted_order_is_not_recoverable(): void
    {
        [$draft, $key] = $this->basketWithKey();
        $draft->update(['status' => OrderDraftStatus::CONVERTED]);

        $this->assertNull(app(OrderDraftManager::class)->recoverByKey($key));
    }

    /** Une clé inventée ne casse rien et n'ouvre rien. */
    public function test_a_bogus_key_opens_nothing(): void
    {
        $this->basketWithKey();

        $this->assertNull(app(OrderDraftManager::class)->recoverByKey('n’importe quoi'));
    }

    /**
     * L'écran expose la clé au navigateur et sait la reprendre.
     *
     * Huitième fois que ce module produit un service sans porte : le test lit le rendu.
     */
    public function test_the_journey_hands_the_key_to_the_browser(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->html();

        $this->assertStringContainsString('recoverDraft', $html);
        $this->assertStringContainsString('cx-order-recovery', $html);
    }

    /** @return array{0: OrderDraft, 1: string} */
    private function basketWithKey(): array
    {
        $manager = app(OrderDraftManager::class);
        $draft = $manager->resumeOrCreate('jeton-'.uniqid(), null, OrderMode::SCHEDULED);
        $key = $manager->issueRecoveryKey($draft);

        return [$draft->fresh(), $key];
    }
}
