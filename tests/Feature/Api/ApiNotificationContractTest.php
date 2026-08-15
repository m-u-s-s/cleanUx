<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * LE CONTRAT ENTRE L'API ET LES APPLICATIONS NATIVES.
 *
 * Les deux écrans natifs affichent `item.title` et `item.body`. `serialize()` ne rendait que
 * `id`, `type`, `data`, `read_at` et `created_at` : la liste chargeait bien ses lignes et les
 * rendait TOUTES VIDES — deux `Text` sans contenu au-dessus d'une date. « Les notifications ne
 * s'affichent pas » ne venait pas d'un appel raté mais d'un contrat jamais tenu.
 *
 * Rien ne pouvait le voir : TypeScript décrit une intention, pas une réponse HTTP, et le test
 * d'écran existant montait la liste avec un tableau VIDE — il ne rendait donc jamais une ligne.
 * Un champ que personne n'assertit peut disparaître sans bruit ; ce test l'assertit.
 */
class ApiNotificationContractTest extends TestCase
{
    use RefreshDatabase;

    private function notifierA(User $user, array $payload, string $classe = 'App\\Notifications\\RappelRdv'): DatabaseNotification
    {
        $notification = new DatabaseNotification([
            'id' => (string) Str::uuid(),
            'type' => $classe,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => $payload,
            'read_at' => null,
        ]);

        $notification->save();

        return $notification;
    }

    public function test_la_liste_rend_les_champs_que_les_applications_natives_affichent(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $this->notifierA($prestataire, [
            'type' => 'finance',
            'title' => 'Virement reçu',
            'message' => 'Paiement de 168,00 € crédité.',
            'invoice_number' => 'FA-2026-0088',
        ], 'App\\Notifications\\FinanceVirementRecu');

        $reponse = $this->getJson('/api/notifications');

        $reponse->assertOk()
            ->assertJsonPath('data.0.title', 'Virement reçu')
            ->assertJsonPath('data.0.body', 'Paiement de 168,00 € crédité.')
            ->assertJsonPath('data.0.label', 'Finance')
            ->assertJsonPath('data.0.type_key', 'finance')
            ->assertJsonPath('data.0.severity', 'warning')
            ->assertJsonPath('data.0.context.invoice_number', 'FA-2026-0088')
            ->assertJsonPath('data.0.action_label', __('ui.notifications.action.finance'))
            ->assertJsonPath('data.0.action_url', route('employe.wallet'))
            // Le chemin, séparément : c'est lui que l'hôte WebView attend.
            ->assertJsonPath('data.0.action_path', '/dashboard/employe/portefeuille');
    }

    /**
     * `type` et `data` étaient déjà consommés ailleurs : les retirer casserait un client déjà
     * installé, qui ne se met pas à jour au même rythme que le serveur.
     */
    public function test_les_champs_historiques_restent_en_place(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $this->notifierA($prestataire, ['message' => 'Coucou'], 'App\\Notifications\\RappelRdv');

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'RappelRdv')
            ->assertJsonPath('data.0.data.message', 'Coucou')
            ->assertJsonStructure(['data' => [['id', 'read_at', 'created_at']]]);
    }

    /**
     * `action_path` reste VIDE hors du domaine de l'application : embarquer une page qui n'est pas
     * à nous dans l'hôte WebView, qui porte la session, serait pire qu'un lien manquant.
     */
    public function test_une_cible_externe_ne_produit_pas_de_chemin_embarquable(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $this->notifierA($prestataire, [
            'message' => 'Documentation',
            'action_url' => 'https://exemple-externe.test/aide',
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.action_url', 'https://exemple-externe.test/aide')
            ->assertJsonPath('data.0.action_path', '');
    }

    public function test_la_fiche_rend_la_notification_complete(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $notification = $this->notifierA($prestataire, [
            'type' => 'urgent',
            'title' => 'Client bloqué sur place',
            'message' => 'Accès impossible.',
            'rdv_id' => 5120,
        ], 'App\\Notifications\\UrgenceSurSite');

        $this->getJson('/api/notifications/'.$notification->id)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.title', 'Client bloqué sur place')
            ->assertJsonPath('data.severity', 'danger')
            ->assertJsonPath('data.context.rdv_id', 5120);
    }

    /**
     * UN GET NE MODIFIE PAS L'ÉTAT.
     *
     * La lecture se pose par `POST /notifications/{id}/read`, que la fiche native appelle à
     * l'ouverture. Si `show` marquait lue au passage, un simple rafraîchissement de cache ou une
     * pré-lecture ferait disparaître le compteur sans que personne n'ait rien lu.
     */
    public function test_consulter_la_fiche_ne_marque_pas_lue(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $notification = $this->notifierA($prestataire, ['message' => 'À lire']);

        $this->getJson('/api/notifications/'.$notification->id)->assertOk();

        $this->assertNull($notification->fresh()?->read_at);

        // Et le geste explicite, lui, fonctionne — sinon le refus ci-dessus mesurerait une panne.
        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertOk();
        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_la_fiche_d_autrui_est_introuvable(): void
    {
        $sofia = User::factory()->employe()->create();
        $autre = User::factory()->employe()->create();

        $sienne = $this->notifierA($sofia, ['message' => 'À moi']);
        $celleDeLAutre = $this->notifierA($autre, ['message' => 'Pas à moi']);

        Sanctum::actingAs($sofia);

        // Témoin : sans ce cas, une route cassée qui rendrait 404 pour tout le monde passerait au
        // vert en mesurant une panne.
        $this->getJson('/api/notifications/'.$sienne->id)->assertOk();

        $this->getJson('/api/notifications/'.$celleDeLAutre->id)->assertNotFound();
        $this->postJson('/api/notifications/'.$celleDeLAutre->id.'/read')->assertNotFound();

        $this->assertNull($celleDeLAutre->fresh()?->read_at);
    }

    /**
     * `read-all` est déclarée AVANT `{id}` : dans l'autre ordre, la chaîne « read-all » serait
     * prise pour un identifiant de notification et l'endpoint deviendrait un 404.
     */
    public function test_read_all_n_est_pas_avale_par_la_route_a_parametre(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $this->notifierA($prestataire, ['message' => 'Une']);
        $this->notifierA($prestataire, ['message' => 'Deux']);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked_as_read', 2)
            ->assertJsonPath('unread_count', 0);
    }
}
