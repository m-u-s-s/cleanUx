<?php

namespace Tests\Feature\Automation;

use App\Listeners\Automation\DeposerLaReevaluation;
use App\Models\AutomationReevaluation;
use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\DeclencheurRegistre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcouteurGeneriqueTest extends TestCase
{
    use RefreshDatabase;

    private function faux(string $cle, bool $applique, ?int $identifiant): Declencheur
    {
        return new class($cle, $applique, $identifiant) implements Declencheur
        {
            public function __construct(
                private string $cle,
                private bool $applique,
                private ?int $identifiant,
            ) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function evenement(): string
            {
                return \stdClass::class;
            }

            public function entite(): string
            {
                return 'test-entite';
            }

            public function sApplique(object $evenement): bool
            {
                return $this->applique;
            }

            public function identifiant(object $evenement): ?int
            {
                return $this->identifiant;
            }

            public function libelle(): string
            {
                return 'Un declencheur de test';
            }
        };
    }

    private function ecouteur(): DeposerLaReevaluation
    {
        return app(DeposerLaReevaluation::class);
    }

    /** TEMOIN — un declencheur applicable dont `identifiant()` rend un entier depose bien.
     *  Sans lui, le test de refus qui suit serait vert en mesurant une panne. */
    public function test_temoin_un_declencheur_applicable_avec_identifiant_depose(): void
    {
        app(DeclencheurRegistre::class)->enregistrer($this->faux('a', true, 42));

        $this->ecouteur()->handle(new \stdClass);

        $depot = AutomationReevaluation::sole();
        $this->assertSame('a', $depot->evenement);
        $this->assertSame('test-entite', $depot->entite_type);
        $this->assertSame(42, $depot->entite_id);
    }

    /** LE REFUS — un declencheur applicable dont `identifiant()` rend `null` ne depose rien :
     *  l'evenement ne designe aucune entite, la file n'a rien a reevaluer. */
    public function test_un_identifiant_nul_ne_depose_rien(): void
    {
        app(DeclencheurRegistre::class)->enregistrer($this->faux('a', true, null));

        $this->ecouteur()->handle(new \stdClass);

        $this->assertSame(0, AutomationReevaluation::count());
    }

    public function test_un_declencheur_qui_ne_s_applique_pas_ne_depose_rien(): void
    {
        app(DeclencheurRegistre::class)->enregistrer($this->faux('a', false, 42));

        $this->ecouteur()->handle(new \stdClass);

        $this->assertSame(0, AutomationReevaluation::count());
    }

    public function test_deux_declencheurs_applicables_deposent_deux_lignes(): void
    {
        app(DeclencheurRegistre::class)->enregistrer($this->faux('a', true, 1));
        app(DeclencheurRegistre::class)->enregistrer($this->faux('b', true, 2));

        $this->ecouteur()->handle(new \stdClass);

        $this->assertSame(2, AutomationReevaluation::count());
        $this->assertSame(['a', 'b'], AutomationReevaluation::orderBy('id')->pluck('evenement')->all());
    }
}
