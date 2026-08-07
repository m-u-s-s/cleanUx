<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LA CLOCHE MONTRE LES MESSAGES SANS QU'ON CHANGE DE PAGE.
 *
 * La navbar portait un bouton « 🔔 Notifications » avec sa pastille : il fallait quitter sa page
 * pour savoir ce qu'il y avait dedans. La cloche seule suffit, et le survol donne l'aperçu.
 */
class ClocheDeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function donnerUneNotification(User $utilisateur, string $message): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => $utilisateur::class,
            'notifiable_id' => $utilisateur->id,
            'data' => json_encode(['message' => $message, 'type' => 'test']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_le_panneau_affiche_les_messages_non_lus(): void
    {
        $client = User::factory()->client()->create();
        $this->donnerUneNotification($client, 'Votre prestataire est en route');
        $this->donnerUneNotification($client, 'Votre facture de mars est disponible');

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertOk();
        // Le panneau est rendu côté serveur et masqué par le survol : son contenu doit donc être
        // présent dans la page, sans quoi il n'y aurait rien à révéler.
        $reponse->assertSee('Votre prestataire est en route');
        $reponse->assertSee('Votre facture de mars est disponible');
    }

    public function test_ne_montre_jamais_les_notifications_d_un_autre_compte(): void
    {
        $client = User::factory()->client()->create();
        $autre = User::factory()->client()->create();
        $this->donnerUneNotification($autre, 'Message strictement privé');

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertDontSee('Message strictement privé');
    }

    public function test_la_pastille_compte_les_non_lues(): void
    {
        $client = User::factory()->client()->create();
        $this->donnerUneNotification($client, 'Une');
        $this->donnerUneNotification($client, 'Deux');
        $this->donnerUneNotification($client, 'Trois');

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertSee('data-cloche-compteur="3"', false);
    }

    public function test_le_panneau_le_dit_quand_il_n_y_a_rien(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertSee('Aucune notification');
    }

    public function test_la_cloche_reste_un_lien_vers_la_page_complete(): void
    {
        // Le panneau est un aperçu : cinq messages au plus. La page complète reste la seule à tout
        // montrer, et le survol ne doit pas la remplacer.
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertSee(route('notifications.index'), false);
    }

    public function test_la_cloche_sans_libelle_reste_annoncee_aux_lecteurs_d_ecran(): void
    {
        /*
         * La cloche n'a plus de texte à côté d'elle : sans `aria-label`, elle deviendrait un lien
         * muet, annoncé « lien » et rien d'autre. Le compte y figure aussi — une pastille rouge ne
         * se lit pas à la voix.
         *
         * La barre MOBILE garde, elle, sa ligne « Notifications » en toutes lettres : il n'y a pas
         * de survol sur un écran tactile, et un panneau qu'on ne peut pas ouvrir ne vaut rien.
         */
        $client = User::factory()->client()->create();
        $this->donnerUneNotification($client, 'Une seule');

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertSee('aria-label="Notifications (1 non lue)"', false);
    }

    public function test_l_annonce_s_accorde_au_pluriel(): void
    {
        $client = User::factory()->client()->create();
        $this->donnerUneNotification($client, 'Une');
        $this->donnerUneNotification($client, 'Deux');

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertSee('aria-label="Notifications (2 non lues)"', false);
    }
}
