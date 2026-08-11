<?php

namespace Tests\Feature\Workforce;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\LeaveRequest;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\Workforce\ProfitabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES CONGÉS (E21) ET LA RENTABILITÉ (E22).
 *
 * E21 — CE QUI COMPTE N'EST PAS LE TABLEAU DES CONGÉS, c'est qu'une demande APPROUVÉE empêche
 * l'assignation. Sans ce lien, le prestataire reçoit sa course le premier jour de ses vacances,
 * refuse, et le moteur cherche quelqu'un d'autre — après avoir perdu vingt secondes et une occasion.
 *
 * E22 — UNE SOCIÉTÉ SAIT CE QU'ELLE FACTURE, PAS CE QUE ÇA LUI COÛTE. C'est pourtant la question
 * qui décide de renégocier un contrat, ou de s'apercevoir qu'un site précis mange toute la marge des
 * autres. Les trois termes du calcul n'existaient pas avant cette phase : les heures viennent des
 * pointages (E20), les consommables des mouvements d'inventaire (E23 et F7).
 *
 * ET LE COÛT HORAIRE EST UNE HYPOTHÈSE, QUI SE DIT. La plateforme ne connaît pas les salaires : une
 * marge calculée sur un taux inventé et présentée sans réserve serait plus dangereuse qu'une absence
 * de marge — on la lirait comme un fait.
 */
class CongesEtRentabiliteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvecSalarie(): array
    {
        $organisation = OrganizationAccount::factory()->create();

        $worker = User::factory()->employe()->create([
            'organization_account_id' => $organisation->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'role' => 'worker',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$organisation, $worker];
    }

    // ── E21 : les congés ─────────────────────────────────────────────────────

    #[Test]
    public function un_conge_approuve_rend_indisponible(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $jour = Carbon::now()->addDays(4)->startOfDay();

        LeaveRequest::factory()->approuvee()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_on' => Carbon::now()->addDays(3)->toDateString(),
            'ends_on' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        /*
         * C'EST TOUT L'INTÉRÊT DE LA FONCTIONNALITÉ. Une demande approuvée qui n'empêche pas
         * l'assignation ne sert qu'à faire un tableau — et le prestataire reçoit sa course le
         * premier jour de ses vacances.
         */
        $this->assertFalse(
            app(WorkerAvailabilityService::class)
                ->libresPour($organisation->id, $jour->copy()->addHours(10))[$worker->id],
        );
    }

    #[Test]
    public function une_demande_en_attente_ne_bloque_rien(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        LeaveRequest::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_on' => Carbon::now()->addDays(3)->toDateString(),
            'ends_on' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        // Tant que personne n'a tranché, le salarié est censé travailler : bloquer sur une demande
        // non traitée ferait perdre des créneaux qu'un refus aurait rendus.
        $this->assertTrue(
            app(WorkerAvailabilityService::class)
                ->libresPour($organisation->id, Carbon::now()->addDays(4)->setTime(10, 0))[$worker->id],
        );
    }

    #[Test]
    public function le_dernier_jour_du_conge_est_couvert(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        LeaveRequest::factory()->approuvee()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_on' => Carbon::now()->addDays(3)->toDateString(),
            'ends_on' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        // Bornes inclusives : exclure le dernier jour ferait travailler quelqu'un à la fin de ses
        // vacances, ce qu'aucun formulaire ne laisse supposer.
        $this->assertFalse(
            app(WorkerAvailabilityService::class)
                ->libresPour($organisation->id, Carbon::now()->addDays(7)->setTime(10, 0))[$worker->id],
        );
    }

    #[Test]
    public function le_conge_l_emporte_meme_sur_un_shift_publie(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();
        $jour = Carbon::now()->addDays(4)->startOfDay();

        Shift::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_at' => $jour->copy()->addHours(8),
            'ends_at' => $jour->copy()->addHours(17),
        ]);

        LeaveRequest::factory()->approuvee()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_on' => $jour->toDateString(),
            'ends_on' => $jour->toDateString(),
        ]);

        // Un planning saisi des semaines à l'avance ne connaît pas le congé posé depuis : c'est
        // l'absence qui tranche, jamais le planning.
        $this->assertFalse(
            app(WorkerAvailabilityService::class)
                ->libresPour($organisation->id, $jour->copy()->addHours(10))[$worker->id],
        );
    }

    // ── E22 : la rentabilité ─────────────────────────────────────────────────

    /** @return array{0: OrganizationAccount, 1: Mission, 2: User} */
    private function missionFacturee(float $devis = 200.0): array
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $booking = Booking::factory()->create(['devis_estime' => $devis]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill(['provider_organization_id' => $organisation->id])->save();

        return [$organisation, $mission->fresh(), $worker];
    }

    #[Test]
    public function la_marge_deduit_les_heures_et_les_consommables(): void
    {
        [$organisation, $mission, $worker] = $this->missionFacturee(200.0);

        // Trois heures pointées, au taux prudent par défaut de 22 €.
        TimeEntry::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'mission_id' => $mission->id,
            'worked_minutes' => 180,
            'status' => TimeEntry::STATUS_RECORDED,
        ]);

        $article = InventoryItem::factory()->create([
            'organization_account_id' => $organisation->id,
            'quantity' => 10,
            'unit_cost_cents' => 450,
        ]);

        app(InventoryService::class)->consommer($article, 2, $worker, $mission);

        $resultat = app(ProfitabilityService::class)->pourLaMission($mission);

        $this->assertSame(20000, $resultat['revenue_cents']);
        $this->assertSame(6600, $resultat['labour_cost_cents']);
        // Les mouvements de consommation portent une quantité NÉGATIVE : les sommer tel quel
        // produirait un coût négatif qui viendrait gonfler la marge.
        $this->assertSame(900, $resultat['consumables_cost_cents']);
        $this->assertSame(12500, $resultat['margin_cents']);
    }

    #[Test]
    public function une_mission_sans_pointage_le_dit(): void
    {
        [, $mission] = $this->missionFacturee(200.0);

        $resultat = app(ProfitabilityService::class)->pourLaMission($mission);

        /*
         * SANS CE DRAPEAU, la mission afficherait une marge de 100 % — et l'agrégat d'un site
         * entier paraîtrait florissant parce que personne n'y a pointé. Une rentabilité flatteuse
         * et fausse est pire que pas de rentabilité du tout.
         */
        $this->assertFalse($resultat['has_timesheet']);
        $this->assertSame(0, $resultat['worked_minutes']);
    }

    #[Test]
    public function le_taux_horaire_dit_s_il_est_declare_ou_suppose(): void
    {
        [$organisation, $mission] = $this->missionFacturee();

        $resultat = app(ProfitabilityService::class)->pourLaMission($mission);

        // Une marge calculée sur un taux inventé et présentée sans réserve se lirait comme un fait.
        $this->assertSame('default', $resultat['hourly_rate_source']);
        $this->assertSame(ProfitabilityService::DEFAULT_HOURLY_COST_CENTS, $resultat['hourly_rate_cents']);

        $organisation->forceFill([
            'metadata' => ['workforce' => ['hourly_cost_cents' => 2800]],
        ])->save();

        $resultat = app(ProfitabilityService::class)->pourLaMission($mission->fresh());

        $this->assertSame('declared', $resultat['hourly_rate_source']);
        $this->assertSame(2800, $resultat['hourly_rate_cents']);
    }

    #[Test]
    public function l_agregat_compte_a_part_les_missions_sans_pointage(): void
    {
        [$organisation, $mission, $worker] = $this->missionFacturee(200.0);

        TimeEntry::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'mission_id' => $mission->id,
            'worked_minutes' => 120,
        ]);

        // Une seconde mission, jamais pointée.
        $autre = Mission::factory()->create([
            'booking_id' => Booking::factory()->create(['devis_estime' => 100.0])->id,
        ]);
        $autre->forceFill([
            'provider_organization_id' => $organisation->id,
            'organization_site_id' => $mission->organization_site_id,
        ])->save();

        $agregat = app(ProfitabilityService::class)->pourLaPeriode(
            $organisation->id,
            Carbon::now()->subDay(),
            Carbon::now()->addDay(),
        );

        $ligne = $agregat->first();

        $this->assertSame(2, $ligne['missions_count']);
        // Les signaler à part permet à l'écran de dire « sur deux missions, une n'a pas d'heures » —
        // sans quoi la marge affichée paraîtrait excellente pour une mauvaise raison.
        $this->assertSame(1, $ligne['missions_without_timesheet']);
    }
}
