<?php

namespace Tests\Feature\Automation;

use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\DeclencheurRegistre;
use Tests\TestCase;

class DeclencheurRegistreTest extends TestCase
{
    private function faux(string $cle, string $classe, bool $applique): Declencheur
    {
        return new class($cle, $classe, $applique) implements Declencheur
        {
            public function __construct(
                private string $cle,
                private string $classe,
                private bool $applique,
            ) {}

            public function cle(): string
            {
                return $this->cle;
            }

            public function evenement(): string
            {
                return $this->classe;
            }

            public function entite(): string
            {
                return 'alerte';
            }

            public function sApplique(object $evenement): bool
            {
                return $this->applique;
            }

            public function identifiant(object $evenement): ?int
            {
                return 42;
            }

            public function libelle(): string
            {
                return 'Un declencheur';
            }
        };
    }

    /** LA CLASSE NE SUFFIT PAS. Cinq alertes partagent un evenement : c'est `sApplique`
     *  qui les separe. Sans elle, un depot partirait pour les cinq. */
    public function test_seuls_les_declencheurs_qui_s_appliquent_sont_rendus(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));
        $registre->enregistrer($this->faux('b', \stdClass::class, false));

        $trouves = $registre->pourEvenement(new \stdClass);

        $this->assertSame(['a'], array_map(fn (Declencheur $d): string => $d->cle(), $trouves));
    }

    /** TEMOIN — un declencheur qui s'applique ET dont la classe correspond EST rendu. */
    public function test_temoin_un_declencheur_applicable_est_rendu(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));

        $this->assertCount(1, $registre->pourEvenement(new \stdClass));
    }

    public function test_une_classe_d_evenement_differente_n_est_jamais_rendue(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \RuntimeException::class, true));

        $this->assertSame([], $registre->pourEvenement(new \stdClass));
    }

    public function test_le_registre_retrouve_un_declencheur_par_sa_cle(): void
    {
        $registre = new DeclencheurRegistre;
        $registre->enregistrer($this->faux('a', \stdClass::class, true));

        $this->assertNotNull($registre->trouver('a'));
        $this->assertNull($registre->trouver('inconnu'));
    }
}
