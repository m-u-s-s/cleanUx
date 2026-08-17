<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionMedia;
use App\Models\MissionVerificationCode;
use App\Models\User;
use App\Services\Missions\MissionQualityService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les photos du terrain ne sont gardées que si la transition a eu lieu.
 *
 * Elles étaient enregistrées AVANT la validation du code : chaque tentative refusée laissait ses
 * fichiers et ses lignes en base sur une mission qui n'avait pas bougé. Trois essais donnaient
 * trois jeux de photos identiques — et le prestataire qui réessaie est précisément celui dont on
 * vient de refuser la tentative, si bien que l'accumulation suivait l'échec.
 *
 * L'exigence de position sur la clôture web a rendu ce défaut nettement plus fréquent : un
 * navigateur sans géolocalisation refuse à chaque coup.
 */
class FieldPhotosOnlyOnSuccessTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_LAT = 50.8467;

    private const SITE_LNG = 4.3525;

    /** ~11 km au nord. */
    private const FAR_LAT = 50.9467;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('private');
    }

    /** LA garantie, côté clôture. */
    public function test_a_refused_closure_stores_no_photo(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::STARTED);
        $this->endCode($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::FAR_LAT,
                'lng' => self::SITE_LNG,
                'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $mission->media()->count());
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_a_wrong_code_stores_no_photo_either(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::STARTED);
        $this->endCode($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '000000',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $mission->media()->count());
    }

    /**
     * Le symptôme réel du défaut : l'accumulation. Le prestataire qui réessaie est celui dont on
     * vient de refuser la tentative, si bien que chaque échec ajoutait un jeu de doublons.
     */
    public function test_retrying_after_refusals_leaves_a_single_set_of_photos(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::STARTED);
        $this->endCode($mission, '654321');

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($provider)
                ->postJson("/missions/{$mission->id}/finish", [
                    'code' => '654321',
                    'lat' => self::FAR_LAT,
                    'lng' => self::SITE_LNG,
                    'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
                ])
                ->assertStatus(422);
        }

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
            ])
            ->assertOk()
            ->assertJsonPath('photos_stored', 1);

        $this->assertSame(1, $mission->media()->where('media_type', MissionMedia::TYPE_AFTER_PHOTO)->count());
    }

    public function test_a_successful_closure_still_keeps_its_photos(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::STARTED);
        $this->endCode($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'photos_apres' => [
                    UploadedFile::fake()->create('apres-1.jpg', 100, 'image/jpeg'),
                    UploadedFile::fake()->create('apres-2.jpg', 100, 'image/jpeg'),
                ],
            ])
            ->assertOk()
            ->assertJsonPath('photos_stored', 2);

        $this->assertSame(2, $mission->media()->where('media_type', MissionMedia::TYPE_AFTER_PHOTO)->count());
        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }

    /**
     * La garantie qui compte vraiment : la photo est VUE.
     *
     * Ce contrôleur écrivait `media_type` = `after` quand toute l'application lit `after_photo`.
     * Les photos prises sur place étaient donc invisibles pour le client, absentes du rapport PDF
     * et comptées à zéro par le score qualité. Chaque moitié était cohérente avec elle-même, ce qui
     * est exactement pourquoi l'écart n'a jamais rien déclenché.
     *
     * Assertion portée sur un LECTEUR réel plutôt que sur la chaîne écrite : vérifier la valeur
     * stockée n'aurait fait que graver la convention du moment, sans dire si quelqu'un la lit.
     */
    public function test_a_stored_photo_is_actually_seen_by_the_mission_report(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::STARTED);
        $this->endCode($mission, '654321');

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/finish", [
                'code' => '654321',
                'lat' => self::SITE_LAT,
                'lng' => self::SITE_LNG,
                'photos_apres' => [UploadedFile::fake()->create('apres.jpg', 100, 'image/jpeg')],
            ])
            ->assertOk();

        $report = app(MissionQualityService::class)->generateOrRefreshReport($mission->fresh());

        $this->assertSame(1, (int) $report->after_photos_count);
    }

    /**
     * Les photos déjà écrites sous l'ancienne orthographe redeviennent visibles.
     *
     * Corriger l'écrivain ne suffisait pas : les lignes posées avant seraient restées invisibles
     * pour toujours, sur des missions déjà clôturées et facturées. La migration est rejouée ici
     * explicitement — sans quoi rien ne prouverait qu'elle fait ce qu'elle annonce, `RefreshDatabase`
     * l'ayant exécutée bien avant que ces lignes existent.
     */
    public function test_the_migration_makes_legacy_photos_visible_again(): void
    {
        [, $mission] = $this->scenario(MissionStatus::STARTED);

        $mission->media()->create([
            'uploaded_by_user_id' => null,
            'media_type' => 'after',
            'path' => 'missions/photos-apres/ancienne.jpg',
            'caption' => 'Photo après mission',
            'taken_at' => now(),
        ]);

        $this->assertSame(
            0,
            (int) app(MissionQualityService::class)->generateOrRefreshReport($mission->fresh())->after_photos_count,
            'Sans la migration, cette photo doit être invisible — sinon le test ne prouve rien.'
        );

        (require database_path('migrations/2026_08_01_000001_normalise_mission_media_types.php'))->up();

        $this->assertSame(
            1,
            (int) app(MissionQualityService::class)->generateOrRefreshReport($mission->fresh())->after_photos_count,
        );
    }

    /** Le même défaut vivait au démarrage : un code refusé y laissait aussi ses photos. */
    public function test_a_refused_start_stores_no_photo(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::ARRIVED);
        MissionVerificationCode::factory()->startCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('111222'),
            'is_consumed' => false,
        ]);

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/start", [
                'code' => '000000',
                'photos_avant' => [UploadedFile::fake()->create('avant.jpg', 100, 'image/jpeg')],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $mission->media()->count());
        $this->assertCount(0, Storage::disk('private')->allFiles());
    }

    public function test_a_successful_start_still_keeps_its_photos(): void
    {
        [$provider, $mission] = $this->scenario(MissionStatus::ARRIVED);
        MissionVerificationCode::factory()->startCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('111222'),
            'is_consumed' => false,
        ]);

        $this->actingAs($provider)
            ->postJson("/missions/{$mission->id}/start", [
                'code' => '111222',
                'photos_avant' => [UploadedFile::fake()->create('avant.jpg', 100, 'image/jpeg')],
            ])
            ->assertOk()
            ->assertJsonPath('photos_stored', 1);

        $this->assertSame(1, $mission->media()->where('media_type', MissionMedia::TYPE_BEFORE_PHOTO)->count());
    }

    private function endCode(Mission $mission, string $code): void
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
    private function scenario(string $status): array
    {
        $provider = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $mission = Mission::factory()->create([
            'status' => $status,
            'lead_employee_id' => $provider->id,
            'lead_provider_user_id' => $provider->id,
            'planned_start_at' => now()->subHours(2),
            'actual_start_at' => $status === MissionStatus::STARTED ? now()->subHour() : null,
            'destination_lat' => self::SITE_LAT,
            'destination_lng' => self::SITE_LNG,
        ]);

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$provider, $mission];
    }
}
