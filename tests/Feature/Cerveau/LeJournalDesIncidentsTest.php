<?php

namespace Tests\Feature\Cerveau;

use App\Models\CodeIncident;
use App\Models\User;
use App\Services\Cerveau\Cerveau;
use App\Services\Cerveau\ClasseurDIncidents;
use App\Services\Cerveau\JournalDesIncidents;
use App\Services\Cerveau\RegistreDesGestes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;
use TypeError;

/**
 * LE JOURNAL DES INCIDENTS — voir, expliquer, contenir. Jamais réécrire le code.
 *
 * DEUX CHOSES SE PROUVENT ICI, et la seconde compte autant :
 *   — il groupe, compte et explique (sinon il ne sert à rien) ;
 *   — il ne casse JAMAIS à son tour. Il tourne dans le gestionnaire d'exceptions : s'il lève,
 *     il remplace le défaut réel par le sien, et le vrai devient invisible.
 */
class LeJournalDesIncidentsTest extends TestCase
{
    use RefreshDatabase;

    // ── Enregistrer ────────────────────────────────────────────────────────

    public function test_une_erreur_devient_un_incident(): void
    {
        $incident = app(JournalDesIncidents::class)->enregistrer(new RuntimeException('boum'));

        $this->assertNotNull($incident);
        $this->assertSame(1, $incident->occurrences);
        $this->assertSame(RuntimeException::class, $incident->exception_class);
    }

    /**
     * LA MÊME ERREUR DEUX CENTS FOIS N'EST PAS DEUX CENTS INCIDENTS.
     *
     * Un journal qui les empile ligne à ligne est illisible au bout d'une heure — c'est pour ça
     * que personne ne lit les journaux.
     */
    public function test_la_meme_erreur_ne_fait_qu_un_incident(): void
    {
        $journal = app(JournalDesIncidents::class);

        $erreur = new RuntimeException('boum');
        $journal->enregistrer($erreur);
        $journal->enregistrer($erreur);
        $journal->enregistrer($erreur);

        $this->assertSame(1, CodeIncident::query()->count());
        $this->assertSame(3, CodeIncident::query()->first()?->occurrences);
    }

    /** TÉMOIN — deux erreurs différentes font bien deux incidents. */
    public function test_temoin_deux_erreurs_differentes_font_deux_incidents(): void
    {
        $journal = app(JournalDesIncidents::class);

        $journal->enregistrer(new RuntimeException('boum'));
        $journal->enregistrer(new TypeError('autre chose'));

        $this->assertSame(2, CodeIncident::query()->count());
    }

    /**
     * CENT FOIS UNE PERSONNE ET UNE FOIS CENT PERSONNES N'APPELLENT PAS LA MÊME RÉACTION.
     */
    public function test_il_compte_les_personnes_touchees_sans_les_doubler(): void
    {
        $journal = app(JournalDesIncidents::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $erreur = new RuntimeException('boum');

        $journal->enregistrer($erreur, $a->id);
        $journal->enregistrer($erreur, $a->id);
        $journal->enregistrer($erreur, $b->id);

        $incident = CodeIncident::query()->first();

        $this->assertSame(3, $incident?->occurrences);
        $this->assertSame(2, $incident?->utilisateurs_touches);
    }

    /**
     * UNE ERREUR QUI REVIENT APRÈS AVOIR ÉTÉ RÉSOLUE SE ROUVRE.
     *
     * La refermer d'office ferait disparaître une régression — exactement ce qu'on veut voir.
     */
    public function test_une_erreur_resolue_qui_revient_se_rouvre(): void
    {
        $journal = app(JournalDesIncidents::class);
        $erreur = new RuntimeException('boum');

        $incident = $journal->enregistrer($erreur);
        $incident?->forceFill(['statut' => CodeIncident::RESOLU])->save();

        $journal->enregistrer($erreur);

        $this->assertSame(CodeIncident::OUVERT, CodeIncident::query()->first()?->statut);
    }

    /**
     * LE JOURNAL NE CASSE JAMAIS À SON TOUR.
     *
     * Il tourne dans le gestionnaire d'exceptions : s'il lève, il remplace le défaut réel par le
     * sien. On lui donne donc une erreur pendant que la table est absente.
     */
    public function test_il_se_tait_quand_sa_propre_table_manque(): void
    {
        Schema::drop('code_incident_victims');
        Schema::drop('code_incidents');

        // AUCUNE EXCEPTION NE DOIT SORTIR D'ICI.
        $this->assertNull(app(JournalDesIncidents::class)->enregistrer(new RuntimeException('boum')));
    }

    // ── Classer et expliquer ───────────────────────────────────────────────

    /** LA FAMILLE LA PLUS COÛTEUSE ICI : le code parle d'une colonne que la base n'a pas. */
    public function test_il_reconnait_une_derive_de_schema(): void
    {
        $famille = app(ClasseurDIncidents::class)
            ->famille(QueryException::class, 'SQLSTATE[HY000]: General error: 1 no such column: bookings.truc');

        $this->assertSame(ClasseurDIncidents::FAMILLE_SCHEMA, $famille);
    }

    public function test_il_reconnait_une_donnee_absente(): void
    {
        $famille = app(ClasseurDIncidents::class)
            ->famille(ModelNotFoundException::class, 'No query results for model [App\Models\Booking] 42');

        $this->assertSame(ClasseurDIncidents::FAMILLE_DONNEE_ABSENTE, $famille);
    }

    public function test_il_reconnait_une_lecture_sur_du_vide(): void
    {
        $famille = app(ClasseurDIncidents::class)
            ->famille(\Error::class, 'Call to a member function titre() on null');

        $this->assertSame(ClasseurDIncidents::FAMILLE_NUL, $famille);
    }

    /** TÉMOIN — une erreur inconnue est nommée comme telle, pas rangée de force. */
    public function test_temoin_une_erreur_inconnue_reste_inconnue(): void
    {
        $famille = app(ClasseurDIncidents::class)->famille(RuntimeException::class, 'quelque chose');

        $this->assertSame(ClasseurDIncidents::FAMILLE_INCONNUE, $famille);
    }

    /** CHAQUE FAMILLE DIT CE QUI SE PASSE, CE QUE ÇA IMPLIQUE, ET OÙ REGARDER. */
    public function test_chaque_explication_dit_les_trois_choses(): void
    {
        $classeur = app(ClasseurDIncidents::class);

        foreach ([
            ClasseurDIncidents::FAMILLE_SCHEMA,
            ClasseurDIncidents::FAMILLE_DONNEE_ABSENTE,
            ClasseurDIncidents::FAMILLE_ACCES,
            ClasseurDIncidents::FAMILLE_NUL,
            ClasseurDIncidents::FAMILLE_VUE,
            ClasseurDIncidents::FAMILLE_TIERS,
            ClasseurDIncidents::FAMILLE_INCONNUE,
        ] as $famille) {
            $incident = new CodeIncident(['famille' => $famille, 'file' => '/a/b.php', 'line' => 1]);
            $lecture = $classeur->expliquer($incident);

            $this->assertNotEmpty($lecture['titre'], $famille);
            $this->assertNotEmpty($lecture['cause'], $famille);
            $this->assertNotEmpty($lecture['implique'], $famille);
            $this->assertNotEmpty($lecture['regarder'], $famille);
        }
    }

    // ── Le cerveau en parle ────────────────────────────────────────────────

    public function test_le_cerveau_remonte_l_incident(): void
    {
        app(JournalDesIncidents::class)->enregistrer(new RuntimeException('boum'));

        $recommandations = app(Cerveau::class)->recommandations('incident');

        $this->assertNotEmpty($recommandations);
        $this->assertStringContainsString('1 fois', $recommandations[0]->constat);
    }

    /** UN INCIDENT CONTENU NE REMONTE PLUS — c'est tout ce que « contenir » veut dire. */
    public function test_un_incident_contenu_ne_remonte_plus(): void
    {
        $incident = app(JournalDesIncidents::class)->enregistrer(new RuntimeException('boum'));

        app(Cerveau::class)->appliquer(
            $this->titulaire(),
            RegistreDesGestes::CONTENIR_INCIDENT,
            ['id' => $incident?->id],
        );

        $this->assertEmpty(app(Cerveau::class)->recommandations('incident'));
        $this->assertSame(CodeIncident::CONTENU, $incident?->fresh()->statut);
    }

    /**
     * CONTENIR NE CORRIGE RIEN, ET LE GESTE LE DIT.
     *
     * Un serveur qui réécrit son propre PHP est à la fois une faille et une panne en puissance :
     * le geste doit annoncer sa propre limite, sinon il ment.
     */
    public function test_le_geste_annonce_qu_il_ne_corrige_pas(): void
    {
        $geste = app(RegistreDesGestes::class)->trouver(RegistreDesGestes::CONTENIR_INCIDENT);

        $this->assertNotNull($geste);
        $this->assertStringContainsString('NE CORRIGE RIEN', $geste->implique);
    }

    /** LE COMPTEUR CONTINUE MÊME CONTENU : si l'erreur revient, on doit pouvoir le voir. */
    public function test_un_incident_contenu_continue_d_etre_compte(): void
    {
        $journal = app(JournalDesIncidents::class);
        $erreur = new RuntimeException('boum');
        $incident = $journal->enregistrer($erreur);

        $incident?->forceFill(['statut' => CodeIncident::CONTENU])->save();
        $journal->enregistrer($erreur);

        $frais = $incident?->fresh();

        $this->assertSame(2, $frais?->occurrences);
        $this->assertSame(CodeIncident::CONTENU, $frais?->statut);
    }

    private function titulaire(): User
    {
        return $this->prendreLeSiege(['role' => 'admin']);
    }
}
