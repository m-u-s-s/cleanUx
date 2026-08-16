<?php

namespace Tests\Feature\Api\Auth;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UNE SUSPENSION VAUT SUR LES DEUX SURFACES.
 *
 * Mesuré le 2026-08-16 sur la base locale : `active.account` était posé sur tous les groupes web et
 * sur AUCUNE route d'API. Un compte `is_active=false, status=suspended` obtenait un jeton NEUF par
 * `/api/auth/login`, puis gardait l'application entière — `/auth/me`, ses réservations, et pour un
 * prestataire approuvé sa boîte d'offres. Bannir quelqu'un (fraude, sécurité, impayé) ne l'arrêtait
 * que dans le navigateur, c'est-à-dire là où il n'allait pas.
 *
 * CHAQUE REFUS A SON TÉMOIN. Un test d'interdiction seul passe au vert le jour où la route casse
 * pour une autre raison : le même compte, actif, doit passer juste à côté. Sans ce contrôle
 * positif, ce fichier mesurerait une panne.
 */
class CompteSuspenduTest extends TestCase
{
    use RefreshDatabase;

    // ─── La porte d'entrée ───────────────────────────────────────────────────────────────────

    public function test_un_compte_suspendu_ne_peut_pas_se_connecter(): void
    {
        $user = $this->compte(['is_active' => false, 'status' => 'suspended']);

        $reponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $reponse->assertForbidden()->assertJsonPath('error_code', 'compte_inactif');
        $this->assertSame(0, $user->tokens()->count(), 'Un jeton a été émis à un compte suspendu.');
    }

    /** LE TÉMOIN : le même compte, actif, entre. */
    public function test_le_meme_compte_actif_se_connecte(): void
    {
        $user = $this->compte();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /**
     * Le refus ne vient pas AVANT le mot de passe.
     *
     * Sinon `/api/auth/login` répondrait « compte suspendu » à qui tape une mauvaise adresse, et
     * dirait ainsi à un inconnu quelles adresses existent — et lesquelles sont bannies.
     */
    public function test_un_mauvais_mot_de_passe_ne_revele_pas_la_suspension(): void
    {
        $user = $this->compte(['is_active' => false, 'status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(422)->assertJsonMissing(['error_code' => 'compte_inactif']);
    }

    // ─── Les jetons déjà émis ────────────────────────────────────────────────────────────────

    /**
     * Fermer la porte ne sert à rien si la fenêtre reste ouverte : le jeton obtenu AVANT la
     * suspension doit mourir avec elle, sans attendre son expiration à trente jours.
     */
    public function test_un_jeton_emis_avant_la_suspension_ne_vaut_plus_rien(): void
    {
        $user = $this->compte();
        $jeton = $user->createToken('telephone')->plainTextToken;

        // Le témoin, d'abord : le jeton fonctionne tant que le compte est actif.
        $this->avecJeton($jeton)->getJson('/api/auth/me')->assertOk();

        $this->suspendre($user);

        $this->avecJeton($jeton)->getJson('/api/auth/me')->assertUnauthorized();
    }

    /** La règle ne dépend pas de la route : elle vaut aussi sur le métier du prestataire. */
    public function test_un_prestataire_suspendu_perd_sa_boite_d_offres(): void
    {
        $user = $this->compte();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $jeton = $user->createToken('telephone')->plainTextToken;

        $this->avecJeton($jeton)->getJson('/api/provider/assignments/inbox')->assertOk();

        $this->suspendre($user);

        $this->avecJeton($jeton)->getJson('/api/provider/assignments/inbox')->assertUnauthorized();
    }

    /**
     * Le renouvellement est une porte lui aussi : sans cela un jeton suspendu se reconduirait.
     */
    public function test_un_compte_suspendu_ne_renouvelle_pas_son_jeton(): void
    {
        $user = $this->compte();
        $jeton = $user->createToken('telephone')->plainTextToken;

        $this->suspendre($user);

        $this->avecJeton($jeton)->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    // ─── Les états qui comptent comme suspendus ──────────────────────────────────────────────

    /**
     * @dataProvider etatsRefuses
     */
    public function test_les_etats_hors_service_sont_refuses(string $statut, bool $actif): void
    {
        $user = $this->compte(['is_active' => $actif, 'status' => $statut]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function etatsRefuses(): array
    {
        return [
            'suspendu' => ['suspended', true],
            'bloqué' => ['blocked', true],
            'désactivé' => ['disabled', true],
            'inactif' => ['inactive', true],
            // La colonne est libre : des seeders y ont écrit des majuscules.
            'suspendu en majuscules' => ['SUSPENDED', true],
            // Le drapeau seul suffit, sans que le statut le dise.
            'drapeau baissé, statut actif' => ['active', false],
        ];
    }

    // ─── Le web, qui marchait déjà, et ne doit pas régresser ──────────────────────────────────

    public function test_le_web_refuse_aussi_un_compte_suspendu(): void
    {
        $user = $this->compte([
            'is_active' => false,
            'status' => 'suspended',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    /**
     * `auth()->logout()` sur une requête d'API lèverait « Session store not set on request » : un
     * 500 à la place d'un 403, sur le chemin même qui refuse un compte banni.
     */
    public function test_le_refus_d_api_ne_produit_pas_une_erreur_serveur(): void
    {
        $user = $this->compte(['is_active' => false, 'status' => 'suspended']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributs */
    private function compte(array $attributs = []): User
    {
        return User::factory()->create(array_merge([
            'password' => bcrypt('password'),
            'is_active' => true,
            'status' => 'active',
        ], $attributs));
    }

    private function avecJeton(string $jeton): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$jeton);
    }

    /**
     * Suspend le compte ET oublie les gardes résolus.
     *
     * `RequestGuard` mémorise l'utilisateur dès la première résolution, et l'instance survit d'une
     * requête de test à l'autre : sans cet oubli, la seconde requête rendrait l'objet en mémoire
     * — actif — sans jamais revalider le jeton. Le test passerait au vert en production comme
     * ici, mais pour la mauvaise raison : il ne mesurerait rien. En production chaque requête part
     * d'un processus neuf, et c'est cette situation-là qu'on reproduit.
     */
    private function suspendre(User $user): void
    {
        $user->forceFill(['is_active' => false, 'status' => 'suspended'])->save();

        $this->app['auth']->forgetGuards();
    }
}
