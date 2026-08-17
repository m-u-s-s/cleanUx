<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\Providers\ExpoPushProvider;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * LE CONTRAT PUSH ÉTAIT ROMPU À SES DEUX EXTRÉMITÉS.
 *
 * `mobile/shared/src/push/hooks.ts` enregistre l'appareil avec `provider: 'expo'` — c'est ce que
 * rend `expo-notifications`. Côté serveur, DEUX barrières le refusaient : la validation du
 * contrôleur (`in:fcm,apns,mock`, réponse 422) et, plus profond, `DeviceTokenService::register()`
 * qui levait « Provider push inconnu. ». Lever l'une sans l'autre n'aurait rien changé.
 *
 * Résultat : AUCUN appareil ne s'enregistrait, donc aucune notification ne pouvait partir — alors
 * que six notifications routaient déjà par `PushChannel` et que tout le reste de la chaîne
 * fonctionnait. Une fonctionnalité entière, morte sur une ligne de validation.
 *
 * ET ACCEPTER `expo` NE SUFFISAIT PAS. Un jeton `ExponentPushToken[…]` n'est ni un jeton FCM ni un
 * jeton APNs : Expo garde la correspondance et route lui-même. Sans fournisseur dédié, on aurait
 * stocké des jetons que plus personne ne savait consommer — un enregistrement réussi, et toujours
 * pas une notification.
 *
 * CES TESTS COUVRENT LA CHAÎNE ENTIÈRE : l'enregistrement, le choix du fournisseur, et l'envoi.
 */
class ContratPushExpoTest extends TestCase
{
    use RefreshDatabase;

    private const JETON = 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]';

    // ── L'enregistrement ─────────────────────────────────────────────────

    public function test_une_application_expo_peut_enregistrer_son_appareil(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/client/devices/register', [
            'token' => self::JETON,
            'platform' => 'android',
            'provider' => 'expo',
        ])->assertSuccessful();

        $this->assertSame(1, DeviceToken::query()->where('provider', 'expo')->count());
    }

    /** L'application prestataire emprunte l'autre route, et doit passer aussi. */
    public function test_lapplication_prestataire_enregistre_aussi(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PROVIDER]));

        $this->postJson('/api/provider/devices/register', [
            'token' => self::JETON,
            'platform' => 'ios',
            'provider' => 'expo',
        ])->assertSuccessful();

        $this->assertSame(1, DeviceToken::query()->where('provider', 'expo')->count());
    }

    /**
     * TÉMOIN — la validation reste une validation.
     *
     * Sans lui, les deux tests ci-dessus passeraient au vert sur un champ devenu libre, et
     * n'importe quelle chaîne finirait en base à la place d'un fournisseur.
     */
    public function test_un_fournisseur_inconnu_reste_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/client/devices/register', [
            'token' => self::JETON,
            'platform' => 'android',
            'provider' => 'pigeon_voyageur',
        ])->assertStatus(422);

        $this->assertSame(0, DeviceToken::query()->count());
    }

    // ── Le choix du fournisseur ──────────────────────────────────────────

    /**
     * UN JETON EXPO NE DOIT PAS PARTIR CHEZ FCM.
     *
     * L'aiguillage se faisait par PLATEFORME : un jeton Expo sur Android serait allé à FCM, qui
     * l'aurait rejeté. On lit désormais le fournisseur déclaré par l'application — c'est elle qui
     * sait d'où vient son jeton.
     */
    public function test_un_jeton_expo_est_route_vers_expo(): void
    {
        config()->set('push.fcm.project_id', 'un-projet-fcm');

        $jeton = $this->jetonEnregistre('android', 'expo');

        $choisi = (new \ReflectionClass(PushService::class))->getMethod('providerFor');
        $choisi->setAccessible(true);

        $this->assertSame('expo', $choisi->invoke(app(PushService::class), $jeton)->name());
    }

    /** TÉMOIN : un jeton FCM continue d'aller chez FCM. */
    public function test_temoin_un_jeton_fcm_va_toujours_chez_fcm(): void
    {
        config()->set('push.fcm.project_id', 'un-projet-fcm');

        $jeton = $this->jetonEnregistre('android', 'fcm');

        $choisi = (new \ReflectionClass(PushService::class))->getMethod('providerFor');
        $choisi->setAccessible(true);

        $this->assertSame('fcm', $choisi->invoke(app(PushService::class), $jeton)->name());
    }

    // ── L'envoi ──────────────────────────────────────────────────────────

    public function test_un_envoi_accepte_par_expo_est_compte_comme_envoye(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-1']])]);

        $resultat = app(ExpoPushProvider::class)->send($this->requete());

        $this->assertTrue($resultat->accepted);
        $this->assertSame('ticket-1', $resultat->externalId);
    }

    /**
     * UN 200 N'EST PAS UN SUCCÈS — Expo répond toujours 200.
     *
     * Le verdict est dans `data.status`. S'y tromper ferait compter comme délivrées des
     * notifications jamais parties : exactement le défaut réparé sur les suppléments et sur le
     * règlement du temps supplémentaire.
     */
    public function test_un_200_avec_une_erreur_nest_pas_un_succes(): void
    {
        Http::fake(['exp.host/*' => Http::response([
            'data' => ['status' => 'error', 'message' => 'trop gros', 'details' => ['error' => 'MessageTooBig']],
        ])]);

        $resultat = app(ExpoPushProvider::class)->send($this->requete());

        $this->assertFalse($resultat->accepted);
        $this->assertFalse($resultat->tokenInvalid, 'Un message trop gros ne condamne pas le jeton.');
    }

    /** L'application désinstallée : ce jeton ne redeviendra jamais valide. */
    public function test_un_appareil_desinstalle_invalide_le_jeton(): void
    {
        Http::fake(['exp.host/*' => Http::response([
            'data' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ])]);

        $resultat = app(ExpoPushProvider::class)->send($this->requete());

        $this->assertFalse($resultat->accepted);
        $this->assertTrue($resultat->tokenInvalid);
    }

    /** Un jeton FCM posté par erreur est refusé sans aller-retour réseau. */
    public function test_un_jeton_qui_nest_pas_expo_est_refuse_avant_lenvoi(): void
    {
        Http::fake();

        $resultat = app(ExpoPushProvider::class)->send(new PushSendRequest(
            token: 'cXy9:APA91bF_un_jeton_fcm',
            platform: 'android',
            title: 'Titre',
            body: 'Corps',
        ));

        $this->assertFalse($resultat->accepted);
        $this->assertTrue($resultat->tokenInvalid);
        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────

    private function requete(): PushSendRequest
    {
        return new PushSendRequest(
            token: self::JETON,
            platform: 'android',
            title: 'Votre prestataire arrive',
            body: 'Il sera là dans 10 minutes.',
        );
    }

    private function jetonEnregistre(string $plateforme, string $fournisseur): DeviceToken
    {
        Sanctum::actingAs($utilisateur = User::factory()->create());

        $this->postJson('/api/client/devices/register', [
            'token' => $fournisseur === 'expo' ? self::JETON : 'cXy9:APA91bF_un_jeton_fcm',
            'platform' => $plateforme,
            'provider' => $fournisseur,
        ])->assertSuccessful();

        return DeviceToken::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
