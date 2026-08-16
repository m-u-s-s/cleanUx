<?php

namespace Tests\Feature\Auth;

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « VÉRIFIÉ PAR NOTRE ÉQUIPE AVANT D'ÊTRE ACTIVÉ » — sur les deux canaux, ou la phrase est fausse.
 *
 * Mesuré le 2026-08-16. `EnsureProviderIsApproved` ne restreint que les profils portant
 * `self_registered_at`, et seule l'inscription MOBILE la posait. Résultat, à état identique en base
 * (`provider_profiles.status = pending`) : inscrit depuis l'application → 403
 * `provider_pending_approval` ; inscrit sur le formulaire web → 200 sur la boîte d'offres, les
 * offres immédiates et les disponibilités. L'écran d'inscription web affiche pourtant cette
 * promesse, juste au-dessus du bouton.
 *
 * Deux corrections, une seule règle : l'inscription web pose la colonne, et le middleware garde
 * aussi l'espace prestataire web — en REDIRIGEANT vers le parcours de vérification plutôt qu'en
 * rendant un 403, parce que le compte n'a rien fait de mal et a un dossier à finir.
 */
class ApprobationPrestataireDeuxCanauxTest extends TestCase
{
    use RefreshDatabase;

    // ─── La colonne, posée par les deux inscriptions ─────────────────────────────────────────

    public function test_l_inscription_web_marque_le_compte_comme_auto_inscrit(): void
    {
        $this->post('/register', $this->formulaireWeb())->assertRedirect();

        $user = User::where('email', 'presta.web@test.local')->firstOrFail();
        $profil = ProviderProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull(
            $profil->self_registered_at,
            "Sans cette colonne, le compte échappe à l'attente d'approbation."
        );
        $this->assertSame('pending', $profil->status);
    }

    public function test_l_inscription_web_d_une_societe_la_marque_aussi(): void
    {
        $this->post('/register', $this->formulaireWeb([
            'account_type' => 'provider_company',
            'provider_company_name' => 'Nettoyage Web SRL',
        ]))->assertRedirect();

        $user = User::where('email', 'presta.web@test.local')->firstOrFail();

        $this->assertNotNull(
            ProviderProfile::where('user_id', $user->id)->firstOrFail()->self_registered_at
        );
    }

    // ─── L'API refuse, comme avant ───────────────────────────────────────────────────────────

    public function test_un_compte_inscrit_sur_le_web_est_refuse_par_l_api_comme_les_autres(): void
    {
        $user = $this->prestataireAutoInscrit();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'provider_pending_approval');
    }

    /** LE TÉMOIN : approuvé, le même compte passe. Sans lui ce fichier mesurerait une panne. */
    public function test_le_meme_compte_approuve_atteint_l_api(): void
    {
        $user = $this->prestataireAutoInscrit();
        ProviderProfile::where('user_id', $user->id)->update(['status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk();
    }

    // ─── Le web refuse désormais aussi ───────────────────────────────────────────────────────

    public function test_le_web_renvoie_le_compte_en_attente_vers_son_dossier(): void
    {
        $user = $this->prestataireAutoInscrit();

        $this->actingAs($user)
            ->get('/dashboard/employe/missions')
            ->assertRedirect(route('provider.onboarding'));
    }

    /** LE TÉMOIN, côté web. */
    public function test_le_meme_compte_approuve_atteint_ses_missions_sur_le_web(): void
    {
        $user = $this->prestataireAutoInscrit();
        ProviderProfile::where('user_id', $user->id)->update(['status' => 'active']);

        $this->actingAs($user)->get('/dashboard/employe/missions')->assertOk();
    }

    /**
     * ON NE L'ENFERME PAS DEHORS : les pages par lesquelles on COMPLÈTE le dossier restent
     * ouvertes, sinon il faudrait une approbation pour fournir ce qui permet d'approuver.
     */
    public function test_les_pages_du_dossier_restent_ouvertes(): void
    {
        $user = $this->prestataireAutoInscrit();

        foreach ([
            '/dashboard/employe',
            '/dashboard/employe/metiers-zones',
            '/dashboard/employe/verification',
            '/provider/onboarding',
        ] as $chemin) {
            $this->actingAs($user)->get($chemin)->assertOk($chemin.' doit rester accessible.');
        }
    }

    /**
     * Les prestataires ANTÉRIEURS ne portent pas la colonne et ne doivent rien perdre : sur la base
     * réelle, 4 comptes sur 9 ne sont pas `active`. Une garde fondée sur le statut seul mettrait
     * dehors des prestataires légitimes déjà en production.
     */
    public function test_un_prestataire_anterieur_sans_la_colonne_traverse_sans_condition(): void
    {
        $user = User::factory()->employe()->create(['email_verified_at' => now()]);
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'self_registered_at' => null,
        ]);

        $this->actingAs($user)->get('/dashboard/employe/missions')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/provider/assignments/inbox')->assertOk();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formulaireWeb(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Presta Web',
            'email' => 'presta.web@test.local',
            'password' => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'account_type' => 'provider_independent',
        ], $overrides);
    }

    private function prestataireAutoInscrit(): User
    {
        $user = User::factory()->employe()->create(['email_verified_at' => now()]);

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'self_registered_at' => now(),
        ]);

        return $user->fresh();
    }
}
