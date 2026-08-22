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
 * `users.role` est la colonne héritée d'avant les profils typés. Trois prédicats de
 * `HasUserTypeChecks` la consultaient en dernier recours — ce qui est légitime tant que tous les
 * comptes n'ont pas de profil — mais ils la consultaient AUSSI quand le profil existait et disait
 * autre chose. La condition testée était « le type est-il celui que je cherche ? », jamais « ai-je
 * un type ? ».
 *
 * Conséquence, mesurée sur `brio` avant correction — DIX-NEUF comptes sur trente :
 *
 *   10 clients dont `customer_type` vaut `company` répondaient OUI à « êtes-vous un particulier ? »
 *    9 prestataires `company_worker` répondaient OUI à « êtes-vous indépendant ? »
 *
 * Aucun n'en souffrait : tous les appelants testent le cas le plus spécifique en premier, si bien
 * que la mauvaise réponse n'était jamais atteinte. C'est un défaut LATENT, et ces tests existent
 * pour qu'il le reste — le premier appelant qui interrogera l'un de ces prédicats seul, pour
 * décider d'un versement ou d'un rattachement, ne doit pas hériter du trou.
 *
 * ── CHAQUE REFUS PORTE SON TÉMOIN ────────────────────────────────────────────────────────────
 *
 * Le repli hérité doit continuer de fonctionner quand le profil est ABSENT : c'est encore le cas
 * de comptes réels, et de la plupart des fabriques de test. Sans le témoin, « le profil l'emporte »
 * passerait au vert même si on avait simplement cassé le repli.
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
            'Le profil dit « société » : la colonne héritée n’a plus voix au chapitre.',
        );
    }

    public function test_un_profil_particulier_confirme_bien_le_particulier(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->assertTrue($user->refresh()->isClientPersonal());
    }

    // ─── Client société ──────────────────────────────────────────────────────────────────────

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
            'Quatorze écrans sont gardés par ce prédicat : la colonne héritée ne doit pas les ouvrir contre le profil.',
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
            'Le profil dit « rattaché à une société » : c’est lui qui tranche.',
        );
    }

    /**
     * ET L'ACCÈS N'EST PAS PERDU AU PASSAGE.
     *
     * Corriger un prédicat ne doit mettre personne dehors : un salarié reste un prestataire, et
     * ouvre le même espace qu'avant. C'est le contrôle qui manquerait si l'on se contentait de
     * vérifier que la mauvaise réponse a disparu.
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

    /**
     * `isAdmin()` GARDE SON REPLI, ET C'EST DÉLIBÉRÉ.
     *
     * Les trois prédicats corrigés s'appuient sur une RELATION, qui peut être absente : « pas de
     * profil » a un sens. `platform_role` est `NOT NULL DEFAULT 'user'` — l'absence n'y est pas
     * exprimable, et gater le repli sur elle retirerait le droit d'administration à tout compte
     * créé avec `role = 'admin'` seul. Treize fichiers de test font exactement cela.
     *
     * Ce test fixe la décision pour qu'on ne la reprenne pas par symétrie apparente.
     */
    public function test_l_administration_conserve_son_repli_herite(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // La fabrique ne pose PAS `platform_role` : la colonne reçoit son défaut SQL à l'insertion,
        // mais l'objet en mémoire garde `null` — le piège du « défaut SQL absent en mémoire ».
        $this->assertNull($user->platform_role);
        $this->assertSame('user', DB::table('users')->where('id', $user->id)->value('platform_role'));

        // Et l'administration reste ouverte par le repli hérité, comme treize fichiers de test
        // l'attendent. C'est ce que ce fichier fixe : la symétrie apparente ne doit pas la fermer.
        $this->assertTrue($user->isAdmin());
    }
}
