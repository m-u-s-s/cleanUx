<?php

namespace Tests\Feature\Navigation;

use App\Support\Navigation\ModuleCatalogue;
use Tests\TestCase;

/** CHAQUE RÔLE DOIT DISPOSER DES MODULES DONT IL A BESOIN. */
class ModulesEssentielsParRoleTest extends TestCase
{
    private const CONTEXTES = ['client', 'employe', 'admin', 'client-company', 'provider-company'];

    /** Ce qu'un compte doit atteindre quel que soit son rôle. */
    private const TRANSVERSAUX = ['profile.show', 'notifications.index', 'help.center', 'terms.show'];

    /**
     * Ce que chaque famille de contextes doit porter EN PLUS des transversaux.
     *
     * @var array<string, list<string>>
     */
    private const PROPRES = [
        'employe' => ['provider.onboarding', 'presence.me'],
        'provider-company' => ['provider.onboarding', 'presence.me'],
        'client' => ['booking.create'],
        'client-company' => ['booking.create'],
    ];

    /** @return list<string> */
    private function routesDe(string $contexte): array
    {
        return ModuleCatalogue::pourContexte($contexte)
            ->flatMap(fn (array $groupe) => array_column($groupe['modules'], 'route'))
            ->all();
    }

    /** TÉMOIN — le catalogue rend bien quelque chose pour chaque contexte. */
    public function test_temoin_chaque_contexte_expose_des_modules(): void
    {
        $vides = [];

        foreach (self::CONTEXTES as $contexte) {
            $n = count($this->routesDe($contexte));

            if ($n < 5) {
                $vides[] = sprintf('%s → %d module(s)', $contexte, $n);
            }
        }

        $this->assertSame([], $vides, 'Ces contextes n’exposent presque rien : la mesure ne prouverait rien.');
    }

    public function test_chaque_role_dispose_de_ses_modules(): void
    {
        $manques = [];

        foreach (self::CONTEXTES as $contexte) {
            $routes = $this->routesDe($contexte);
            $attendues = array_merge(self::TRANSVERSAUX, self::PROPRES[$contexte] ?? []);

            foreach ($attendues as $route) {
                if (! in_array($route, $routes, true)) {
                    $manques[] = sprintf('%s → %s', $contexte, $route);
                }
            }
        }

        $this->assertSame([], $manques, 'Ces modules manquent à ces rôles.');
    }

    public function test_aucun_contexte_ne_laisse_une_categorie_essentielle_vide(): void
    {
        // Trois catégories doivent être servies partout : qui je suis (`comptes`), ce qu'on me dit (`communication`), et les textes qui m'engagent (`plateforme`).
        $manques = [];

        foreach (self::CONTEXTES as $contexte) {
            $categories = ModuleCatalogue::pourContexte($contexte)->pluck('category')->all();

            foreach (['comptes', 'communication', 'plateforme'] as $essentielle) {
                if (! in_array($essentielle, $categories, true)) {
                    $manques[] = sprintf('%s → rien en « %s »', $contexte, $essentielle);
                }
            }
        }

        $this->assertSame([], $manques, 'Ces catégories sont vides pour ces contextes.');
    }

    public function test_les_modules_transversaux_ne_sont_declares_qu_une_fois(): void
    {
        // Ils portent le contexte `*`. Les recopier dans les cinq contextes serait cinq occasions
        // d'en oublier un — c'est exactement ce que le registre unique a supprimé.
        $partages = collect(config('modules.catalogue'))->where('context', '*');

        $this->assertGreaterThan(0, $partages->count(), 'Aucun module transversal déclaré.');

        $doublons = $partages->pluck('route')
            ->countBy()
            ->filter(fn (int $n) => $n > 1)
            ->keys()
            ->all();

        $this->assertSame([], $doublons, 'Ces modules transversaux sont déclarés plusieurs fois.');
    }
}
