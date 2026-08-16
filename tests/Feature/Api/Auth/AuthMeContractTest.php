<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `/auth/me` et l'application mobile ne parlaient pas la même langue.
 *
 * Le serveur renvoie les attributs de l'utilisateur À PLAT — `{ id, name, email, … }` — et deux
 * tests figent cette forme. L'application, elle, lit `data.user` : à la reprise de session, elle
 * recevait `undefined`, en concluait qu'il n'y avait personne, et renvoyait vers l'écran de
 * connexion. Un jeton parfaitement valide en poche, et une reconnexion à CHAQUE lancement.
 *
 * Rien ne le signalait : le test mobile d'`AuthProvider` simule `{ user: MOCK_USER }`, une forme
 * que le serveur n'a jamais envoyée. Les deux côtés étaient verts, chacun sur sa version du
 * contrat.
 *
 * La réponse porte donc les DEUX. Retirer la forme à plat casserait les consommateurs existants ;
 * ne pas ajouter `user` laisserait l'application dehors.
 */
class AuthMeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_user_under_a_user_key_for_the_mobile_apps(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    /** Et la forme À PLAT reste, parce que des consommateurs la lisent déjà. */
    public function test_the_flat_shape_is_preserved(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('is_premium', false);
    }

    /**
     * L'ÉTAT DE VÉRIFICATION DE L'ADRESSE, DIT AUX DEUX ENDROITS.
     *
     * Le web bloque tant que l'adresse n'est pas confirmée ; l'API ne porte pas cette garde — c'est
     * un choix, l'imposer déconnecterait tout le parc déjà inscrit. Mais l'application ne pouvait
     * même pas SAVOIR : ni le dire, ni proposer de renvoyer l'e-mail, et la même personne se
     * retrouvait bloquée sans explication le jour où elle ouvrait le site.
     *
     * Les deux réponses doivent porter la MÊME clé : c'est leur divergence qui a produit, un par un,
     * tous les drapeaux d'identité de ce contrat.
     */
    public function test_les_deux_reponses_annoncent_la_verification_de_l_adresse(): void
    {
        $nonVerifie = User::factory()->client()->create([
            'email_verified_at' => null,
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $nonVerifie->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email_verified', false);

        Sanctum::actingAs($nonVerifie);
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email_verified', false)
            ->assertJsonPath('user.email_verified', false);

        $verifie = User::factory()->client()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $verifie->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email_verified', true);
    }
}
