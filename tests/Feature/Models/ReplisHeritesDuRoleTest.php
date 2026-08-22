<?php

namespace Tests\Feature\Models;

use App\Models\CustomerProfile;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UN REPLI NE PARLE QUE QUAND L'AUTRE SE TAIT.
 *
 * `users.role` est la colonne heritee d'avant les profils types. Trois predicats de
 * `HasUserTypeChecks` la consultaient en dernier recours — ce qui est legitime tant que tous les
 * comptes n'ont pas de profil — mais ils la consultaient AUSSI quand le profil existait et disait
 * autre chose. La condition testee etait « le type est-il celui que je cherche ? », jamais « ai-je
 * un type ? ».
 *
 * Consequence, mesuree sur `brio` avant correction — DIX-NEUF comptes sur trente :
 *
 *   10 clients dont `customer_type` vaut `company` repondaient OUI à « etes-vous un particulier ? »
 *    9 prestataires `company_worker` repondaient OUI à « etes-vous independant ? »
 *
 * Aucun n'en souffrait : tous les appelants testent le cas le plus specifique en premier, si bien
 * que la mauvaise reponse n'etait jamais atteinte. C'est un defaut LATENT, et ces tests existent
 * pour qu'il le reste — le premier appelant qui interrogera l'un de ces predicats seul, pour
 * decider d'un versement ou d'un rattachement, ne doit pas heriter du trou.
 *
 * ── CHAQUE REFUS PORTE SON TÉMOIN ────────────────────────────────────────────────────────────
 *
 * Le repli herite doit continuer de fonctionner quand le profil est ABSENT : c'est encore le cas
 * de comptes reels, et de la plupart des fabriques de test. Sans le temoin, « le profil l'emporte »
 * passerait au vert meme si on avait simplement casse le repli.
 */
class ReplisHeritesDuRoleTest extends TestCase
{
    use RefreshDatabase;

    // ─── Client particulier ──────────────────────────────────────────────────────────────────

    public function test_temoin_sans_profil_la_colonne_heritee_decide_encore(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->assertTrue($user->isClientPersonal());
    }

    public function test_un_profil_societe_l_emporte_sur_la_colonne_heritee(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'company']);

        $this->assertFalse(
            $user->refresh()->isClientPersonal(),
            'Le profil dit « societe » : la colonne heritee n’a plus voix au chapitre.',
        );
    }

    public function test_un_profil_particulier_confirme_bien_le_particulier(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->assertTrue($user->refresh()->isClientPersonal());
    }

    // ─── Client societe ──────────────────────────────────────────────────────────────────────

    public function test_temoin_sans_profil_le_role_entreprise_ouvre_l_espace_societe(): void
    {
        $user = User::factory()->create(['role' => 'entreprise']);

        $this->assertTrue($user->isClientCompany());
    }

    public function test_un_profil_particulier_ferme_l_espace_societe(): void
    {
        $user = User::factory()->create(['role' => 'entreprise']);
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->assertFalse(
            $user->refresh()->isClientCompany(),
            'Quatorze ecrans sont gardes par ce predicat : la colonne heritee ne doit pas les ouvrir contre le profil.',
        );
    }

    // ─── Prestataire ─────────────────────────────────────────────────────────────────────────

    public function test_temoin_sans_profil_le_role_employe_designe_un_independant(): void
    {
        $user = User::factory()->create(['role' => 'employe']);

        $this->assertTrue($user->isProviderIndependent());
    }

    public function test_un_profil_salarie_n_est_pas_un_independant(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['provider_type' => 'company_worker', 'status' => 'active'],
        );

        $this->assertFalse(
            $user->refresh()->isProviderIndependent(),
            'Le profil dit « rattache à une societe » : c’est lui qui tranche.',
        );
    }

    /**
     * ET L'ACCÈS N'EST PAS PERDU AU PASSAGE.
     *
     * Corriger un predicat ne doit mettre personne dehors : un salarie reste un prestataire, et
     * ouvre le meme espace qu'avant. C'est le contrôle qui manquerait si l'on se contentait de
     * verifier que la mauvaise reponse a disparu.
     */
    public function test_le_salarie_garde_son_espace_prestataire(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['provider_type' => 'company_worker', 'status' => 'active'],
        );

        $user->refresh();

        $this->assertTrue($user->isProviderCompanyWorker());
        $this->assertTrue($user->isEmploye(), 'L’espace prestataire est garde par `isEmploye()`, qui unit les deux cas.');
    }

    // ─── Les PORTÉES : la meme règle, mais en SQL ─────────────────────────────────────

    /**
     * LE TÉMOIN DES PORTÉES : le chemin herite fonctionne aussi en base.
     *
     * Sans lui, « la portee trouve le prestataire à profil » passerait au vert meme si la portee
     * avait cesse de voir les comptes sans profil — c'est-à-dire en cassant ce qui marchait.
     */
    public function test_temoin_la_portee_voit_le_prestataire_sans_profil(): void
    {
        $sansProfil = User::factory()->create(['role' => 'employe']);

        $this->assertTrue(User::query()->providers()->whereKey($sansProfil->id)->exists());
    }

    /**
     * LE CAS QUE LE FILTRE HÉRITÉ MANQUAIT.
     *
     * `where('role', 'employe')` voyait 11 prestataires sur `brio` quand la verite typee en
     * comptait 14. Trois etaient invisibles — y compris pour les rappels de rendez-vous.
     */
    public function test_la_portee_voit_le_prestataire_que_seul_son_profil_designe(): void
    {
        $parProfil = User::factory()->create(['role' => 'user']);
        ProviderProfile::updateOrCreate(
            ['user_id' => $parProfil->id],
            ['provider_type' => 'company_worker', 'status' => 'active'],
        );

        $this->assertTrue(
            User::query()->providers()->whereKey($parProfil->id)->exists(),
            'Un prestataire designe par son profil doit sortir des requetes, pas seulement des predicats.',
        );
    }

    /** `where('role', 'entreprise')` rendait ZERO sur `brio` : onze societes etaient aveugles. */
    public function test_la_portee_voit_la_societe_que_seul_son_profil_designe(): void
    {
        $societe = User::factory()->create(['role' => 'client']);
        CustomerProfile::create(['user_id' => $societe->id, 'customer_type' => 'company']);

        $this->assertTrue(User::query()->companyClients()->whereKey($societe->id)->exists());
    }

    public function test_la_portee_ne_ramasse_pas_qui_n_est_pas_prestataire(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->assertFalse(User::query()->providers()->whereKey($client->id)->exists());
    }

    /**
     * LA GARANTIE QUI COMPTE : LA PORTÉE ET LE PRÉDICAT NE DOIVENT JAMAIS DIVERGER.
     *
     * Une portee SQL et un predicat PHP qui repondent à la meme question sont deux ecritures de la
     * meme règle. C'est exactement la situation qui a produit le defaut d'origine — alors ce test
     * les confronte sur une population melangee, plutôt que de verifier chacun de son côte.
     */
    public function test_la_portee_et_le_predicat_repondent_la_meme_chose(): void
    {
        User::factory()->create(['role' => 'employe']);
        User::factory()->create(['role' => 'client']);
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'entreprise']);

        $avecProfilPresta = User::factory()->create(['role' => 'user']);
        ProviderProfile::updateOrCreate(
            ['user_id' => $avecProfilPresta->id],
            ['provider_type' => 'independent', 'status' => 'active'],
        );

        $societe = User::factory()->create(['role' => 'client']);
        CustomerProfile::create(['user_id' => $societe->id, 'customer_type' => 'company']);

        $tous = User::query()->with(['customerProfile', 'providerProfile'])->get();

        foreach ([
            ['providers', fn (User $u) => $u->isEmploye()],
            ['admins', fn (User $u) => $u->isAdmin()],
            ['companyClients', fn (User $u) => $u->isClientCompany()],
        ] as [$portee, $predicat]) {
            $parPortee = User::query()->{$portee}()->pluck('id')->sort()->values()->all();
            $parPredicat = $tous->filter($predicat)->pluck('id')->sort()->values()->all();

            $this->assertSame($parPredicat, $parPortee, "La portee `{$portee}()` et son predicat divergent.");
        }
    }

    /**
     * `isAdmin()` GARDE SON REPLI, ET C'EST DÉLIBÉRÉ.
     *
     * Les trois predicats corriges s'appuient sur une RELATION, qui peut etre absente : « pas de
     * profil » a un sens. `platform_role` est `NOT NULL DEFAULT 'user'` — l'absence n'y est pas
     * exprimable, et gater le repli sur elle retirerait le droit d'administration à tout compte
     * cree avec `role = 'admin'` seul. Treize fichiers de test font exactement cela.
     *
     * Ce test fixe la decision pour qu'on ne la reprenne pas par symetrie apparente.
     */
    public function test_l_administration_conserve_son_repli_herite(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // La fabrique ne pose PAS `platform_role` : la colonne reçoit son defaut SQL à l'insertion,
        // mais l'objet en memoire garde `null` — le piège du « defaut SQL absent en memoire ».
        $this->assertNull($user->platform_role);
        $this->assertSame('user', DB::table('users')->where('id', $user->id)->value('platform_role'));

        // Et l'administration reste ouverte par le repli herite, comme treize fichiers de test
        // l'attendent. C'est ce que ce fichier fixe : la symetrie apparente ne doit pas la fermer.
        $this->assertTrue($user->isAdmin());
    }
}
