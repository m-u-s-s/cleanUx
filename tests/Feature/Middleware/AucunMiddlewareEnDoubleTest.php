<?php

namespace Tests\Feature\Middleware;

use App\Http\Kernel;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use ReflectionClass;
use Tests\TestCase;

/**
 * Aucune pile de middlewares ne déclare deux fois la même entrée.
 *
 * Laravel dédoublonne à la résolution, donc un doublon ne coûte rien à l'exécution : il ne se
 * voit qu'ici, et il ment sur ce que traverse une requête.
 */
class AucunMiddlewareEnDoubleTest extends TestCase
{
    /**
     * Les piles déclarées par le noyau, par nom.
     *
     * @return array<string, list<string>>
     */
    private function pilesDeclarees(): array
    {
        $noyau = $this->app->make(KernelContract::class);

        $this->assertInstanceOf(Kernel::class, $noyau, 'Le noyau HTTP du projet n’est plus celui-ci.');

        $reflet = new ReflectionClass($noyau);

        /** @var list<string> $global */
        $global = $reflet->getProperty('middleware')->getValue($noyau);

        /** @var array<string, list<string>> $groupes */
        $groupes = $reflet->getProperty('middlewareGroups')->getValue($noyau);

        $piles = ['global' => $global];

        foreach ($groupes as $nom => $entrees) {
            $piles['groupe '.$nom] = $entrees;
        }

        return $piles;
    }

    /** @param list<string> $pile */
    private function doublonsDe(array $pile): array
    {
        $vus = [];
        $doublons = [];

        foreach ($pile as $entree) {
            $cle = is_string($entree) ? $entree : gettype($entree);

            if (isset($vus[$cle])) {
                $doublons[] = $cle;
            }

            $vus[$cle] = true;
        }

        return array_values(array_unique($doublons));
    }

    public function test_aucune_pile_ne_declare_deux_fois_la_meme_entree(): void
    {
        $fautives = [];

        foreach ($this->pilesDeclarees() as $nom => $pile) {
            foreach ($this->doublonsDe($pile) as $double) {
                $fautives[] = $nom.' → '.$double;
            }
        }

        sort($fautives);

        $this->assertSame([], $fautives,
            'Ces middlewares sont déclarés deux fois dans la même pile. Laravel n’en exécute qu’un : '
            .'la seconde ligne ne fait que tromper le lecteur sur ce que traverse une requête.');
    }

    /** LE TÉMOIN : le détecteur voit un doublon quand il y en a un. */
    public function test_temoin_le_detecteur_voit_un_doublon(): void
    {
        $this->assertSame(
            ['A'],
            $this->doublonsDe(['A', 'B', 'A', 'C']),
            'Le détecteur ne détecte rien : le test ci-dessus passerait au vert sans rien mesurer.'
        );

        $this->assertSame([], $this->doublonsDe(['A', 'B', 'C']));
    }

    /** Et le noyau expose bien des piles à mesurer — pas un tableau vide. */
    public function test_temoin_il_y_a_bien_des_piles_a_mesurer(): void
    {
        $piles = $this->pilesDeclarees();

        $this->assertArrayHasKey('groupe web', $piles);
        $this->assertGreaterThan(3, count($piles['groupe web']));
    }
}
