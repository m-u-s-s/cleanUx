<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA MARGE DU TABLEAU DE BORD ADMINISTRATEUR.
 *
 * Elle affichait 0,00 € depuis toujours, et aucun test ne l'a jamais regardée — c'est exactement à
 * cela que ressemble un calcul mort. Le code cherchait une colonne `margin`, puis `marge`, sur une
 * table qui n'a jamais porté ni l'une ni l'autre, et interrogeait de surcroît une table différente
 * de celle qu'il additionnait. Les deux gardes se refermaient sur rien et la carte annonçait zéro
 * avec l'aplomb d'un résultat.
 *
 * CE QUI REND CE FICHIER UTILE PLUTÔT QUE DÉCORATIF : un test qui vérifierait « la marge vaut zéro
 * quand il n'y a pas de réservation » serait passé au vert pendant toutes ces années. Il faut donc
 * qu'une commission NON NULLE apparaisse dans le total — c'est la seule assertion que l'ancien code
 * ne pouvait pas satisfaire.
 */
class AdminAnalyticsMargeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AdminAnalyticsService
    {
        return app(AdminAnalyticsService::class);
    }

    /**
     * LA NOTE MOYENNE VIENT DE LA TABLE DU MODÈLE, PAS D'UNE AUTRE.
     *
     * Deuxième occurrence, dans ce même service, du défaut décrit en tête de fichier : le garde
     * interrogeait le schéma de `feedbacks` pour décider quelle colonne moyenner, puis moyennait
     * sur `Feedback::query()` — c'est-à-dire sur `feedback`, que le modèle désigne explicitement.
     * Deux tables réelles et distinctes, l'une avec modèle et 34 colonnes, l'autre sans modèle et
     * avec 15.
     *
     * Cela fonctionnait par COÏNCIDENCE : les deux portent une colonne `note`. Le jour où la table
     * sans modèle disparaît — ce qui est prévu — `hasTable()` rend faux, aucune branche ne
     * s'exécute, et la note moyenne tombe à zéro sans erreur ni trace, pendant que les avis
     * continuent d'être enregistrés.
     */
    #[Test]
    public function la_note_moyenne_ne_depend_pas_d_une_table_sans_modele(): void
    {
        Feedback::factory()->create(['note' => 4]);
        Feedback::factory()->create(['note' => 2]);

        $this->assertSame(3.0, $this->service()->overview()['average_rating']);
    }

    /**
     * TÉMOIN POSITIF. Sans lui, le test ci-dessus passerait au vert sur un service qui rendrait
     * toujours la même valeur : il faut vérifier que l'absence d'avis donne bien zéro, et que ce
     * zéro-là est un vrai zéro et non le zéro par défaut d'un garde refermé.
     */
    #[Test]
    public function sans_aucun_avis_la_note_moyenne_est_nulle(): void
    {
        $this->assertSame(0.0, $this->service()->overview()['average_rating']);
    }

    #[Test]
    public function la_marge_additionne_les_commissions_reellement_retenues(): void
    {
        Booking::factory()->create(['platform_fee_cents' => 1250, 'status' => 'termine']);
        Booking::factory()->create(['platform_fee_cents' => 3075, 'status' => 'termine']);

        // 12,50 € + 30,75 €. L'assertion porte sur des centimes convertis en euros : c'est là que se
        // logent les erreurs de facteur 100, et la carte affiche des euros.
        $this->assertSame(43.25, $this->service()->overview()['total_margin']);
    }

    #[Test]
    public function une_reservation_sans_commission_ne_pese_rien(): void
    {
        Booking::factory()->create(['platform_fee_cents' => 2000, 'status' => 'termine']);

        // Un devis en attente n'a encore rien rapporté : le compter reviendrait à annoncer une
        // marge sur de l'argent qui n'a pas bougé.
        Booking::factory()->create(['platform_fee_cents' => 0, 'status' => 'en_attente']);
        Booking::factory()->create(['platform_fee_cents' => null, 'status' => 'annule']);

        $this->assertSame(20.0, $this->service()->overview()['total_margin']);
    }

    #[Test]
    public function sans_aucune_reservation_la_marge_est_nulle(): void
    {
        // Zéro reste zéro — mais désormais parce qu'il n'y a rien à additionner, pas parce que la
        // colonne lue n'existe pas.
        $this->assertSame(0.0, $this->service()->overview()['total_margin']);
    }

    #[Test]
    public function les_deux_clefs_de_marge_portent_la_meme_valeur(): void
    {
        Booking::factory()->create(['platform_fee_cents' => 900, 'status' => 'termine']);

        $apercu = $this->service()->overview();

        // La vue lit `total_margin` ; d'autres composants lisent `totalMargin`. Les laisser diverger
        // ferait afficher deux marges différentes selon l'écran.
        $this->assertSame($apercu['total_margin'], $apercu['totalMargin']);
    }

    #[Test]
    public function le_chiffre_d_affaires_reste_sur_sa_propre_base(): void
    {
        Booking::factory()->create([
            'devis_estime' => 200.0,
            'platform_fee_cents' => 4000,
            'status' => 'termine',
        ]);

        $apercu = $this->service()->overview();

        /*
         * LES DEUX BASES SONT DISTINCTES, ET CE TEST EXISTE POUR QUE PERSONNE NE L'OUBLIE. Le
         * chiffre d'affaires additionne des devis, la marge additionne des commissions encaissées.
         * Les deux cartes se touchent sur l'écran, et il serait tentant d'en tirer un taux — 40 sur
         * 200 ne fait pas 20 % de commission, parce que le dénominateur inclut des réservations
         * annulées ou jamais payées.
         */
        $this->assertSame(200.0, $apercu['total_revenue']);
        $this->assertSame(40.0, $apercu['total_margin']);
    }
}
