<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ApplicationsMobiles;
use App\Models\Parametre;
use App\Models\TranslationOverride;
use App\Models\User;
use App\Support\Apps\LiensApplications;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/** Les liens de téléchargement n'existaient nulle part : le bloc public n'avait rien à afficher. */
class ApplicationsMobilesTest extends TestCase
{
    use RefreshDatabase;

    private const IOS_CLIENT = 'https://apps.apple.com/app/brio/id1';

    private const ANDROID_CLIENT = 'https://play.google.com/store/apps/details?id=brio.client';

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        PorteDuSiege::ouvrir(fn () => $admin->forceFill([
            'platform_role' => 'admin',
            'permissions' => $capacites,
        ])->save());

        return $admin->refresh();
    }

    public function test_les_liens_enregistres_sortent_sur_la_page_d_accueil(): void
    {
        // Avant : aucun lien, donc aucun bloc — pas un bloc vide.
        $this->get(route('home'))->assertOk()->assertDontSee('id="telecharger"', false);

        Livewire::actingAs($this->admin(['manage-platform']))
            ->test(ApplicationsMobiles::class)
            ->set('liens.apps_client_ios', self::IOS_CLIENT)
            ->set('liens.apps_client_android', self::ANDROID_CLIENT)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="telecharger"', false)
            ->assertSee(self::IOS_CLIENT, false)
            ->assertSee(self::ANDROID_CLIENT, false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_un_lien_vide_n_affiche_aucun_bouton(): void
    {
        Parametre::setValeur('apps_client_ios', self::IOS_CLIENT);
        Parametre::setValeur('apps_client_android', '');

        $reponse = $this->get(route('home'))->assertOk();

        // TEMOIN : le lien rempli sort bien, c'est donc bien le vide qui masque l'autre.
        $reponse->assertSee(self::IOS_CLIENT, false);
        $reponse->assertDontSee('play.google.com', false);
    }

    public function test_une_carte_eteinte_disparait_meme_avec_ses_liens(): void
    {
        Parametre::setValeur('apps_client_ios', self::IOS_CLIENT);

        // TEMOIN : allumee, la carte sort.
        $this->get(route('home'))->assertOk()->assertSee(self::IOS_CLIENT, false);

        Parametre::setValeur(LiensApplications::cleVisibilite(LiensApplications::CLIENT), '0');

        $this->get(route('home'))->assertOk()->assertDontSee(self::IOS_CLIENT, false);
    }

    public function test_une_url_qui_n_est_pas_https_est_refusee(): void
    {
        $admin = $this->admin(['manage-platform']);

        Livewire::actingAs($admin)
            ->test(ApplicationsMobiles::class)
            ->set('liens.apps_client_ios', 'http://apps.apple.com/app/brio/id1')
            ->call('enregistrer')
            ->assertHasErrors(['liens.apps_client_ios']);

        $this->assertSame('', (string) Parametre::getValeur('apps_client_ios', ''),
            'Un refus de validation ne doit rien écrire.');

        // TEMOIN : sans ce contrôle, le refus ci-dessus passerait au vert sur une panne du chemin.
        Livewire::actingAs($admin)
            ->test(ApplicationsMobiles::class)
            ->set('liens.apps_client_ios', self::IOS_CLIENT)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(self::IOS_CLIENT, (string) Parametre::getValeur('apps_client_ios', ''));
    }

    public function test_un_javascript_pose_hors_de_l_ecran_n_atteint_pas_la_page(): void
    {
        // L'ecran valide, mais la lecture filtre AUSSI : une valeur arrivee par une autre
        // porte — import, tinker, console — ne doit pas poser un `javascript:` dans un href.
        Parametre::setValeur('apps_client_ios', 'javascript:alert(1)');
        Parametre::setValeur('apps_client_android', self::ANDROID_CLIENT);

        $reponse = $this->get(route('home'))->assertOk();

        $reponse->assertDontSee('javascript:alert(1)', false);
        // TEMOIN : la carte s'affiche bien, c'est donc le lien seul qui a ete ecarte.
        $reponse->assertSee(self::ANDROID_CLIENT, false);
    }

    public function test_les_accroches_s_ecrivent_la_ou_le_centre_de_traductions_les_lit(): void
    {
        Parametre::setValeur('apps_client_ios', self::IOS_CLIENT);

        Livewire::actingAs($this->admin(['manage-platform']))
            ->test(ApplicationsMobiles::class)
            ->set('locale', 'fr')
            ->set('textes.client_titre', 'Brio — la version courte')
            ->call('enregistrer')
            ->assertHasNoErrors();

        // Une SEULE source de verite : `translation_overrides`, pas une table de textes bis.
        $this->assertDatabaseHas('translation_overrides', [
            'locale' => 'fr',
            'group' => 'vitrine',
            'key' => 'apps.client.titre',
            'value' => 'Brio — la version courte',
        ]);

        $this->get(route('home'))->assertOk()->assertSee('Brio — la version courte', false);
    }

    public function test_une_locale_inconnue_n_ecrit_jamais(): void
    {
        $admin = $this->admin(['manage-platform']);

        // La garde est double : le crochet `updatedLocale` ramene a « fr » des la frappe, et la
        // regle `in:` refuserait quand meme a l'enregistrement. On mesure le RESULTAT : rien
        // ne s'ecrit jamais sous une locale inconnue.
        Livewire::actingAs($admin)
            ->test(ApplicationsMobiles::class)
            ->set('locale', 'xx')
            ->assertSet('locale', 'fr')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('translation_overrides', ['locale' => 'xx']);

        // TEMOIN : une locale activee est bien retenue, c'est donc la liste qui ferme la porte.
        Livewire::actingAs($admin)
            ->test(ApplicationsMobiles::class)
            ->set('locale', 'nl')
            ->assertSet('locale', 'nl')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('translation_overrides', ['locale' => 'nl', 'group' => 'vitrine']);
    }

    public function test_la_page_d_accueil_survit_a_l_absence_du_magasin_de_reglages(): void
    {
        Parametre::setValeur('apps_client_ios', self::IOS_CLIENT);

        // TEMOIN : avec la table, le bloc sort.
        $this->get(route('home'))->assertOk()->assertSee(self::IOS_CLIENT, false);

        // La page d'accueil est PUBLIQUE : un bloc de decoration en bas de page ne peut pas la
        // faire tomber. Sans la table, il ne s'affiche pas — et le reste de la page vit.
        Schema::drop('parametres');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('id="telecharger"', false);
    }

    public function test_un_admin_sans_la_capacite_n_atteint_pas_la_page(): void
    {
        $this->actingAs($this->admin(['manage-bookings']))
            ->get(route('admin.applications-mobiles'))
            ->assertForbidden();

        // TEMOIN : la page existe et répond, c'est bien la capacité qui ferme la porte.
        $this->actingAs($this->admin(['manage-platform']))
            ->get(route('admin.applications-mobiles'))
            ->assertOk();
    }

    public function test_la_console_mobile_corrige_un_lien_mais_ne_supprime_pas_la_ligne(): void
    {
        Parametre::setValeur('apps_client_ios', self::IOS_CLIENT);
        $ligne = Parametre::where('cle', 'apps_client_ios')->firstOrFail();

        Sanctum::actingAs(User::factory()->adminComplet()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);

        // TEMOIN : le meme geste que le web passe bien par la console mobile.
        $this->postJson("/api/admin/console/applications-mobiles/{$ligne->id}/actions/modifier", [
            'valeur' => 'https://apps.apple.com/app/brio/id2',
        ])->assertOk();

        $this->assertSame('https://apps.apple.com/app/brio/id2', (string) Parametre::getValeur('apps_client_ios', ''));

        // Supprimer la ligne ferait retomber la clef sur son defaut, sans que rien ne le dise.
        $this->deleteJson("/api/admin/console/applications-mobiles/{$ligne->id}")->assertStatus(409);

        $this->assertSame('https://apps.apple.com/app/brio/id2', (string) Parametre::getValeur('apps_client_ios', ''));
    }

    protected function tearDown(): void
    {
        TranslationOverride::query()->delete();
        parent::tearDown();
    }
}
