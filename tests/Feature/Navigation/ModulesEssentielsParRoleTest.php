<?php

namespace Tests\Feature\Navigation;

use App\Support\Navigation\ModuleCatalogue;
use Tests\TestCase;

/**
 * CHAQUE RÔLE DOIT DISPOSER DES MODULES DONT IL A BESOIN.
 *
 * Le catalogue a été bâti à partir des registres de navigation, qui ne couvraient que les pages de
 * tableau de bord — `admin/*` et `dashboard/*`. Les pages TRANSVERSALES vivent ailleurs :
 * `user/profile`, `notifications`, `aide`, `legal/*`. Elles n'appartiennent à aucun tableau de
 * bord, donc elles n'appartenaient à AUCUN rôle : cinq contextes sur cinq n'avaient ni profil, ni
 * notifications, ni mentions légales dans leur page Modules.
 *
 * L'audit par catégorie le montrait sans ambiguïté : l'espace prestataire n'avait rien en
 * « Comptes », rien en « Communication », rien en « Plateforme ». Non parce que ces pages
 * n'existent pas, mais parce que le périmètre de départ les ignorait.
 */
class ModulesEssentielsParRoleTest extends TestCase
{
    private const CONTEXTES = ['client', 'employe', 'admin', 'client-company', 'provider-company'];

    /** @return list<string> */
    private function routesDe(string $contexte): array
    {
        return ModuleCatalogue::pourContexte($contexte)
            ->flatMap(fn (array $groupe) => array_column($groupe['modules'], 'route'))
            ->all();
    }

    public function test_chaque_role_dispose_des_modules_transversaux(): void
    {
        // Ce qu'un compte doit pouvoir atteindre quel que soit son rôle : qui il est, ce qu'on lui
        // a écrit, où demander de l'aide, et à quoi il a souscrit.
        foreach (self::CONTEXTES as $contexte) {
            $routes = $this->routesDe($contexte);

            foreach (['profile.show', 'notifications.index', 'help.center', 'terms.show'] as $essentiel) {
                $this->assertContains($essentiel, $routes, "{$essentiel} manque au contexte {$contexte}");
            }
        }
    }

    public function test_les_espaces_prestataires_disposent_de_leur_dossier_et_de_leur_presence(): void
    {
        foreach (['employe', 'provider-company'] as $contexte) {
            $routes = $this->routesDe($contexte);

            $this->assertContains('provider.onboarding', $routes, $contexte);
            $this->assertContains('presence.me', $routes, $contexte);
        }
    }

    public function test_les_espaces_clients_disposent_de_la_prise_de_rendez_vous(): void
    {
        foreach (['client', 'client-company'] as $contexte) {
            $this->assertContains('booking.create', $this->routesDe($contexte), $contexte);
        }
    }

    public function test_aucun_contexte_ne_laisse_une_categorie_essentielle_vide(): void
    {
        /*
         * Trois catégories doivent être servies partout : qui je suis (`comptes`), ce qu'on me dit
         * (`communication`), et les textes qui m'engagent (`plateforme`). Une page Modules qui n'a
         * rien à montrer dans ces trois-là n'est pas un répertoire, c'est une liste partielle.
         */
        foreach (self::CONTEXTES as $contexte) {
            $categories = ModuleCatalogue::pourContexte($contexte)->pluck('category')->all();

            foreach (['comptes', 'communication', 'plateforme'] as $essentielle) {
                $this->assertContains($essentielle, $categories, "{$contexte} n'a rien en {$essentielle}");
            }
        }
    }

    public function test_les_modules_transversaux_ne_sont_declares_qu_une_fois(): void
    {
        // Ils portent le contexte `*`. Les recopier dans les cinq contextes serait cinq occasions
        // d'en oublier un — c'est exactement ce que le registre unique a supprimé.
        $partages = collect(config('modules.catalogue'))->where('context', '*');

        $this->assertGreaterThan(0, $partages->count(), 'Aucun module transversal déclaré.');
        $this->assertSame(
            $partages->pluck('route')->unique()->count(),
            $partages->count(),
            'Un module transversal est déclaré deux fois.'
        );
    }
}
