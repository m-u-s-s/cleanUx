<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LA MARGE DU TABLEAU DE BORD ADMINISTRATEUR. */
class AdminAnalyticsMargeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AdminAnalyticsService
    {
        return app(AdminAnalyticsService::class);
    }

    /** LA NOTE MOYENNE VIENT DE LA TABLE DU MODÈLE, PAS D'UNE AUTRE. */
    #[Test]
    public function la_note_moyenne_ne_depend_pas_d_une_table_sans_modele(): void
    {
        Feedback::factory()->create(['note' => 4]);
        Feedback::factory()->create(['note' => 2]);

        $this->assertSame(3.0, $this->service()->overview()['average_rating']);
    }

    /** TÉMOIN POSITIF. */
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

        // LES DEUX BASES SONT DISTINCTES, ET CE TEST EXISTE POUR QUE PERSONNE NE L'OUBLIE.
        $this->assertSame(200.0, $apercu['total_revenue']);
        $this->assertSame(40.0, $apercu['total_margin']);
    }
}
