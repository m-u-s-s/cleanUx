<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\RecurringBookingSeries;
use App\Models\RecurringTemplate;
use App\Models\User;
use App\Services\Client\Templates\ApplyRecurringTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** AUCUNE RÉSERVATION RÉCURRENTE N'ÉTAIT JAMAIS GÉNÉRÉE. */
class SeriesRecurrentesGenerentTest extends TestCase
{
    use RefreshDatabase;

    public function test_appliquer_un_modele_ne_cree_quune_seule_serie(): void
    {
        $this->appliquer();

        $this->assertSame(
            1,
            RecurringBookingSeries::query()->count(),
            'Le bloc de création était dupliqué : deux séries identiques par application.',
        );
    }

    /** LA SÉRIE EST VISIBLE PAR LA COMMANDE — c'est tout l'enjeu. */
    public function test_la_serie_porte_sa_premiere_echeance(): void
    {
        $serie = $this->appliquer();

        $this->assertNotNull(
            $serie->next_occurrence_at,
            'Sans cette date, la commande planifiée ne verra JAMAIS cette série.',
        );

        $this->assertSame(
            1,
            RecurringBookingSeries::query()
                ->where('status', RecurringBookingSeries::STATUS_ACTIVE)
                ->whereNotNull('next_occurrence_at')
                ->count(),
            'C’est exactement le filtre de `ProcessRecurringBookings`.',
        );
    }

    /** L'HEURE VIENT DU MODÈLE, ET NON D'UN DÉFAUT RÉINVENTÉ. */
    public function test_lheure_de_la_premiere_echeance_est_celle_du_modele(): void
    {
        $serie = $this->appliquer(heureDuModele: '14:30');

        $echeance = Carbon::parse($serie->next_occurrence_at);

        $this->assertSame('14:30', $echeance->format('H:i'));
        $this->assertSame('2026-09-01', $echeance->toDateString());
    }

    /** L'heure demandée par le client l'emporte sur celle du modèle. */
    public function test_lheure_demandee_par_le_client_lemporte(): void
    {
        $serie = $this->appliquer(heureDuModele: '08:00', heureDemandee: '17:15');

        $this->assertSame('17:15', Carbon::parse($serie->next_occurrence_at)->format('H:i'));
    }

    /** LA PREUVE QUI COMPTE : la commande produit vraiment une réservation. */
    public function test_la_commande_planifiee_genere_enfin_une_reservation(): void
    {
        $serie = $this->appliquer();

        // L'échéance est dans le futur : on se place au jour dit, comme le ferait l'ordonnanceur.
        Carbon::setTestNow(Carbon::parse($serie->next_occurrence_at)->addMinute());

        $this->artisan('bookings:process-recurring')->assertSuccessful();

        $this->assertSame(
            1,
            Booking::query()->where('recurring_booking_series_id', $serie->id)->count(),
            'Aucune réservation générée : la série reste invisible ou la génération échoue.',
        );

        // Et l'échéance a avancé : sans cela, la même réservation repartirait à chaque passage.
        $apres = $serie->refresh()->next_occurrence_at;
        $this->assertNotNull($apres);
        $this->assertTrue(Carbon::parse($apres)->greaterThan(Carbon::now()));

        Carbon::setTestNow();
    }

    /** TÉMOIN — un modèle inactif reste refusé. */
    public function test_temoin_un_modele_inactif_est_refuse(): void
    {
        $modele = $this->modele();
        $modele->forceFill(['is_active' => false])->save();

        $this->expectException(\DomainException::class);

        app(ApplyRecurringTemplateService::class)->apply(
            User::factory()->create(),
            $modele->refresh(),
            ['starts_at' => '2026-09-01'],
        );
    }

    /** L'autre garde : une fin avant le début n'a pas de sens. */
    public function test_temoin_une_fin_avant_le_debut_est_refusee(): void
    {
        $this->expectException(\DomainException::class);

        app(ApplyRecurringTemplateService::class)->apply(
            User::factory()->create(),
            $this->modele(),
            ['starts_at' => '2026-09-01', 'ends_at' => '2026-08-01'],
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    private function appliquer(string $heureDuModele = '09:00', ?string $heureDemandee = null): RecurringBookingSeries
    {
        $params = ['starts_at' => '2026-09-01'];

        if ($heureDemandee !== null) {
            $params['custom_time'] = $heureDemandee;
        }

        return app(ApplyRecurringTemplateService::class)->apply(
            User::factory()->create(),
            $this->modele($heureDuModele),
            $params,
        );
    }

    private function modele(string $heure = '09:00'): RecurringTemplate
    {
        return RecurringTemplate::create([
            'slug' => 'menage-hebdo-'.uniqid(),
            'name' => 'Ménage hebdomadaire',
            'frequency' => 'weekly',
            'interval' => 1,
            'default_time' => $heure,
            'default_duration_minutes' => 120,
            'is_active' => true,
        ]);
    }
}
