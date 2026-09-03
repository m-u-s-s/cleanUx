<?php

namespace Tests\Feature\Platform;

use App\Livewire\Admin\LeSiegeDeLaPlateforme;
use App\Models\PlatformSeatTransfer;
use App\Models\User;
use App\Notifications\TransfertDeSiegeArme;
use App\Services\Platform\SiegeDuSuperAdmin;
use App\Support\Platform\PorteDuSiege;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UN SEUL SIÈGE DE SUPER-ADMINISTRATEUR, DANS TOUTE LA PLATEFORME.
 *
 * Le siège est une LIGNE, pas une constante : aucune adresse dans le dépôt, aucune dans
 * l'environnement. Ce qui est garanti ici, c'est l'invariant — au plus un — et le fait qu'il ne
 * se déplace que par sa porte.
 *
 * CHAQUE REFUS PORTE SON TÉMOIN : un test « ceci est interdit » qui passerait au vert parce que
 * le chemin est cassé mesurerait une panne, pas une garde.
 */
class LeSiegeDeSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private const PHRASE = 'ma phrase de siege 2026';

    // ── L'invariant ────────────────────────────────────────────────────────

    /**
     * LA BASE REFUSE LE SECOND, SANS PASSER PAR LE CODE.
     *
     * Un crochet de modèle ne voit ni `DB::table()->update()`, ni un import SQL, ni une console
     * de base de données. C'est l'index unique qui les voit tous.
     */
    public function test_la_base_refuse_un_second_super_admin(): void
    {
        $this->prendreLeSiege();
        $autre = User::factory()->admin()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('users')->where('id', $autre->id)->update(['platform_role' => 'super_admin']);
    }

    /** TÉMOIN — la même écriture passe quand le siège est vacant. */
    public function test_temoin_la_meme_ecriture_passe_sur_un_siege_vacant(): void
    {
        $autre = User::factory()->admin()->create();

        DB::table('users')->where('id', $autre->id)->update(['platform_role' => 'super_admin']);

        $this->assertSame('super_admin', $autre->fresh()->platform_role);
    }

    /** LE MODÈLE REFUSE LA PROMOTION HORS DE SA PORTE. */
    public function test_le_modele_refuse_une_promotion_directe(): void
    {
        $autre = User::factory()->admin()->create();

        $this->expectException(DomainException::class);

        $autre->forceFill(['platform_role' => 'super_admin'])->save();
    }

    /**
     * ET LA RÉTROGRADATION AUSSI — sinon le vol se fait en deux temps.
     *
     * Rétrograder le titulaire, puis se promouvoir sur un siège devenu vacant : chacune des deux
     * écritures serait innocente prise seule.
     */
    public function test_le_modele_refuse_de_retrograder_le_titulaire(): void
    {
        $titulaire = $this->prendreLeSiege();

        $this->expectException(DomainException::class);

        $titulaire->forceFill(['platform_role' => 'admin'])->save();
    }

    /** TÉMOIN — par la porte, les deux écritures passent. */
    public function test_temoin_par_la_porte_le_siege_se_deplace(): void
    {
        $titulaire = $this->prendreLeSiege();

        PorteDuSiege::ouvrir(function () use ($titulaire) {
            $titulaire->forceFill(['platform_role' => 'admin'])->save();
        });

        $this->assertSame('admin', $titulaire->fresh()->platform_role);
    }

    /** LA PORTE SE REFERME MÊME QUAND LE GESTE ÉCHOUE. */
    public function test_la_porte_se_referme_apres_une_exception(): void
    {
        try {
            PorteDuSiege::ouvrir(fn () => throw new DomainException('boum'));
        } catch (DomainException) {
            // attendu
        }

        $this->assertFalse(PorteDuSiege::estOuverte());
    }

    // ── La seconde notion ──────────────────────────────────────────────────

    /**
     * `is_super_admin` N'ÉLÈVE PLUS PERSONNE.
     *
     * Elle ouvrait `hasAdminPermission()` à elle seule, AVANT toute vérification de rôle : un
     * seeder qui la posait sans le rôle créait un super-administrateur de fait.
     */
    public function test_la_colonne_miroir_n_eleve_personne(): void
    {
        $admin = User::factory()->admin()->create(['is_super_admin' => true]);

        $this->assertFalse((bool) $admin->fresh()->is_super_admin);
        $this->assertFalse($admin->fresh()->hasAdminPermission('manage-finance'));
    }

    /** TÉMOIN — le vrai titulaire, lui, a bien tout. */
    public function test_temoin_le_titulaire_a_toutes_les_capacites(): void
    {
        $titulaire = $this->prendreLeSiege();

        $this->assertTrue((bool) $titulaire->fresh()->is_super_admin);
        $this->assertTrue($titulaire->hasAdminPermission('manage-finance'));
    }

    // ── Prendre un siège vacant ────────────────────────────────────────────

    public function test_on_reclame_un_siege_vacant_avec_une_phrase(): void
    {
        $futur = User::factory()->admin()->create();

        app(SiegeDuSuperAdmin::class)->reclamer($futur, self::PHRASE);

        $futur->refresh();

        $this->assertSame('super_admin', $futur->platform_role);
        $this->assertNotNull($futur->seat_secret_hash);
        $this->assertNotNull($futur->seat_claimed_at);
    }

    /** UN SIÈGE OCCUPÉ NE SE REPREND PAS : il se transfère. */
    public function test_un_siege_occupe_ne_se_reclame_pas(): void
    {
        $this->prendreLeSiege();
        $autre = User::factory()->admin()->create();

        $this->expectException(DomainException::class);

        app(SiegeDuSuperAdmin::class)->reclamer($autre, self::PHRASE);
    }

    /** UNE PHRASE TROP COURTE EST REFUSÉE : elle ne se saisit pas tous les jours. */
    public function test_une_phrase_trop_courte_est_refusee(): void
    {
        $futur = User::factory()->admin()->create();

        $this->expectException(DomainException::class);

        app(SiegeDuSuperAdmin::class)->reclamer($futur, 'court');
    }

    // ── Le transfert ───────────────────────────────────────────────────────

    public function test_le_transfert_s_arme_mais_ne_s_applique_pas_tout_de_suite(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        $transfert = app(SiegeDuSuperAdmin::class)
            ->armerLeTransfert($titulaire, $cible, self::PHRASE);

        // LE SIÈGE N'A PAS BOUGÉ : c'est ce délai qui laisse le temps d'annuler.
        $this->assertSame('super_admin', $titulaire->fresh()->platform_role);
        $this->assertSame('admin', $cible->fresh()->platform_role);
        $this->assertTrue($transfert->effective_at->isFuture());
    }

    public function test_une_mauvaise_phrase_n_arme_rien(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        try {
            app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, 'ce n est pas la phrase');
            $this->fail('Le transfert aurait dû être refusé.');
        } catch (DomainException) {
            // attendu
        }

        $this->assertSame(0, PlatformSeatTransfer::query()->count());
    }

    /** QUI N'EST PAS TITULAIRE NE TRANSFÈRE RIEN, même avec la bonne phrase. */
    public function test_un_autre_administrateur_ne_transfere_rien(): void
    {
        [, $cible] = $this->siegePris();
        $intrus = User::factory()->admin()->create(['is_active' => true]);

        $this->expectException(DomainException::class);

        app(SiegeDuSuperAdmin::class)->armerLeTransfert($intrus, $cible, self::PHRASE);
    }

    /** LE SIÈGE NE FABRIQUE PAS UN POUVOIR : la cible doit déjà être administratrice et active. */
    public function test_le_siege_ne_va_pas_a_un_compte_ordinaire(): void
    {
        [$titulaire] = $this->siegePris();
        $quidam = User::factory()->create(['is_active' => true]);

        $this->expectException(DomainException::class);

        app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $quidam, self::PHRASE);
    }

    public function test_le_transfert_mur_deplace_le_siege(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        $transfert = app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, self::PHRASE);
        $transfert->forceFill(['effective_at' => now()->subMinute()])->save();

        $this->assertSame(1, app(SiegeDuSuperAdmin::class)->appliquerLesTransfertsMurs());

        $this->assertSame('admin', $titulaire->fresh()->platform_role);
        $this->assertSame('super_admin', $cible->fresh()->platform_role);
        // LA PHRASE MEURT AVEC LE SIÈGE : l'ancien titulaire ne garde pas de quoi le reprendre.
        $this->assertNull($titulaire->fresh()->seat_secret_hash);
    }

    /** TÉMOIN — avant l'échéance, la même passe ne déplace rien. */
    public function test_temoin_avant_l_echeance_la_passe_ne_deplace_rien(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, self::PHRASE);

        $this->assertSame(0, app(SiegeDuSuperAdmin::class)->appliquerLesTransfertsMurs());
        $this->assertSame('super_admin', $titulaire->fresh()->platform_role);
    }

    /** UNE CIBLE SUSPENDUE ENTRE-TEMPS N'HÉRITE PAS DU PASSE-PARTOUT. */
    public function test_une_cible_suspendue_entre_temps_n_herite_pas(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        $transfert = app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, self::PHRASE);
        $transfert->forceFill(['effective_at' => now()->subMinute()])->save();

        $cible->forceFill(['is_active' => false])->save();

        $this->assertSame(0, app(SiegeDuSuperAdmin::class)->appliquerLesTransfertsMurs());
        $this->assertSame('super_admin', $titulaire->fresh()->platform_role);
        $this->assertNotNull($transfert->fresh()->cancelled_at);
    }

    public function test_l_annulation_arrete_le_transfert(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, self::PHRASE);
        app(SiegeDuSuperAdmin::class)->annulerLeTransfert($titulaire, self::PHRASE, 'ce n’était pas moi');

        $this->assertNull(app(SiegeDuSuperAdmin::class)->transfertEnAttente());
        $this->assertSame(0, app(SiegeDuSuperAdmin::class)->appliquerLesTransfertsMurs());
    }

    /** L'ANNULATION EXIGE LA PHRASE : sinon le voleur annulerait l'annulation. */
    public function test_l_annulation_sans_la_phrase_est_refusee(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        app(SiegeDuSuperAdmin::class)->armerLeTransfert($titulaire, $cible, self::PHRASE);

        try {
            app(SiegeDuSuperAdmin::class)->annulerLeTransfert($titulaire, 'mauvaise phrase');
            $this->fail('L’annulation aurait dû être refusée.');
        } catch (DomainException) {
            // attendu
        }

        $this->assertNotNull(app(SiegeDuSuperAdmin::class)->transfertEnAttente());
    }

    // ── L'écran ────────────────────────────────────────────────────────────

    public function test_seul_le_titulaire_ouvre_l_ecran(): void
    {
        $this->siegePris();

        $this->actingAs(User::factory()->admin()->create(['is_active' => true]))
            ->get(route('admin.siege'))
            ->assertForbidden();
    }

    /** TÉMOIN — le titulaire, lui, entre. */
    public function test_temoin_le_titulaire_ouvre_l_ecran(): void
    {
        [$titulaire] = $this->siegePris();

        $this->actingAs($titulaire)->get(route('admin.siege'))->assertOk();
    }

    /** L'ÉCRAN PRÉVIENT LE TITULAIRE — c'est ce qui rend le délai utile. */
    public function test_l_ecran_previent_le_titulaire_a_l_armement(): void
    {
        Notification::fake();

        [$titulaire, $cible] = $this->siegePris();

        Livewire::actingAs($titulaire)->test(LeSiegeDeLaPlateforme::class)
            ->set('destinataire', $cible->id)
            ->set('phrase', self::PHRASE)
            ->call('armerLeTransfert')
            ->assertSet('erreur', null);

        Notification::assertSentTo($titulaire, TransfertDeSiegeArme::class);
    }

    /** LA PHRASE NE SURVIT PAS À L'ACTION : elle voyagerait dans l'instantané du navigateur. */
    public function test_la_phrase_ne_reste_pas_dans_l_ecran(): void
    {
        [$titulaire, $cible] = $this->siegePris();

        Livewire::actingAs($titulaire)->test(LeSiegeDeLaPlateforme::class)
            ->set('destinataire', $cible->id)
            ->set('phrase', self::PHRASE)
            ->call('armerLeTransfert')
            ->assertSet('phrase', '');
    }

    // ── La commande ────────────────────────────────────────────────────────

    public function test_la_commande_annonce_un_siege_vacant(): void
    {
        $this->artisan('plateforme:siege', ['--etat' => true])
            ->expectsOutputToContain('VACANT')
            ->assertSuccessful();
    }

    public function test_la_commande_refuse_de_reprendre_un_siege_occupe(): void
    {
        [$titulaire] = $this->siegePris();

        $this->artisan('plateforme:siege', ['email' => User::factory()->admin()->create()->email])
            ->expectsOutputToContain($titulaire->email)
            ->assertFailed();
    }

    /**
     * @return array{0: User, 1: User} le titulaire, et un administrateur actif
     */
    private function siegePris(): array
    {
        $titulaire = User::factory()->admin()->create(['is_active' => true]);
        app(SiegeDuSuperAdmin::class)->reclamer($titulaire, self::PHRASE);

        $cible = User::factory()->admin()->create(['is_active' => true]);

        return [$titulaire->refresh(), $cible];
    }
}
