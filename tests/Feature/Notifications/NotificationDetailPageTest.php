<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LA FICHE D'UNE NOTIFICATION : tout son contenu, et où aller pour régler le problème.
 *
 * Le centre montre une ligne par notification et n'en dit qu'une partie : le payload porte des
 * références, des montants, une zone, une source, des horodatages que la liste ne peut pas
 * afficher. Cette page les rend, et surtout elle porte le lien de résolution.
 */
class NotificationDetailPageTest extends TestCase
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

    /**
     * LE CŒUR DE LA DEMANDE : la fiche montre TOUT et dit où aller.
     */
    public function test_la_fiche_rend_le_payload_complet_et_le_lien_de_resolution(): void
    {
        $prestataire = User::factory()->employe()->create();

        $notification = $this->notifierA($prestataire, [
            'type' => 'finance',
            'title' => 'Virement reçu',
            'message' => 'Paiement de 168,00 € crédité.',
            'invoice_number' => 'FA-2026-0088',
            'zone_name' => 'Bruxelles 1000',
        ], 'App\\Notifications\\FinanceVirementRecu');

        $reponse = $this->actingAs($prestataire)
            ->get(route('notifications.show', $notification->id));

        $reponse->assertOk()
            ->assertSee('Virement reçu')
            ->assertSee('Paiement de 168,00 € crédité.')
            // Les champs du payload que la liste n'affichait pas.
            ->assertSee('FA-2026-0088')
            ->assertSee('Bruxelles 1000')
            // Traçabilité : la référence et la classe d'origine.
            ->assertSee($notification->id)
            ->assertSee('FinanceVirementRecu')
            // Le lien de résolution, nommé par sa destination et écrit en clair.
            ->assertSee(route('employe.wallet'))
            ->assertSee(__('ui.notifications.action.finance'));
    }

    /**
     * Une clé inconnue de cet écran doit quand même apparaître : un tableau réduit aux champs
     * déjà connus recréerait le défaut qu'on corrige — de l'information portée par la
     * notification et invisible à l'écran.
     */
    public function test_une_cle_de_payload_inconnue_est_affichee_telle_quelle(): void
    {
        $prestataire = User::factory()->employe()->create();

        $notification = $this->notifierA($prestataire, [
            'message' => 'Contrôle qualité planifié',
            'inspecteur_nom' => 'Karim Haddad',
        ]);

        $this->actingAs($prestataire)
            ->get(route('notifications.show', $notification->id))
            ->assertOk()
            ->assertSee('inspecteur_nom')
            ->assertSee('Karim Haddad');
    }

    /**
     * L'APPARTENANCE, ET SON TÉMOIN.
     *
     * Le composant cherche la notification dans `$user->notifications()` : celle d'autrui n'existe
     * pas. Le cas d'admission est indispensable — sans lui, une page cassée qui renverrait 404
     * pour tout le monde ferait passer ce test au vert en mesurant une panne.
     */
    public function test_la_notification_d_autrui_est_introuvable(): void
    {
        $sofia = User::factory()->employe()->create();
        $autre = User::factory()->employe()->create();

        $sienne = $this->notifierA($sofia, ['message' => 'À moi']);
        $celleDeLAutre = $this->notifierA($autre, ['message' => 'Pas à moi']);

        $this->actingAs($sofia)
            ->get(route('notifications.show', $sienne->id))
            ->assertOk();

        $this->actingAs($sofia)
            ->get(route('notifications.show', $celleDeLAutre->id))
            ->assertNotFound();

        // Et rien n'a fuité : la notification d'autrui n'a pas été marquée comme lue au passage.
        $this->assertNull($celleDeLAutre->fresh()?->read_at);
    }

    public function test_un_identifiant_inexistant_rend_404(): void
    {
        $prestataire = User::factory()->employe()->create();

        $this->actingAs($prestataire)
            ->get(route('notifications.show', (string) Str::uuid()))
            ->assertNotFound();
    }

    /**
     * Ouvrir vaut lecture : sans cela le compteur ne redescendrait qu'à la main.
     */
    public function test_ouvrir_la_fiche_marque_la_notification_comme_lue(): void
    {
        $prestataire = User::factory()->employe()->create();
        $notification = $this->notifierA($prestataire, ['message' => 'À lire']);

        $this->assertNull($notification->read_at);
        $this->assertSame(1, $prestataire->unreadNotifications()->count());

        $this->actingAs($prestataire)
            ->get(route('notifications.show', $notification->id))
            ->assertOk();

        $this->assertNotNull($notification->fresh()?->read_at);
        $this->assertSame(0, $prestataire->unreadNotifications()->count());
    }

    /**
     * LA CARTE MÈNE À LA FICHE, PAS DIRECTEMENT À LA RÉSOLUTION.
     *
     * La liste pointait droit sur `actionUrl()` : on quittait le centre sans jamais voir le
     * contenu complet de la notification. Une seule destination par carte, et c'est la fiche.
     */
    public function test_le_centre_pointe_vers_la_fiche(): void
    {
        $prestataire = User::factory()->employe()->create();
        $notification = $this->notifierA($prestataire, ['message' => 'Mission demain']);

        $this->actingAs($prestataire)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(route('notifications.show', $notification->id));
    }
}
