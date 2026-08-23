<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LE PANNEAU DE COMMUNICATION NE PROMET QUE CE QU'IL PEUT TENIR. */
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

    /** La carte « Notifications » visait `notifications` quand la route s'appelle `notifications.index` : elle n'a JAMAIS été rendue, sur aucune page, pour aucun rôle. */
    public function test_la_carte_notifications_est_rendue_pour_tous_les_roles(): void
    {
        $manquants = [];

        foreach (['admin', 'client', 'employe'] as $role) {
            $html = $this->rendu(User::factory()->{$role}()->create());

            if (! str_contains($html, route('notifications.index'))) {
                $manquants[] = $role;
            }
        }

        $this->assertSame([], $manquants, 'La carte Notifications manque a ces roles.');
    }
}
