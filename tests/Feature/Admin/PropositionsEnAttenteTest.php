<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\PropositionsEnAttente;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Services\Automation\FileDePropositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ECRAN DE LA FILE DES PROPOSITIONS — ce qu'une regle a propose sans agir seule. Une ligne
 * `proposee` immobilise son entite jusqu'a ce qu'un humain la valide ou la refuse : c'est un
 * ecran de travail, pas de consultation.
 */
class PropositionsEnAttenteTest extends TestCase
{
    use RefreshDatabase;

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    /** Le meme administrateur, MOINS la capacite du module — le seul ecart mesure. */
    private function adminSansLaPermission(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => [],
        ]);
    }

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
        ], $attributs));
    }

    /** Une ligne posee, au patron exact de FileDePropositionsTest::proposition(). */
    private function proposition(
        Booking $booking,
        string $resultat = AutomationAction::RESULTAT_PROPOSEE,
        string $actionCle = 'journaliser',
        array $parametres = ['message' => 'vue'],
        ?AutomationRule $regle = null,
    ): AutomationAction {
        return AutomationAction::create([
            'automation_rule_id' => ($regle ?? $this->regle())->id,
            'entite_type' => 'booking',
            'entite_id' => $booking->id,
            'action_cle' => $actionCle,
            'parametres' => $parametres,
            'mode' => 'armee',
            'resultat' => $resultat,
            'pose_le' => now(),
        ]);
    }

    // ── L'ecran est joignable ─────────────────────────────────────────────

    public function test_la_route_sert_le_composant_et_non_le_gabarit_de_repli(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation.propositions'))
            ->assertOk()
            ->assertSeeLivewire(PropositionsEnAttente::class);
    }

    /** LA LISTE MENE A L'ECRAN : une route qu'aucun ecran ne lie reste injoignable. */
    public function test_la_liste_porte_le_lien_vers_l_ecran_des_propositions(): void
    {
        $html = $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('admin.automation.propositions', absolute: false), $html);
    }

    public function test_un_non_administrateur_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.automation.propositions'))
            ->assertForbidden();
    }

    public function test_un_administrateur_sans_la_permission_du_module_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs($this->adminSansLaPermission())
            ->get(route('admin.automation.propositions'))
            ->assertForbidden();
    }

    // ── La file montre ce qui attend, et rien d'autre ────────────────────

    /** BRIEF — la file montre les lignes `proposee` et rien d'autre, parmi les sept resultats possibles. */
    public function test_la_file_montre_les_lignes_proposees_et_rien_d_autre(): void
    {
        $bookings = Booking::factory()->count(6)->create(['status' => 'en_attente']);
        $regle = $this->regle(['nom' => 'Règle visible']);

        $visible = $this->proposition($bookings[0], AutomationAction::RESULTAT_PROPOSEE, 'action-proposee', regle: $regle);
        $this->proposition($bookings[1], AutomationAction::RESULTAT_SIMULEE, 'action-simulee', regle: $regle);
        $this->proposition($bookings[2], AutomationAction::RESULTAT_EXECUTEE, 'action-executee', regle: $regle);
        $this->proposition($bookings[3], AutomationAction::RESULTAT_VALIDEE, 'action-validee', regle: $regle);
        $this->proposition($bookings[4], AutomationAction::RESULTAT_REFUSEE, 'action-refusee', regle: $regle);
        $this->proposition($bookings[5], AutomationAction::RESULTAT_ECHOUEE, 'action-echouee', regle: $regle);

        $composant = Livewire::actingAs($this->adminGlobal())->test(PropositionsEnAttente::class);

        $composant->assertSee('action-proposee')
            ->assertSee('Règle visible')
            ->assertSee('Réservation #'.$visible->entite_id)
            ->assertDontSee('action-simulee')
            ->assertDontSee('action-executee')
            ->assertDontSee('action-validee')
            ->assertDontSee('action-refusee')
            ->assertDontSee('action-echouee');
    }

    /** TEMOIN — une file vide affiche un etat vide, pas un tableau vide. */
    public function test_temoin_une_file_vide_affiche_un_etat_vide(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->assertSee('Aucune proposition en attente')
            ->assertDontSee('action-');
    }

    /** Les parametres s'affichent `nom : valeur`, tronques a l'affichage, le texte entier en `title`. */
    public function test_les_parametres_s_affichent_nom_valeur_avec_le_texte_entier_en_title(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $long = str_repeat('x', 150);
        $this->proposition($booking, parametres: ['message' => $long]);

        $html = Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->assertSee('message')
            ->html();

        $this->assertStringContainsString('title="'.$long.'"', $html);
        $this->assertStringNotContainsString('>'.$long.'<', $html, 'La valeur affichée doit être tronquée, le texte entier reste dans `title`.');
    }

    // ── Valider et refuser dessaisissent la file ─────────────────────────

    /** BRIEF — valider execute l'action et retire la ligne de la file. */
    public function test_valider_execute_et_retire_la_ligne_de_la_file(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('valider', $ligne->id)
            ->assertDontSee('journaliser');

        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    /** BRIEF — refuser sans motif est refuse cote serveur, pas seulement dans le formulaire. */
    public function test_refuser_sans_motif_est_refuse(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('ouvrirRefus', $ligne->id)
            ->set('motifRefus', '')
            ->call('confirmerRefus')
            ->assertHasErrors(['motifRefus' => 'required']);

        $this->assertSame(AutomationAction::RESULTAT_PROPOSEE, $ligne->fresh()->resultat);
    }

    /** BRIEF — refuser avec motif retire la ligne de la file. */
    public function test_refuser_avec_motif_retire_la_ligne_de_la_file(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('ouvrirRefus', $ligne->id)
            ->set('motifRefus', 'Faux positif, déjà traité.')
            ->call('confirmerRefus')
            ->assertHasNoErrors()
            ->assertSet('ligneCiblee', null)
            ->assertDontSee('journaliser');

        $fraiche = $ligne->fresh();
        $this->assertSame(AutomationAction::RESULTAT_REFUSEE, $fraiche->resultat);
        $this->assertSame('Faux positif, déjà traité.', $fraiche->motif);
    }

    /** Fermer le panneau de refus sans valider ne decide rien : la ligne reste en attente. */
    public function test_fermer_le_refus_sans_confirmer_ne_decide_rien(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('ouvrirRefus', $ligne->id)
            ->set('motifRefus', 'un motif quelconque')
            ->call('fermerRefus')
            ->assertSet('ligneCiblee', null)
            ->assertSet('motifRefus', '');

        $this->assertSame(AutomationAction::RESULTAT_PROPOSEE, $ligne->fresh()->resultat);
    }

    /**
     * DEUX ADMINISTRATEURS SUR LA MEME PROPOSITION, C'EST LE CAS NORMAL D'UN ECRAN TRANSVERSAL —
     * `DecisionDejaPrise` se montre, elle ne plante pas l'ecran.
     */
    public function test_une_ligne_deja_decidee_par_un_autre_administrateur_ne_plante_pas_l_ecran(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        $premier = $this->adminGlobal();
        $second = $this->adminGlobal();

        // Le premier administrateur valide reellement la ligne.
        app(FileDePropositions::class)->valider($ligne, $premier);

        // Le second, dont l'ecran montrait encore la ligne, tente de la valider a son tour.
        Livewire::actingAs($second)
            ->test(PropositionsEnAttente::class)
            ->call('valider', $ligne->id)
            ->assertOk()
            ->assertDispatched('toast');

        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
    }

    /** MEME GARDE POUR LE REFUS — une decision deja prise ne se rejoue pas silencieusement. */
    public function test_refuser_une_ligne_deja_decidee_ne_plante_pas_l_ecran(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);
        app(FileDePropositions::class)->refuser($ligne, $this->adminGlobal(), 'un premier motif');

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('ouvrirRefus', $ligne->id)
            ->set('motifRefus', 'un second motif')
            ->call('confirmerRefus')
            ->assertOk()
            ->assertDispatched('toast');

        $this->assertSame('un premier motif', $ligne->fresh()->motif);
    }

    /**
     * CONFIRMER SANS AVOIR OUVERT LE PANNEAU — `abort_if` rend une 404 nette ; sans lui,
     * `findOrFail(null)` leve `ModelNotFoundException` non convertie, mesure en retirant la garde.
     */
    public function test_confirmer_le_refus_sans_ligne_ciblee_rend_une_404(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->set('motifRefus', 'un motif suffisant')
            ->call('confirmerRefus')
            ->assertNotFound();
    }

    // ── `#[Locked]` sur l'identifiant de ligne ────────────────────────────

    public function test_ligne_ciblee_est_verrouillee(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->set('ligneCiblee', 999);
    }

    // ── La capacite garde AUSSI le chemin d'action Livewire ──────────────

    /**
     * `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : sans capacite verifiee sur le
     * composant, un administrateur sans `manage-automation` validerait une proposition en
     * appelant sa methode directement.
     */
    public function test_sans_la_permission_l_ecran_refuse_le_montage_et_la_validation(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminSansLaPermission())
            ->test(PropositionsEnAttente::class)
            ->assertForbidden();

        // L'INSTANTANE EST VALIDE, ET IL NE SUFFIT PAS : monte par un administrateur habilite,
        // il est ensuite rejoue par un autre qui ne l'est pas — exactement `/livewire/update`.
        $composant = Livewire::actingAs($this->adminGlobal())->test(PropositionsEnAttente::class);

        Livewire::actingAs($this->adminSansLaPermission());

        $composant->call('valider', $ligne->id)->assertForbidden();

        $this->assertSame(AutomationAction::RESULTAT_PROPOSEE, $ligne->fresh()->resultat);
    }

    /** TEMOIN — avec la capacite, le meme appel aboutit. */
    public function test_temoin_avec_la_permission_l_ecran_valide(): void
    {
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $ligne = $this->proposition($booking);

        Livewire::actingAs($this->adminGlobal())
            ->test(PropositionsEnAttente::class)
            ->call('valider', $ligne->id);

        $this->assertSame(AutomationAction::RESULTAT_VALIDEE, $ligne->fresh()->resultat);
    }

    // ── Le N+1 signale par la tache 3 ─────────────────────────────────────

    /**
     * `FileDePropositions::enAttente()` n'engageait pas la regle liee (signale par la tache 3) :
     * lire `$ligne->regle->nom` pour N lignes de N regles distinctes ouvrait N requetes en plus
     * de la liste. Mesure ici a 1 (liste) + 1 (regles, chargees en une fois).
     */
    public function test_en_attente_charge_la_regle_en_une_seule_requete_supplementaire(): void
    {
        $bookings = Booking::factory()->count(3)->create(['status' => 'en_attente']);
        foreach ($bookings as $i => $booking) {
            $this->proposition($booking, actionCle: 'action-'.$i, regle: $this->regle(['nom' => 'Règle '.$i]));
        }

        DB::enableQueryLog();
        $lignes = app(FileDePropositions::class)->enAttente();
        foreach ($lignes as $ligne) {
            $ligne->regle->nom;
        }
        $requetes = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $lignes);
        $this->assertCount(2, $requetes, 'La liste, puis les règles en une fois — jamais une requête par ligne.');
    }
}
