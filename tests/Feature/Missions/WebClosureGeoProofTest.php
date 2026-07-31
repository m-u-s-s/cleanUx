<?php

namespace Tests\Feature\Missions;

use App\Livewire\Employe\MissionActions;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\User;
use App\Services\Geo\OnSiteVerifier;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clôturer depuis le web exige aussi d'être sur place.
 *
 * Le scan mobile exigeait la position ; le web, non. Un prestataire qui connaissait le tableau de
 * bord pouvait donc encaisser depuis chez lui avec un code de fin photographié ou dicté — la
 * protection mobile n'était qu'un détour à contourner.
 *
 * Ces deux surfaces sont sous `role:employe` : ce sont celles du PRESTATAIRE, pas des outils
 * d'administration. Y exiger la position ne bloque donc aucune correction faite depuis un bureau.
 */
class WebClosureGeoProofTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_LAT = 50.8467;

    private const SITE_LNG = 4.3525;

    /** ~11 km au nord : le prestataire est reparti. */
    private const FAR_LAT = 50.9467;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // ─── Tableau de bord Livewire ────────────────────────────────────────────────────────────

    public function test_the_dashboard_refuses_a_closure_without_position(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->actingAs($provider);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode');

        $component->set('endCode', $component->get('generatedEndCode'))
            ->call('finishMission');

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
        $this->assertStringContainsString('Position', (string) $component->get('errorMessage'));
    }

    /**
     * Le refus doit DIRE pourquoi.
     *
     * « Clôture impossible » enverrait le prestataire redemander au client un code qui n'a aucun
     * problème, pendant que la vraie cause — il est à onze kilomètres — resterait invisible. Le
     * message du serveur doit traverser le composant intact.
     */
    public function test_the_dashboard_explains_why_it_refused(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->actingAs($provider);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode');

        $component->set('endCode', $component->get('generatedEndCode'))
            ->set('lat', self::FAR_LAT)
            ->set('lng', self::SITE_LNG)
            ->call('finishMission');

        $message = (string) $component->get('errorMessage');
        $this->assertStringNotContainsString('given data was invalid', $message);
        $this->assertStringContainsString('km', $message);
    }

    public function test_the_dashboard_closes_the_mission_on_site(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->actingAs($provider);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode');

        $component->set('endCode', $component->get('generatedEndCode'))
            ->set('lat', self::SITE_LAT)
            ->set('lng', self::SITE_LNG)
            ->call('finishMission');

        $mission->refresh();
        $this->assertSame(MissionStatus::COMPLETED, $mission->status);
        $this->assertSame(OnSiteVerifier::PASSED, $mission->end_geo_verdict);
        $this->assertNull($component->get('errorMessage'));
    }

    /**
     * Une mission sans coordonnées ne réclame rien : il n'y a rien à comparer, et un navigateur
     * sans géolocalisation — HTTP simple, permission coupée — laisserait sinon le prestataire
     * devant un bouton qui ne marche jamais.
     */
    public function test_a_mission_without_coordinates_still_closes_from_the_dashboard(): void
    {
        [$provider, $mission] = $this->scenario();
        $mission->forceFill(['destination_lat' => null, 'destination_lng' => null])->save();
        $this->actingAs($provider);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode');

        $component->set('endCode', $component->get('generatedEndCode'))
            ->call('finishMission');

        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
        $this->assertSame(OnSiteVerifier::SKIPPED_NO_DESTINATION, $mission->fresh()->end_geo_verdict);
    }

    // ─── Action terrain ──────────────────────────────────────────────────────────────────────

    public function test_the_field_action_refuses_a_closure_without_position(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->endCodeFor($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", ['code' => '654321'])
            ->assertStatus(422)
            ->assertJsonPath('errors.position.0', fn ($m) => str_contains((string) $m, 'Position'));

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    public function test_the_field_action_refuses_a_closure_from_far_away(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->endCodeFor($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::FAR_LAT,
                'lng' => self::SITE_LNG,
            ])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    public function test_the_field_action_closes_the_mission_on_site(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->endCodeFor($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'accuracy_m' => 14,
            ])
            ->assertOk();

        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }

    /** Le code du client ne doit pas être brûlé par un problème de position. */
    public function test_a_position_refusal_leaves_the_web_end_code_usable(): void
    {
        [$provider, $mission] = $this->scenario();
        $this->endCodeFor($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::FAR_LAT,
                'lng' => self::SITE_LNG,
            ])
            ->assertStatus(422);

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
            ])
            ->assertOk();
    }

    private function endCodeFor(Mission $mission, string $code): void
    {
        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make($code),
            'is_consumed' => false,
        ]);
    }

    /**
     * @return array{0: User, 1: Mission}
     */
    private function scenario(): array
    {
        $provider = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $mission = Mission::factory()->create([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $provider->id,
            'lead_provider_user_id' => $provider->id,
            'planned_start_at' => now()->subHours(2),
            'actual_start_at' => now()->subHour(),
            'destination_lat' => self::SITE_LAT,
            'destination_lng' => self::SITE_LNG,
        ]);

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$provider, $mission];
    }
}
