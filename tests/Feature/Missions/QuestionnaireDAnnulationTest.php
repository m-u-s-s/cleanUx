<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\CancellationExemptReason;
use App\Models\CancellationQuestionOption;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Cancellation\CancellationExemptQuota;
use App\Services\Cancellation\CancellationQuestionnaireService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Database\Seeders\CancellationQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LE QUESTIONNAIRE D'ANNULATION — et ce qui le distingue d'un menu d'évitement de frais.
 *
 * Le moteur v2 honorait déjà `reason_code` et l'exemption ; il n'a jamais reçu de code, l'API
 * n'acceptant qu'un texte libre. Ces tests portent sur les deux garanties qui rendent le
 * questionnaire honnête :
 *
 *   - une option dont la vérification échoue n'est PAS PROPOSÉE ;
 *   - un motif exempté cesse d'exonérer au-delà de son plafond par personne.
 */
class QuestionnaireDAnnulationTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPoliciesSeeder::class);
        $this->seed(CancellationQuestionnaireSeeder::class);
    }

    private function reservation(string $moteur = 'domicile', ?Carbon $prevu = null): Booking
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $prevu ??= Carbon::now()->addHours(6);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 120.00,
            'estimated_price' => 120.00,
            'scheduled_at' => $prevu,
            'date' => $prevu->toDateString(),
            'heure' => $prevu->format('H:i:s'),
        ] + match ($moteur) {
            'vehicule' => ['dropoff_lat' => 50.9010, 'dropoff_lng' => 4.4844],
            'horaire' => ['purchased_minutes' => 180],
            default => [],
        });

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => MissionStatus::ASSIGNED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $booking->fresh();
    }

    private function questionnaire(): CancellationQuestionnaireService
    {
        return app(CancellationQuestionnaireService::class);
    }

    /** @return list<string> */
    private function codes(array $questions): array
    {
        return collect($questions)->flatMap(fn (array $q) => collect($q['options'])->pluck('code'))->all();
    }

    // ── UNE OPTION NON SOUTENUE N'EST PAS PROPOSÉE ────────────────────────────

    /**
     * LE PRESTATAIRE N'EST PAS EN RETARD — l'option ne doit pas exister à l'écran.
     *
     * La proposer puis la refuser se lirait comme une panne, et la personne recommencerait.
     */
    public function test_sans_retard_l_option_retard_n_est_pas_proposee(): void
    {
        $booking = $this->reservation(prevu: Carbon::now()->addHours(6));

        $codes = $this->codes($this->questionnaire()->pour($this->client, $booking, 'client'));

        $this->assertNotContains('client_provider_late', $codes);
        $this->assertContains('client_no_longer_needed', $codes, 'le reste du questionnaire est bien là');
    }

    /** LE TÉMOIN : l'heure prévue passée et rien de démarré, l'option apparaît. */
    public function test_avec_retard_l_option_retard_apparait(): void
    {
        $booking = $this->reservation(prevu: Carbon::now()->subMinutes(40));

        $codes = $this->codes($this->questionnaire()->pour($this->client, $booking, 'client'));

        $this->assertContains('client_provider_late', $codes);
    }

    /** Le piège à entente est toujours proposé : il ne se vérifie pas, il engage. */
    public function test_le_piege_a_entente_est_toujours_propose(): void
    {
        $booking = $this->reservation();

        $this->assertContains(
            'client_provider_asked',
            $this->codes($this->questionnaire()->pour($this->client, $booking, 'client')),
        );
    }

    // ── LE MOTEUR DÉCIDE QUELLES QUESTIONS EXISTENT ──────────────────────────

    /**
     * « Le travail ne correspond pas » renvoie vers le nouveau devis — qui n'existe que sur le
     * moteur à domicile. Sur une course, l'option ne doit pas exister : elle ne mènerait qu'à un
     * refus que le chauffeur ne pourrait pas comprendre.
     */
    public function test_l_aiguillage_vers_le_devis_n_existe_pas_sur_une_course(): void
    {
        $booking = $this->reservation('vehicule');
        $codes = $this->codes($this->questionnaire()->pour($this->prestataire, $booking, 'provider'));

        $this->assertNotContains('provider_scope_mismatch', $codes);
        $this->assertContains('provider_unable', $codes, 'le questionnaire général reste posé');
    }

    /** LE TÉMOIN : sur une mission à domicile, l'aiguillage existe. */
    public function test_l_aiguillage_vers_le_devis_existe_a_domicile(): void
    {
        $booking = $this->reservation('domicile');
        $codes = $this->codes($this->questionnaire()->pour($this->prestataire, $booking, 'provider'));

        $this->assertContains('provider_scope_mismatch', $codes);
        $this->assertContains('provider_scope_too_big_alone', $codes);
    }

    public function test_le_client_ne_voit_pas_les_questions_du_prestataire(): void
    {
        $booking = $this->reservation();
        $codes = $this->codes($this->questionnaire()->pour($this->client, $booking, 'client'));

        $this->assertNotContains('provider_unable', $codes);
    }

    /** L'aiguillage se DIT à l'écran : il ne va pas annuler, et l'écran doit le savoir. */
    public function test_une_option_d_aiguillage_se_declare_comme_telle(): void
    {
        $booking = $this->reservation('domicile');
        $questions = $this->questionnaire()->pour($this->prestataire, $booking, 'provider');

        $option = collect($questions)
            ->flatMap(fn (array $q) => $q['options'])
            ->firstWhere('code', 'provider_scope_mismatch');

        $this->assertTrue($option['redirects']);
        $this->assertSame(CancellationQuestionOption::ISSUE_VERS_DEVIS, $option['outcome']);
    }

    // ── LE PLAFOND D'EXEMPTION ────────────────────────────────────────────────

    /**
     * « PAS LA PREMIÈRE FOIS, MAIS SI C'EST FRÉQUENT ».
     *
     * `max_per_user_per_30d` était déclarée, semée à 2, et appliquée par personne : l'urgence
     * médicale — le motif le plus généreux du barème — exonérait autant de fois que voulu.
     */
    public function test_le_plafond_par_personne_finit_par_mordre(): void
    {
        $booking = $this->reservation();
        $motif = CancellationExemptReason::query()->where('reason_code', 'provider_unable')->firstOrFail();
        $quota = app(CancellationExemptQuota::class);

        $this->assertSame(2, $motif->max_per_user_per_30d, 'le plafond semé');
        $this->assertTrue($quota->exonereEncore($motif, $this->prestataire->id), 'première fois : exonéré');

        $this->exemptionPassee($booking->id, 'provider_unable', $this->prestataire);
        $this->assertTrue($quota->exonereEncore($motif, $this->prestataire->id), 'deuxième : encore');

        $this->exemptionPassee($booking->id, 'provider_unable', $this->prestataire);
        $this->assertFalse($quota->exonereEncore($motif, $this->prestataire->id), 'troisième : le plafond mord');
    }

    /** LE TÉMOIN : un motif sans plafond exonère indéfiniment — c'est le cas du piège à entente. */
    public function test_un_motif_sans_plafond_exonere_toujours(): void
    {
        $booking = $this->reservation();
        $motif = CancellationExemptReason::query()->where('reason_code', 'client_asked_cancel')->firstOrFail();

        for ($i = 0; $i < 5; $i++) {
            $this->exemptionPassee($booking->id, 'client_asked_cancel', $this->prestataire);
        }

        $this->assertNull($motif->max_per_user_per_30d);
        $this->assertTrue(app(CancellationExemptQuota::class)->exonereEncore($motif, $this->prestataire->id));
    }

    /** Les exemptions de PLUS DE TRENTE JOURS ne comptent plus : on peut se corriger. */
    public function test_les_exemptions_anciennes_ne_comptent_plus(): void
    {
        $booking = $this->reservation();
        $motif = CancellationExemptReason::query()->where('reason_code', 'provider_unable')->firstOrFail();

        $this->exemptionPassee($booking->id, 'provider_unable', $this->prestataire, Carbon::now()->subDays(45));
        $this->exemptionPassee($booking->id, 'provider_unable', $this->prestataire, Carbon::now()->subDays(40));

        $this->assertSame(0, app(CancellationExemptQuota::class)
            ->usagesRecents('provider_unable', $this->prestataire->id));
    }

    /**
     * Une exemption qui n'a PAS été appliquée ne compte pas contre la personne — sinon le premier
     * dépassement en produirait un second, puis un troisième, sans qu'elle ait rien obtenu.
     */
    public function test_un_motif_invoque_sans_exemption_ne_compte_pas(): void
    {
        $booking = $this->reservation();

        $this->exemptionPassee($booking->id, 'provider_unable', $this->prestataire, exemptApplied: false);

        $this->assertSame(0, app(CancellationExemptQuota::class)
            ->usagesRecents('provider_unable', $this->prestataire->id));
    }

    private function exemptionPassee(
        int $bookingId,
        string $code,
        User $acteur,
        ?Carbon $quand = null,
        bool $exemptApplied = true,
    ): void {
        BookingCancellationV2::create([
            'booking_id' => $bookingId,
            'cancelled_by_user_id' => $acteur->id,
            'actor_role' => 'provider',
            'reason_code' => $code,
            'fee_percent_applied' => 0,
            'fee_amount_cents' => 0,
            'refund_amount_cents' => 0,
            'currency' => 'EUR',
            'exempt_applied' => $exemptApplied,
            'cancelled_at' => $quand ?? Carbon::now(),
        ]);
    }
}
