<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RolesEtPermissions;
use App\Models\AdminRole;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Admin\GestionDesRolesService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RÔLES ET PERMISSIONS D'ADMINISTRATION.
 *
 * AUCUN ÉCRAN NE SAVAIT DONNER UNE CAPACITÉ avant celui-ci : les méthodes existaient dans
 * `GestionUtilisateurs`, aucune Blade ne les appelait, et les vingt et une capacités ne se
 * posaient donc qu'en base ou par un seeder.
 *
 * DISTRIBUER DU POUVOIR EST LE GESTE LE PLUS SENSIBLE D'UNE CONSOLE. Chaque règle qui l'encadre
 * porte ici son témoin : un refus qui passerait au vert parce que le chemin est cassé ne
 * mesurerait rien.
 */
class RolesEtPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_repond_et_annonce_ce_qu_il_est(): void
    {
        $this->actingAs($this->distributeur())
            ->get(route('admin.roles.permissions'))
            ->assertOk()
            ->assertSee('Rôles et permissions');
    }

    public function test_un_admin_sans_gestion_utilisateurs_reste_dehors(): void
    {
        Livewire::actingAs($this->admin(['manage-finance']))
            ->test(RolesEtPermissions::class)
            ->assertForbidden();
    }

    // ── Les rôles ──────────────────────────────────────────────────────────

    public function test_un_role_se_cree_avec_ses_capacites(): void
    {
        Livewire::actingAs($this->distributeur(['manage-finance', 'manage-accounting']))
            ->test(RolesEtPermissions::class)
            ->set('nomDuRole', 'Comptable')
            ->set('capacitesDuRole', ['manage-accounting'])
            ->call('enregistrerLeRole')
            ->assertSet('erreur', null);

        $role = AdminRole::query()->firstOrFail();

        $this->assertSame('Comptable', $role->name);
        $this->assertSame('comptable', $role->slug);
        $this->assertSame(['manage-accounting'], $role->capacites());
    }

    /** LE RÔLE OUVRE VRAIMENT LES PORTES : sinon ce ne serait qu'une étiquette. */
    public function test_le_role_donne_ses_capacites_a_qui_le_porte(): void
    {
        $role = AdminRole::create([
            'name' => 'Comptable', 'slug' => 'comptable',
            'permissions' => ['manage-accounting'],
        ]);

        $cible = $this->admin([]);

        app(GestionDesRolesService::class)->appliquerA(
            $this->distributeur(['manage-accounting']),
            $cible,
            $role,
            [],
            User::ACCESS_SCOPE_ALL,
        );

        $cible->refresh();

        $this->assertTrue($cible->hasAdminPermission('manage-accounting'));
        $this->assertTrue($cible->canAccessAdminModule('manage-accounting'));
        $this->assertContains('manage-accounting', $cible->permissionList());
    }

    /** TÉMOIN — sans le rôle, la même porte reste fermée. */
    public function test_temoin_sans_le_role_la_porte_reste_fermee(): void
    {
        $cible = $this->admin([]);

        $this->assertFalse($cible->hasAdminPermission('manage-accounting'));
    }

    /**
     * L'UNION NE PEUT QU'AJOUTER.
     *
     * Un rôle ne retire jamais une capacité posée à la main, sinon changer le rôle d'un compte
     * lui ferait perdre en silence un accès qu'on lui avait accordé exprès.
     */
    public function test_le_role_s_ajoute_aux_capacites_individuelles(): void
    {
        $role = AdminRole::create(['name' => 'R', 'slug' => 'r', 'permissions' => ['manage-accounting']]);
        $cible = $this->admin(['manage-quality']);
        $cible->forceFill(['admin_role_id' => $role->id])->save();

        $capacites = $cible->fresh()->permissionList();

        $this->assertContains('manage-quality', $capacites);
        $this->assertContains('manage-accounting', $capacites);
    }

    /** SUPPRIMER UN RÔLE NE SUPPRIME PERSONNE : les comptes retombent sur leurs capacités propres. */
    public function test_supprimer_un_role_laisse_les_comptes_en_place(): void
    {
        $role = AdminRole::create(['name' => 'R', 'slug' => 'r', 'permissions' => ['manage-accounting']]);
        $cible = $this->admin(['manage-quality']);
        $cible->forceFill(['admin_role_id' => $role->id])->save();

        app(GestionDesRolesService::class)->supprimerUnRole($role);

        $cible->refresh();

        $this->assertNotNull($cible->id);
        $this->assertNull($cible->admin_role_id);
        $this->assertSame(['manage-quality'], $cible->permissionList());
    }

    // ── Les règles d'élévation ─────────────────────────────────────────────

    /**
     * ON NE DONNE QUE CE QU'ON A.
     *
     * Sans cette règle, deux administrateurs complices s'élèvent mutuellement : A donne à B ce
     * que A n'a pas, B le rend à A.
     */
    public function test_on_ne_peut_pas_accorder_une_capacite_qu_on_n_a_pas(): void
    {
        $acteur = $this->distributeur(['manage-quality']);
        $cible = $this->admin([]);

        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $cible, null, ['manage-finance'], User::ACCESS_SCOPE_ALL);
    }

    /** TÉMOIN — une capacité que l'acteur détient passe bien. */
    public function test_temoin_une_capacite_detenue_s_accorde(): void
    {
        $acteur = $this->distributeur(['manage-quality']);
        $cible = $this->admin([]);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $cible, null, ['manage-quality'], User::ACCESS_SCOPE_ALL);

        $this->assertContains('manage-quality', $cible->fresh()->permissionList());
    }

    /** ON N'AUGMENTE PAS SES PROPRES CAPACITÉS : sinon l'écran serait une porte vers tout. */
    public function test_on_ne_modifie_pas_ses_propres_capacites(): void
    {
        $acteur = $this->distributeur(['manage-finance']);

        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $acteur, null, ['manage-finance'], User::ACCESS_SCOPE_ALL);
    }

    /** LE TITULAIRE DU SIÈGE NE SE RÈGLE PAS ICI. */
    public function test_le_titulaire_du_siege_est_intouchable(): void
    {
        $acteur = $this->distributeur(['manage-finance']);
        $titulaire = $this->prendreLeSiege(['role' => 'admin']);

        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $titulaire, null, [], User::ACCESS_SCOPE_ALL);
    }

    /** DISTRIBUER EXIGE « ACTIONS CRITIQUES » — lire la page ne suffit pas. */
    public function test_sans_actions_critiques_on_ne_distribue_rien(): void
    {
        $acteur = $this->admin(['manage-users', 'manage-quality']);
        $cible = $this->admin([]);

        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $cible, null, ['manage-quality'], User::ACCESS_SCOPE_ALL);
    }

    /** TÉMOIN — la même page se LIT sans « Actions critiques » : c'est une question d'audit. */
    public function test_temoin_la_page_se_lit_sans_actions_critiques(): void
    {
        Livewire::actingAs($this->admin(['manage-users']))
            ->test(RolesEtPermissions::class)
            ->assertOk();
    }

    /**
     * LES CAPACITÉS HORS DE PORTÉE DE L'ACTEUR SURVIVENT.
     *
     * Un administrateur au périmètre étroit ne doit pas pouvoir désarmer un collègue plus large
     * que lui en décochant des cases qu'il ne voit même pas.
     */
    public function test_une_capacite_hors_de_portee_n_est_pas_retiree(): void
    {
        $acteur = $this->distributeur(['manage-quality']);
        $cible = $this->admin(['manage-finance']);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $cible, null, ['manage-quality'], User::ACCESS_SCOPE_ALL);

        $capacites = $cible->fresh()->permissionList();

        $this->assertContains('manage-finance', $capacites, 'La capacité hors de portée a été effacée.');
        $this->assertContains('manage-quality', $capacites);
    }

    /** UN PÉRIMÈTRE « UNE SEULE ZONE » SANS ZONE ne limite rien et n'ouvre rien. */
    public function test_le_perimetre_zone_exige_une_zone(): void
    {
        $acteur = $this->distributeur();
        $cible = $this->admin([]);

        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($acteur, $cible, null, [], User::ACCESS_SCOPE_ZONE, null);
    }

    /** TÉMOIN — avec une zone, le même périmètre s'enregistre. */
    public function test_temoin_avec_une_zone_le_perimetre_s_enregistre(): void
    {
        $zone = ServiceZone::factory()->create();
        $cible = $this->admin([]);

        app(GestionDesRolesService::class)
            ->appliquerA($this->distributeur(), $cible, null, [], User::ACCESS_SCOPE_ZONE, $zone->id);

        $cible->refresh();

        $this->assertSame(User::ACCESS_SCOPE_ZONE, $cible->access_scope);
        $this->assertSame($zone->id, $cible->managed_service_zone_id);
    }

    /** UNE CAPACITÉ INVENTÉE N'OUVRE RIEN : la laisser passer ferait croire le contraire. */
    public function test_une_capacite_inconnue_est_refusee(): void
    {
        $this->expectException(DomainException::class);

        app(GestionDesRolesService::class)
            ->appliquerA($this->distributeur(), $this->admin([]), null, ['manage-tout'], User::ACCESS_SCOPE_ALL);
    }

    // ── L'écran ────────────────────────────────────────────────────────────

    public function test_l_ecran_enregistre_les_capacites(): void
    {
        $cible = $this->admin([]);

        Livewire::actingAs($this->distributeur(['manage-quality']))
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->set('capacitesEnPlus', ['manage-quality'])
            ->call('enregistrerLAdministrateur')
            ->assertSet('erreur', null);

        $this->assertContains('manage-quality', $cible->fresh()->permissionList());
    }

    /** L'ÉCRAN DIT POURQUOI IL REFUSE, au lieu de planter. */
    public function test_l_ecran_explique_le_refus(): void
    {
        $cible = $this->admin([]);

        Livewire::actingAs($this->distributeur(['manage-quality']))
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->set('capacitesEnPlus', ['manage-finance'])
            ->call('enregistrerLAdministrateur')
            ->assertSet('erreur', 'Vous ne pouvez pas accorder une capacité que vous n’avez pas : manage-finance');

        $this->assertSame([], $cible->fresh()->permissionList());
    }

    /**
     * UN PERIMETRE SANS SENS SE CHARGE COMME « TOUTE LA PLATEFORME ».
     *
     * `own`, `organization` et `global` sont declarees et lues NULLE PART. Les afficher telles
     * quelles rendait le formulaire invalide des son chargement : l'enregistrement echouait
     * a la validation, sans message et sans rien ecrire.
     */
    public function test_un_perimetre_sans_sens_se_charge_comme_toute_la_plateforme(): void
    {
        $cible = $this->admin([]);
        $cible->forceFill(['access_scope' => User::ACCESS_SCOPE_OWN])->save();

        Livewire::actingAs($this->distributeur())
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->assertSet('perimetre', User::ACCESS_SCOPE_ALL);
    }

    /** TEMOIN — un perimetre qui a un sens se charge tel quel. */
    public function test_temoin_un_perimetre_lecture_seule_se_charge_tel_quel(): void
    {
        $cible = $this->admin([]);
        $cible->forceFill(['access_scope' => User::ACCESS_SCOPE_READONLY])->save();

        Livewire::actingAs($this->distributeur())
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->assertSet('perimetre', User::ACCESS_SCOPE_READONLY);
    }

    /** LA LECTURE INVERSE — pour une capacité, ce qu'elle ouvre et qui la détient. */
    public function test_la_lecture_par_capacite_nomme_les_ecrans_et_les_porteurs(): void
    {
        $porteur = $this->admin(['manage-finance']);
        $porteur->forceFill(['name' => 'Camille Porteur'])->save();

        $tableau = Livewire::actingAs($this->distributeur())
            ->test(RolesEtPermissions::class)
            ->instance()->parCapacite;

        $this->assertContains('Camille Porteur', $tableau['manage-finance']['porteurs']);
        $this->assertNotEmpty($tableau['manage-finance']['ecrans'], 'Aucun écran ne déclare manage-finance.');
    }

    /** LA LECTURE SEULE EFFACE LA ZONE GEREE : garder les deux laisserait deux perimetres. */
    public function test_le_perimetre_lecture_seule_efface_la_zone_geree(): void
    {
        $zone = ServiceZone::factory()->create();
        $cible = $this->admin([]);
        $cible->forceFill([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zone->id,
        ])->save();

        app(GestionDesRolesService::class)
            ->appliquerA($this->distributeur(), $cible, null, [], User::ACCESS_SCOPE_READONLY, $zone->id);

        $cible->refresh();

        $this->assertSame(User::ACCESS_SCOPE_READONLY, $cible->access_scope);
        $this->assertNull($cible->managed_service_zone_id);
    }

    /**
     * UN ADMINISTRATEUR LIMITE A UNE ZONE NE VOIT QUE LA SIENNE.
     *
     * Sans cela, il placerait un collegue sur une zone voisine — sortir de son perimetre en
     * passant par quelqu'un d'autre.
     */
    public function test_un_admin_de_zone_ne_propose_que_sa_zone(): void
    {
        $sienne = ServiceZone::factory()->create(['name' => 'Zone A']);
        ServiceZone::factory()->create(['name' => 'Zone B']);

        $acteur = $this->distributeur();
        $acteur->forceFill([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $sienne->id,
        ])->save();

        $zones = Livewire::actingAs($acteur)->test(RolesEtPermissions::class)->instance()->zones;

        $this->assertCount(1, $zones);
        $this->assertSame($sienne->id, $zones->first()->id);
    }

    /** TEMOIN — un administrateur sans limite de zone les voit toutes. */
    public function test_temoin_un_admin_sans_limite_voit_toutes_les_zones(): void
    {
        ServiceZone::factory()->count(2)->create();

        $zones = Livewire::actingAs($this->distributeur())->test(RolesEtPermissions::class)->instance()->zones;

        $this->assertCount(2, $zones);
    }

    // ── Fabriques ──────────────────────────────────────────────────────────

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => $capacites,
        ]);
    }

    /** @param  list<string>  $capacites */
    private function distributeur(array $capacites = []): User
    {
        return $this->admin([...$capacites, 'manage-users', 'perform-critical-admin-actions']);
    }
}
