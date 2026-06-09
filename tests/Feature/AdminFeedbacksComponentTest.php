<?php

namespace Tests\Feature;

use App\Livewire\AdminFeedbacks;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire tests for the admin feedbacks moderation screen.
 *
 * Mirrors the conventions of AdminPromoCodesCenterTest / FeedbackExportTest:
 *   User::factory()->admin()/->client()/->employe()->create([...])
 *   $this->actingAs($admin)
 *   Livewire::test(AdminFeedbacks::class, [...mount])->...
 *
 * NOTE (source bug, not fixed here): AdminFeedbacks::updatedReponse() calls
 * Gate::authorize('respond', Feedback::class). The FeedbackPolicy::respond()
 * method requires a Feedback *instance*, but only the class string is passed,
 * so Laravel raises an ArgumentCountError before any branch of updatedReponse
 * is reached. That action is therefore not exercisable green and is left
 * uncovered intentionally. exportPdf/exportCsv use the export() policy method
 * (no model param) and work fine.
 */
class AdminFeedbacksComponentTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    /**
     * Create a feedback row attached to a fresh booking, controlling the
     * fields the component filters/searches on.
     */
    private function makeFeedback(
        string $commentaire,
        ?int $serviceZoneId = null,
        ?User $client = null,
        ?User $employe = null,
        string $status = 'termine',
        int $note = 5,
        ?string $reponseAdmin = null,
    ): Feedback {
        $bookingAttributes = [
            'status' => $status,
        ];

        if ($client) {
            $bookingAttributes['client_id'] = $client->id;
        }

        if ($employe) {
            $bookingAttributes['employe_id'] = $employe->id;
        }

        if ($serviceZoneId) {
            $bookingAttributes['service_zone_id'] = $serviceZoneId;
        }

        $booking = Booking::factory()->create($bookingAttributes);

        return Feedback::create([
            'rendez_vous_id' => $booking->id,
            'client_id' => $booking->client_id,
            'note' => $note,
            'commentaire' => $commentaire,
            'reponse_admin' => $reponseAdmin,
        ]);
    }

    public function test_mount_and_render_global_scope_lists_feedbacks(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->makeFeedback('ZZALPHACOMMENT', note: 5);
        $this->makeFeedback('ZZBETACOMMENT', note: 2);

        Livewire::test(AdminFeedbacks::class)
            ->assertOk()
            ->assertSet('scopeId', null)
            ->assertSee('ZZALPHACOMMENT')
            ->assertSee('ZZBETACOMMENT')
            ->assertSee('Global')
            ->assertSee('Aucun filtre actif');
    }

    public function test_empty_state_shown_when_no_feedbacks(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminFeedbacks::class)
            ->assertOk()
            ->assertSee('Aucun feedback trouvé');
    }

    public function test_search_filter_narrows_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->makeFeedback('ZZNEEDLECOMMENT');
        $this->makeFeedback('ZZHAYSTACKCOMMENT');

        Livewire::test(AdminFeedbacks::class)
            ->set('search', 'ZZNEEDLE')
            ->assertOk()
            ->assertSee('ZZNEEDLECOMMENT')
            ->assertDontSee('ZZHAYSTACKCOMMENT')
            ->assertSee('1 filtre(s) actif(s)');
    }

    public function test_client_filter_narrows_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();

        $this->makeFeedback('ZZCLIENTACOMMENT', client: $clientA);
        $this->makeFeedback('ZZCLIENTBCOMMENT', client: $clientB);

        Livewire::test(AdminFeedbacks::class)
            ->set('client_id', (string) $clientA->id)
            ->assertOk()
            ->assertSee('ZZCLIENTACOMMENT')
            ->assertDontSee('ZZCLIENTBCOMMENT');
    }

    public function test_employe_filter_narrows_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $employeA = User::factory()->employe()->create();
        $employeB = User::factory()->employe()->create();

        $this->makeFeedback('ZZEMPLOYEACOMMENT', employe: $employeA);
        $this->makeFeedback('ZZEMPLOYEBCOMMENT', employe: $employeB);

        Livewire::test(AdminFeedbacks::class)
            ->set('employe_id', (string) $employeA->id)
            ->assertOk()
            ->assertSee('ZZEMPLOYEACOMMENT')
            ->assertDontSee('ZZEMPLOYEBCOMMENT');
    }

    public function test_filter_by_status_action_sets_status_and_filters(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->makeFeedback('ZZTERMINECOMMENT', status: 'termine');
        $this->makeFeedback('ZZREFUSECOMMENT', status: 'refuse');

        Livewire::test(AdminFeedbacks::class)
            ->call('filterByStatus', 'refuse')
            ->assertSet('status', 'refuse')
            ->assertOk()
            ->assertSee('ZZREFUSECOMMENT')
            ->assertDontSee('ZZTERMINECOMMENT');
    }

    public function test_reset_filters_clears_all_filters(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->makeFeedback('ZZRESETCOMMENT');

        Livewire::test(AdminFeedbacks::class)
            ->set('search', 'ZZNOMATCH')
            ->set('client_id', '999')
            ->set('employe_id', '888')
            ->set('status', 'refuse')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('client_id', '')
            ->assertSet('employe_id', '')
            ->assertSet('status', '')
            ->assertOk()
            ->assertSee('Aucun filtre actif')
            ->assertSee('ZZRESETCOMMENT');
    }

    public function test_updated_hooks_reset_page_and_keep_component_ok(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->makeFeedback('ZZHOOKSCOMMENT');

        Livewire::test(AdminFeedbacks::class)
            ->set('employe_id', '1')
            ->set('client_id', '2')
            ->set('status', 'termine')
            ->set('search', 'foo')
            ->set('perPage', 4)
            ->assertOk();
    }

    public function test_explicit_scope_id_restricts_to_zone(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $inZone = $this->makeFeedback('ZZINZONECOMMENT');
        $zoneId = $inZone->rendezVous->service_zone_id;

        // Different booking → different auto-created zone.
        $this->makeFeedback('ZZOUTZONECOMMENT');

        Livewire::test(AdminFeedbacks::class, ['scopeId' => $zoneId])
            ->assertOk()
            ->assertSet('scopeId', $zoneId)
            ->assertSee('Zone filtrée')
            ->assertSee('ZZINZONECOMMENT')
            ->assertDontSee('ZZOUTZONECOMMENT');
    }

    public function test_zone_scoped_admin_uses_managed_zone_when_no_scope_param(): void
    {
        $inZone = $this->makeFeedback('ZZMANAGEDZONECOMMENT');
        $zoneId = $inZone->rendezVous->service_zone_id;

        $this->makeFeedback('ZZUNMANAGEDZONECOMMENT');

        $zoneAdmin = User::factory()->admin()->create([
            'permissions' => ['perform-critical-admin-actions'],
            'access_scope' => 'zone',
            'managed_service_zone_id' => $zoneId,
            'is_active' => true,
        ]);
        $this->actingAs($zoneAdmin);

        Livewire::test(AdminFeedbacks::class)
            ->assertOk()
            ->assertSee('Zone filtrée')
            ->assertSee('ZZMANAGEDZONECOMMENT')
            ->assertDontSee('ZZUNMANAGEDZONECOMMENT');
    }

    public function test_quality_stats_reflect_seeded_feedbacks(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // 1 high answered + 1 low unanswered => avg 3.0, satisfaction 60%.
        $this->makeFeedback('ZZSTATHIGH', note: 4, reponseAdmin: 'Merci pour votre retour.');
        $this->makeFeedback('ZZSTATLOW', note: 2);

        Livewire::test(AdminFeedbacks::class)
            ->assertOk()
            ->assertSee('3/5')   // average_note_label
            ->assertSee('60%');  // satisfaction_rate
    }

    public function test_export_pdf_redirects_to_export_route(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminFeedbacks::class)
            ->set('status', 'termine')
            ->call('exportPdf')
            ->assertRedirect(route('admin.feedbacks.export', [
                'employe_id' => '',
                'client_id' => '',
                'status' => 'termine',
            ]));
    }

    public function test_export_csv_redirects_to_csv_route(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminFeedbacks::class)
            ->call('exportCsv')
            ->assertRedirect(route('admin.feedbacks.export.csv', [
                'employe_id' => '',
                'client_id' => '',
                'status' => '',
            ]));
    }
}
