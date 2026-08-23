<?php

namespace Tests\Feature\Workforce;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\Workforce\TimesheetService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE PLANNING D'ÉQUIPE (E19) ET LES FEUILLES D'HEURES (E20). */
class PlanningEtPointageTest extends TestCase
{
    use RefreshDatabase;

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

    // ── E19 : le planning ────────────────────────────────────────────────────

    #[Test]
    public function sans_planning_saisi_rien_ne_change(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $verdicts = app(WorkerAvailabilityService::class)->libresPour(
            $organisation->id,
            Carbon::now()->addDay()->setTime(10, 0),
        );

        // C'EST LA GARANTIE QUI COMPTE À LA MISE EN SERVICE.
        $this->assertTrue($verdicts[$worker->id]);
    }

    #[Test]
    public function hors_de_son_shift_le_salarie_n_est_pas_disponible(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $jour = Carbon::now()->addDay()->startOfDay();

        Shift::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_at' => $jour->copy()->addHours(8),
            'ends_at' => $jour->copy()->addHours(17),
        ]);

        $service = app(WorkerAvailabilityService::class);

        // Dans le shift : disponible.
        $this->assertTrue(
            $service->libresPour($organisation->id, $jour->copy()->addHours(10))[$worker->id],
        );

        // Vingt-trois heures : l'auto-assignation lui envoyait une course, faute de savoir qu'il ne
        // travaille pas.
        $this->assertFalse(
            $service->libresPour($organisation->id, $jour->copy()->addHours(23))[$worker->id],
        );
    }

    #[Test]
    public function un_planning_en_preparation_ne_rend_personne_assignable(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();
        $jour = Carbon::now()->addDay()->startOfDay();

        Shift::factory()->planifie()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'starts_at' => $jour->copy()->addHours(8),
            'ends_at' => $jour->copy()->addHours(17),
        ]);

        // Un brouillon de planning ne doit pas engager quelqu'un : on publie quand c'est arrêté.
        // Ici aucun shift PUBLIÉ n'existe, donc on retombe sur le comportement d'avant.
        $this->assertTrue(
            app(WorkerAvailabilityService::class)
                ->libresPour($organisation->id, $jour->copy()->addHours(10))[$worker->id],
        );
    }

    // ── E20 : les heures ─────────────────────────────────────────────────────

    #[Test]
    public function une_saisie_manuelle_attend_une_approbation(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $entry = app(TimesheetService::class)->saisirManuellement(
            $worker,
            $organisation->id,
            Carbon::now()->subHours(4),
            Carbon::now()->subHour(),
            motif: 'GPS coupé au sous-sol.',
        );

        // Une correction non approuvée rendrait le pointage déclaratif.
        $this->assertSame(TimeEntry::STATUS_PENDING_APPROVAL, $entry->status);
        $this->assertSame(180, $entry->worked_minutes);
    }

    #[Test]
    public function on_ne_s_approuve_pas_soi_meme(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $entry = app(TimesheetService::class)->saisirManuellement(
            $worker,
            $organisation->id,
            Carbon::now()->subHours(2),
            Carbon::now(),
        );

        // S'approuver soi-même viderait l'approbation de son sens : la correction redeviendrait
        // purement déclarative.
        $this->expectException(DomainException::class);

        app(TimesheetService::class)->statuer($entry, $worker, true);
    }

    #[Test]
    public function une_journee_impossible_est_refusee(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        // Au-delà de seize heures, c'est une erreur de saisie ou un oubli de clôture : l'accepter
        // ferait passer une journée impossible dans une paie.
        $this->expectException(DomainException::class);

        app(TimesheetService::class)->saisirManuellement(
            $worker,
            $organisation->id,
            Carbon::now()->subHours(20),
            Carbon::now(),
        );
    }

    #[Test]
    public function la_feuille_ne_compte_que_ce_qui_est_retenu(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        TimeEntry::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'started_at' => Carbon::now()->subHours(3),
            'worked_minutes' => 180,
            'status' => TimeEntry::STATUS_RECORDED,
        ]);

        TimeEntry::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'started_at' => Carbon::now()->subHours(2),
            'worked_minutes' => 120,
            'status' => TimeEntry::STATUS_PENDING_APPROVAL,
        ]);

        $feuille = app(TimesheetService::class)->feuilleDeLaPeriode(
            $organisation->id,
            Carbon::now()->subDay(),
            Carbon::now()->addDay(),
        );

        // Payer avant approbation reviendrait à ne jamais approuver.
        $this->assertSame(180, $feuille->first()['worked_minutes']);
        $this->assertSame(3.0, $feuille->first()['worked_hours']);
    }

    #[Test]
    public function l_export_paie_survit_a_un_nom_qui_contient_le_separateur(): void
    {
        [$organisation, $worker] = $this->societeAvecSalarie();

        $worker->forceFill(['name' => 'Dupont; Marie'])->save();

        TimeEntry::factory()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $worker->id,
            'started_at' => Carbon::now()->subHours(3),
            'worked_minutes' => 90,
        ]);

        $csv = app(TimesheetService::class)->exporterCsv(
            $organisation->id,
            Carbon::now()->subDay(),
            Carbon::now()->addDay(),
        );

        // Le point-virgule est le séparateur : le laisser dans un nom couperait la ligne et
        // décalerait toutes les colonnes suivantes.
        $ligne = explode("\n", trim($csv))[1] ?? '';

        $this->assertSame(5, count(explode(';', $ligne)));
        // `str_replace` rend deux espaces là où il y avait « ; » : l'important est que la ligne
        // garde ses cinq colonnes, pas la typographie du nom.
        $this->assertStringContainsString('Dupont', $ligne);
        $this->assertStringNotContainsString('Dupont;', $ligne);
    }
}
