<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE PANNEAU DE COMMUNICATION NE PROMET QUE CE QU'IL PEUT TENIR.
 *
 * Deux défauts vivaient ensemble dans `livewire.shared.communication.quick-links`, constatés à
 * l'écran le 2026-08-15 sur /notifications avec un compte prestataire :
 *
 *   1. La visibilité se décidait sur `Route::has()`, qui dit que la porte EXISTE, pas qu'on a la
 *      clé. « Alertes admin » et « Emails produit » s'affichaient pour tout le monde alors que
 *      `admin.alerts` / `admin.emails` portent `CheckRole:admin` : un clic menait à une page 403
 *      nue, sans navigation ni retour.
 *   2. Deux entrées visaient un nom qui n'existe pas — `notifications` au lieu de
 *      `notifications.index`, `client.litiges` au lieu de `client.claims`. Le même `Route::has()`
 *      les avalait en silence : cinq destinations déclarées, trois rendues, dont deux interdites.
 *
 * Pourquoi `ToutePageEstAtteignableTest::test_aucun_garde_route_has_ne_vise_une_route_fantome`
 * ne l'a pas vu : son expression cherche `Route::has('nom-littéral')`. Ici l'appel s'écrit
 * `Route::has($link['route'])` — les noms vivent dans un tableau au-dessus, invisibles à une
 * recherche de littéral. Un garde textuel ne peut pas suivre une table de données ; il faut
 * RENDRE le panneau pour le savoir.
 *
 * D'où ce test, qui exerce les trois rôles. Les cas d'admission (l'admin VOIT ses cartes) sont
 * aussi importants que le refus : sans eux, un panneau qui ne rendrait plus rien du tout passerait
 * au vert en mesurant une panne.
 */
class CommunicationQuickLinksTest extends TestCase
{
    use RefreshDatabase;

    private function rendu(User $utilisateur): string
    {
        $this->actingAs($utilisateur);

        return view('livewire.shared.communication.quick-links')->render();
    }

    public function test_le_prestataire_ne_voit_aucune_carte_d_administration(): void
    {
        $html = $this->rendu(User::factory()->employe()->create());

        $this->assertStringNotContainsString('Alertes admin', $html);
        $this->assertStringNotContainsString('Emails produit', $html);
        $this->assertStringNotContainsString(route('admin.alerts'), $html);
        $this->assertStringNotContainsString(route('admin.emails'), $html);
        $this->assertStringNotContainsString('Litiges client', $html);

        // Témoin : le panneau rend bien quelque chose pour ce rôle, sinon le refus ci-dessus
        // mesurerait une page vide.
        $this->assertStringContainsString('Incident terrain', $html);
        $this->assertStringContainsString(route('employe.incident'), $html);
    }

    public function test_le_client_ne_voit_ni_administration_ni_incident_terrain(): void
    {
        $html = $this->rendu(User::factory()->client()->create());

        $this->assertStringNotContainsString('Alertes admin', $html);
        $this->assertStringNotContainsString('Emails produit', $html);
        $this->assertStringNotContainsString('Incident terrain', $html);

        $this->assertStringContainsString('Litiges client', $html);
        $this->assertStringContainsString(route('client.claims'), $html);
    }

    public function test_l_administrateur_voit_ses_deux_cartes(): void
    {
        $html = $this->rendu(User::factory()->admin()->create());

        $this->assertStringContainsString(route('admin.alerts'), $html);
        $this->assertStringContainsString(route('admin.emails'), $html);
    }

    /**
     * La carte « Notifications » visait `notifications` quand la route s'appelle
     * `notifications.index` : elle n'a JAMAIS été rendue, sur aucune page, pour aucun rôle.
     */
    public function test_la_carte_notifications_est_rendue_pour_tous_les_roles(): void
    {
        foreach (['admin', 'client', 'employe'] as $role) {
            $html = $this->rendu(User::factory()->{$role}()->create());

            $this->assertStringContainsString(
                route('notifications.index'),
                $html,
                sprintf('La carte Notifications manque pour le rôle %s.', $role),
            );
        }
    }
}
