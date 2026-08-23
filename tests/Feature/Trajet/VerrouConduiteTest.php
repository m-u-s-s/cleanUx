<?php

namespace Tests\Feature\Trajet;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\Question;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\CandidateFinder;
use App\Services\Dispatch\ConduiteRequirements;
use App\Services\Onboarding\ProviderVehicleService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/** SANS PERMIS, ON PERD LES COURSES — ET SEULEMENT LES COURSES. */
class VerrouConduiteTest extends TestCase
{
    use OuvreLeCatalogue, RefreshDatabase;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = ServiceZone::factory()->create(['country_id' => Country::factory()->create()->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function metierDeCourse(bool $taxi = true, ?Carbon $depuis = null): Trade
    {
        $trade = Trade::factory()->create(['taxi_rules' => $taxi]);

        foreach ([LocationRole::PICKUP, LocationRole::DROPOFF] as $role) {
            Question::create([
                'trade_id' => $trade->id,
                'code' => $role,
                'label' => LocationRole::label($role),
                'type' => QuestionType::LOCATION,
                'location_role' => $role,
                'is_active' => true,
            ]);
        }

        // Les dates sont posées par observateur ; on les recule pour sortir de la grâce quand le
        // test veut mesurer le verrou lui-même et non le délai.
        $trade->forceFill([
            'route_rules_since' => $depuis ?? now()->subYear(),
            'taxi_rules_since' => $taxi ? ($depuis ?? now()->subYear()) : null,
        ])->save();

        $this->ouvrirAuCatalogue($trade, $this->zone);

        return $trade->fresh()->load('questions');
    }

    private function prestataire(Trade $trade, bool $enRegle): User
    {
        $user = User::factory()->employe()->create(['is_active' => true]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => ProviderPresence::STATUS_ONLINE,
            'current_lat' => 50.8467,
            'current_lng' => 4.3525,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->attach($trade->id);

        if (! $enRegle) {
            return $user->fresh();
        }

        foreach (app(ConduiteRequirements::class)->typesExiges($trade) as $type) {
            ProviderOnboardingDocument::create([
                'user_id' => $user->id,
                'document_type' => $type,
                'status' => ProviderOnboardingDocument::STATUS_APPROVED,
                'file_path' => "providers/{$user->id}/{$type}.pdf",
            ]);
        }

        app(ProviderVehicleService::class)->declarer($user, [
            'plate' => strtoupper(Str::random(7)),
            'registered_at' => now()->subYear()->toDateString(),
        ]);

        return $user->fresh();
    }

    private function course(Trade $trade): Booking
    {
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'status' => BookingStatus::EN_ATTENTE,
            'trade_id' => $trade->id,
            'service_zone_id' => $this->zone->id,
            'booking_mode' => 'asap',
            'currency' => 'EUR',
            'priority' => 'normal',
            'address' => 'Rue de la Loi 1',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
        ]);
    }

    /** @return list<int> */
    private function candidats(Booking $booking): array
    {
        return app(CandidateFinder::class)
            ->immediate($booking, 20_000)
            ->map(fn ($candidat) => (int) $candidat->user->id)
            ->all();
    }

    public function test_un_conducteur_non_conforme_ne_recoit_pas_la_course(): void
    {
        $trade = $this->metierDeCourse();
        $sansPermis = $this->prestataire($trade, enRegle: false);

        $this->assertNotContains($sansPermis->id, $this->candidats($this->course($trade)));
    }

    /** LE TÉMOIN, et il est indispensable. */
    public function test_un_conducteur_en_regle_recoit_la_course(): void
    {
        $trade = $this->metierDeCourse();
        $conforme = $this->prestataire($trade, enRegle: true);

        $this->assertContains($conforme->id, $this->candidats($this->course($trade)));
    }

    /** LA MOITIÉ QUI COMPTE AUTANT : les autres métiers ne sont pas touchés. */
    public function test_il_garde_les_missions_de_ses_autres_metiers(): void
    {
        $course = $this->metierDeCourse();
        $peinture = Trade::factory()->create();
        $this->ouvrirAuCatalogue($peinture, $this->zone);

        $sansPermis = $this->prestataire($course, enRegle: false);
        $sansPermis->trades()->attach($peinture->id);

        $missionPeinture = $this->course($peinture);
        $missionPeinture->forceFill(['dropoff_lat' => null, 'dropoff_lng' => null])->save();

        $this->assertContains(
            $sansPermis->id,
            $this->candidats($missionPeinture->fresh()),
            'Un verrou posé sur le compte entier punirait quelqu’un pour une pièce qui ne concerne que la moitié de son activité.'
        );
    }

    public function test_la_periode_de_grace_laisse_le_temps_de_se_mettre_en_regle(): void
    {
        // Règle activée AUJOURD'HUI : le délai court, rien ne doit être coupé.
        $trade = $this->metierDeCourse(depuis: now());
        $sansPermis = $this->prestataire($trade, enRegle: false);

        $this->assertFalse(app(ConduiteRequirements::class)->estBloquant($trade));
        $this->assertContains($sansPermis->id, $this->candidats($this->course($trade)));
    }

    public function test_passee_la_grace_le_verrou_s_applique(): void
    {
        $trade = $this->metierDeCourse(depuis: now());
        $sansPermis = $this->prestataire($trade, enRegle: false);

        Carbon::setTestNow(now()->addDays(31));

        $this->assertTrue(app(ConduiteRequirements::class)->estBloquant($trade->fresh()->load('questions')));
        $this->assertNotContains($sansPermis->id, $this->candidats($this->course($trade)));
    }

    public function test_une_piece_perimee_ne_vaut_plus_rien(): void
    {
        $trade = $this->metierDeCourse();
        $conforme = $this->prestataire($trade, enRegle: true);

        $this->assertContains($conforme->id, $this->candidats($this->course($trade)));

        ProviderOnboardingDocument::query()
            ->where('user_id', $conforme->id)
            ->where('document_type', ProviderOnboardingDocument::TYPE_DRIVING_LICENSE)
            ->update(['expires_at' => now()->subDay()->toDateString()]);

        $this->assertNotContains(
            $conforme->id,
            $this->candidats($this->course($trade)),
            'La colonne `expires_at` n’était écrite par personne : un permis approuvé le restait indéfiniment.'
        );
    }

    public function test_un_vehicule_devenu_trop_ancien_sort_du_dispatch(): void
    {
        $trade = $this->metierDeCourse();
        $conforme = $this->prestataire($trade, enRegle: true);

        $this->assertContains($conforme->id, $this->candidats($this->course($trade)));

        // Le véhicule vieillit : c'est précisément ce qu'un contrôle passé une seule fois à
        // l'inscription ne verrait jamais.
        Carbon::setTestNow(now()->addYears(4));

        $this->assertNotContains($conforme->id, $this->candidats($this->course($trade)));
    }

    public function test_le_motif_du_refus_nomme_ce_qui_manque(): void
    {
        $trade = $this->metierDeCourse();
        $sansPermis = $this->prestataire($trade, enRegle: false);

        $manquants = app(ConduiteRequirements::class)->manquantsPour($sansPermis, $trade);

        $this->assertNotEmpty($manquants);
        $this->assertStringContainsString('Permis', implode(' ', $manquants));
    }
}
