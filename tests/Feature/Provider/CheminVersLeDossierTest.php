<?php

namespace Tests\Feature\Provider;

use App\Enums\ProviderType;
use App\Livewire\Provider\ProviderDossierBanner;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\Onboarding\ProviderDossierSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UN PRESTATAIRE QUI VIENT DE S'INSCRIRE DOIT POUVOIR TROUVER SON DOSSIER.
 *
 * LE DÉFAUT, relevé en s'inscrivant vraiment. Le compte est créé, le tableau de bord s'ouvre, et il
 * y lit « Passez en ligne pour recevoir des missions ». C'est une promesse intenable : son profil
 * est `unverified`, et la requête candidate du dispatch exige `verified`. Il ne recevra jamais rien.
 *
 * L'assistant de dossier existait, fonctionnait, et disait exactement ce qu'il fallait — mais AUCUNE
 * page du portail prestataire n'y menait. Le seul lien direct des vues se trouvait dans une barre
 * latérale d'ADMINISTRATION.
 *
 * CE QUE CES TESTS MESURENT, c'est la JOIGNABILITÉ, pas l'existence : ils demandent la page du
 * tableau de bord et y cherchent le lien. Un composant correct mais non monté — le défaut d'origine,
 * exactement — les ferait tomber.
 */
class CheminVersLeDossierTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(string $verification): User
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYE, 'is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => $verification,
        ]);

        return $user->fresh();
    }

    #[Test]
    public function le_tableau_de_bord_d_un_prestataire_non_verifie_mene_a_son_dossier(): void
    {
        $reponse = $this->actingAs($this->prestataire('unverified'))
            ->followingRedirects()
            ->get('/dashboard/employe')
            ->assertOk();

        $reponse->assertSee('banniere-dossier-prestataire', false);
        $reponse->assertSee(route('provider.onboarding'), false);
        $reponse->assertSee('Compléter mon dossier', false);
    }

    /**
     * LE TÉMOIN — sans lui, le test précédent pourrait mesurer un bandeau collé en permanence.
     *
     * Un bandeau qui ne disparaît jamais devient du décor : le jour où il compte vraiment, plus
     * personne ne le lit. Une fois la porte du dispatch franchie, il n'a plus rien à apprendre.
     */
    #[Test]
    public function un_prestataire_verifie_ne_voit_aucun_bandeau_de_dossier(): void
    {
        $this->actingAs($this->prestataire('verified'))
            ->followingRedirects()
            ->get('/dashboard/employe')
            ->assertOk()
            ->assertDontSee('banniere-dossier-prestataire', false);
    }

    /**
     * DEUX SITUATIONS QUI N'APPELLENT PAS LE MÊME GESTE.
     *
     * Dossier complet mais pas encore relu : réclamer une pièce déjà fournie enverrait le
     * prestataire chercher ce qu'il a déjà donné.
     */
    #[Test]
    public function un_dossier_complet_en_attente_de_relecture_ne_reclame_rien(): void
    {
        // Le dossier est remplacé plutôt que construit : ce qu'on mesure ici est la DÉCISION du
        // bandeau face à une liste de blocages vide, pas la façon dont cette liste est calculée —
        // ce calcul a ses propres tests.
        $this->app->instance(ProviderDossierSummary::class, new class(app(ProviderDocumentRequirements::class)) extends ProviderDossierSummary
        {
            public function for(User $user): array
            {
                return ['blockers' => [], 'warnings' => [], 'is_complete' => true, 'can_mark_verified' => true];
            }
        });

        Livewire::actingAs($this->prestataire('pending'))
            ->test(ProviderDossierBanner::class)
            ->assertSee('Nous le relisons')
            ->assertSee('Revoir mon dossier')
            ->assertDontSee('Il reste');
    }

    /**
     * QUAND IL RESTE QUELQUE CHOSE, ON LE NOMME.
     *
     * Le contrôle positif du test précédent : sans lui, un bandeau bloqué sur « nous relisons »
     * passerait au vert alors qu'il tairait à un prestataire ce qu'il doit fournir.
     */
    #[Test]
    public function un_dossier_incomplet_nomme_ce_qui_manque(): void
    {
        $this->app->instance(ProviderDossierSummary::class, new class(app(ProviderDocumentRequirements::class)) extends ProviderDossierSummary
        {
            public function for(User $user): array
            {
                return [
                    'blockers' => ['Justificatif manquant : Permis de conduire'],
                    'warnings' => [],
                    'is_complete' => false,
                    'can_mark_verified' => false,
                ];
            }
        });

        Livewire::actingAs($this->prestataire('unverified'))
            ->test(ProviderDossierBanner::class)
            ->assertSee('Permis de conduire')
            ->assertSee('Compléter mon dossier')
            ->assertDontSee('Nous le relisons');
    }

    /**
     * LE BOUTON FLOTTANT NE SE POSE PLUS SUR LES FORMULAIRES.
     *
     * Il est en position fixe. Sur l'inscription prestataire il recouvrait des champs — et invitait
     * à partir réserver une prestation quelqu'un qui s'inscrivait pour EN RENDRE.
     */
    #[Test]
    public function les_pages_d_authentification_ne_portent_pas_le_bouton_flottant(): void
    {
        foreach (['/register', '/login', '/forgot-password'] as $chemin) {
            $this->get($chemin)->assertOk()->assertDontSee('cx-fab', false);
        }
    }

    /**
     * LE TÉMOIN DU BOUTON — sans lui, le test précédent passerait au vert si le bouton avait
     * simplement disparu du site entier.
     */
    #[Test]
    public function la_vitrine_porte_toujours_le_bouton_flottant(): void
    {
        $this->get('/')->assertOk()->assertSee('cx-fab', false);
    }

    /**
     * LA PAGE DE CONFIRMATION D'E-MAIL EST EN FRANÇAIS.
     *
     * Elle passait bien par `__()`, mais les clés n'avaient jamais été traduites : le premier écran
     * qui suit l'inscription était donc à moitié en anglais.
     */
    #[Test]
    public function la_page_de_confirmation_d_email_est_en_francais(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $reponse = $this->actingAs($user)->get('/email/verify')->assertOk();

        $reponse->assertSee('Renvoyer l’e-mail de confirmation', false);
        $reponse->assertSee('Se déconnecter', false);
        $reponse->assertDontSee('Resend Verification Email', false);
        $reponse->assertDontSee('Before continuing', false);
        $reponse->assertDontSee('Log Out', false);
    }
}
