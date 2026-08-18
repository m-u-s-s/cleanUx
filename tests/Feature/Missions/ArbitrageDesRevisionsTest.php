<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionDisputeSignal;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionQuoteRevision;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\QuoteRevisionArbiter;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * L'ARBITRE — et surtout ce qu'il REFUSE de conclure.
 *
 * La garde centrale n'est pas « à partir de trois fois, on sanctionne ». C'est « à partir de trois
 * fois CHEZ DEUX CONTREPARTIES DISTINCTES ». Sans le second nombre, un client acharné produirait
 * trois signaux contre le même prestataire et le ferait sanctionner à lui seul — exactement ce que
 * le porteur a demandé d'empêcher.
 */
class ArbitrageDesRevisionsTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        $u = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $u->id, 'status' => 'active']);

        return $u;
    }

    /** Un signal déjà constitué : on teste l'arbitrage, pas le parcours qui le produit. */
    private function signal(User $prestataire, User $client, string $issue, string $cote): MissionDisputeSignal
    {
        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 50.00,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::STARTED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return MissionDisputeSignal::create([
            'mission_id' => $mission->id,
            'booking_id' => $booking->id,
            'quote_revision_id' => null,
            'provider_user_id' => $prestataire->id,
            'client_user_id' => $client->id,
            'signal_code' => 'quote_revision',
            'charged_side' => $cote,
            'outcome' => $issue,
            'verdict' => MissionDisputeSignal::VERDICT_AUCUN,
        ]);
    }

    private function arbitre(): QuoteRevisionArbiter
    {
        return app(QuoteRevisionArbiter::class);
    }

    // ── AUCUNE SANCTION TANT QUE LE MOTIF N'EST PAS ÉTABLI ────────────────────

    public function test_un_premier_refus_ne_sanctionne_personne(): void
    {
        $presta = $this->prestataire();
        $signal = $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);

        $arbitre = $this->arbitre()->arbitrer($signal);

        $this->assertSame(MissionDisputeSignal::VERDICT_AUCUN, $arbitre->verdict);
        $this->assertSame(0, MissionFeatureSuspension::count());
    }

    /**
     * LA GARDE CENTRALE : trois occurrences chez UN SEUL client ne prouvent rien.
     *
     * Sans elle, un client acharné ferait sanctionner un prestataire honnête à lui tout seul.
     */
    public function test_trois_refus_du_meme_client_ne_sanctionnent_pas(): void
    {
        $presta = $this->prestataire();
        $client = User::factory()->client()->create();

        $dernier = null;
        for ($i = 0; $i < 3; $i++) {
            $dernier = $this->signal($presta, $client,
                MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);
        }

        $this->assertSame(MissionDisputeSignal::VERDICT_AUCUN, $this->arbitre()->arbitrer($dernier)->verdict);
        $this->assertSame(0, MissionFeatureSuspension::count());
    }

    /** LE TÉMOIN : les mêmes trois refus, mais chez trois clients, établissent le motif. */
    public function test_trois_refus_chez_des_clients_distincts_sanctionnent(): void
    {
        $presta = $this->prestataire();

        $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);
        $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);
        $dernier = $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);

        $verdict = $this->arbitre()->arbitrer($dernier);

        $this->assertSame(MissionDisputeSignal::VERDICT_PRESTATAIRE, $verdict->verdict);

        $suspension = MissionFeatureSuspension::query()->firstOrFail();
        $this->assertSame($presta->id, $suspension->user_id);
        $this->assertSame(MissionFeatureSuspension::OPTION_REVISION, $suspension->feature);
        $this->assertSame(1, $suspension->level);
        $this->assertSame(14, (int) round($suspension->starts_at->diffInDays($suspension->ends_at)));
    }

    /** Le client aussi : trois devis révisés ACCEPTÉS, chez trois prestataires, le désignent. */
    public function test_un_client_qui_sous_declare_est_designe(): void
    {
        $client = User::factory()->client()->create();

        $this->signal($this->prestataire(), $client,
            MissionDisputeSignal::ISSUE_ACCEPTEE, MissionDisputeSignal::COTE_CLIENT);
        $this->signal($this->prestataire(), $client,
            MissionDisputeSignal::ISSUE_ACCEPTEE, MissionDisputeSignal::COTE_CLIENT);
        $dernier = $this->signal($this->prestataire(), $client,
            MissionDisputeSignal::ISSUE_ACCEPTEE, MissionDisputeSignal::COTE_CLIENT);

        $this->assertSame(MissionDisputeSignal::VERDICT_CLIENT, $this->arbitre()->arbitrer($dernier)->verdict);
        $this->assertSame(
            MissionFeatureSuspension::OPTION_COMMANDE,
            MissionFeatureSuspension::query()->firstOrFail()->feature,
        );
    }

    // ── LES PALIERS ───────────────────────────────────────────────────────────

    public function test_la_sanction_est_graduee_puis_definitive(): void
    {
        $presta = $this->prestataire();

        // Palier 1 posé à la main, déjà expiré : c'est le compte des SANCTIONS qui fait le palier,
        // jamais le compte des signaux. Deux mesures donneraient deux paliers pour une personne.
        MissionFeatureSuspension::create([
            'user_id' => $presta->id,
            'feature' => MissionFeatureSuspension::OPTION_REVISION,
            'level' => 1,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(16),
            'reason' => 'Premier motif',
        ]);

        $signal = $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);

        $deuxieme = $this->arbitre()->sanctionner($presta, MissionFeatureSuspension::OPTION_REVISION, $signal);

        $this->assertSame(2, $deuxieme->level);
        $this->assertSame(60, (int) round($deuxieme->starts_at->diffInDays($deuxieme->ends_at)));

        $deuxieme->forceFill(['ends_at' => now()->subDay()])->save();
        $troisieme = $this->arbitre()->sanctionner($presta, MissionFeatureSuspension::OPTION_REVISION, $signal);

        $this->assertSame(3, $troisieme->level);
        $this->assertNull($troisieme->ends_at, 'la troisième est définitive');
        $this->assertTrue($troisieme->estDefinitive());
    }

    public function test_on_ne_superpose_pas_deux_peines(): void
    {
        $presta = $this->prestataire();
        $signal = $this->signal($presta, User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_POURSUITE, MissionDisputeSignal::COTE_PRESTATAIRE);

        $this->arbitre()->sanctionner($presta, MissionFeatureSuspension::OPTION_REVISION, $signal);
        $seconde = $this->arbitre()->sanctionner($presta, MissionFeatureSuspension::OPTION_REVISION, $signal);

        $this->assertNull($seconde);
        $this->assertSame(1, MissionFeatureSuspension::count());
    }

    // ── L'ENTENTE ─────────────────────────────────────────────────────────────

    /**
     * Deux arrêts sur le MÊME COUPLE ne sanctionnent pas : ils envoient en revue humaine. Une
     * sanction automatique frapperait aussi deux personnes qui se retrouvent par hasard.
     */
    public function test_deux_arrets_sur_le_meme_couple_partent_en_revue(): void
    {
        $presta = $this->prestataire();
        $client = User::factory()->client()->create();

        $this->signal($presta, $client,
            MissionDisputeSignal::ISSUE_REFUSEE_ARRET, MissionDisputeSignal::COTE_PRESTATAIRE);
        $second = $this->signal($presta, $client,
            MissionDisputeSignal::ISSUE_REFUSEE_ARRET, MissionDisputeSignal::COTE_PRESTATAIRE);

        $this->assertSame(MissionDisputeSignal::VERDICT_INDECIS, $this->arbitre()->arbitrer($second)->verdict);
        $this->assertSame(0, MissionFeatureSuspension::count());
    }

    /** LE TÉMOIN : un seul arrêt sur ce couple n'éveille rien. */
    public function test_un_arret_isole_n_est_pas_une_entente(): void
    {
        $signal = $this->signal($this->prestataire(), User::factory()->client()->create(),
            MissionDisputeSignal::ISSUE_REFUSEE_ARRET, MissionDisputeSignal::COTE_PRESTATAIRE);

        $this->assertFalse($this->arbitre()->ententeSuspectee($signal));
    }
}
