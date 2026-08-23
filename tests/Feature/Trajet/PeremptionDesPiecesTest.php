<?php

namespace Tests\Feature\Trajet;

use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\ProviderDocumentExpiringNotification;
use App\Services\Onboarding\ProviderDocumentExpiryScanner;
use App\Services\Onboarding\ProviderDossierSummary;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** UNE PIÈCE PÉRIMÉE NE VAUT PLUS RIEN — et tout le monde doit le dire de la même façon. */
class PeremptionDesPiecesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prestataireDeCourse(): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $user->id, 'status' => 'active']);

        $trade = Trade::factory()->create();

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

        $user->trades()->attach($trade->id);

        return $user->fresh();
    }

    private function piece(User $user, string $type, ?string $expiresAt): ProviderOnboardingDocument
    {
        return ProviderOnboardingDocument::create([
            'user_id' => $user->id,
            'document_type' => $type,
            'status' => ProviderOnboardingDocument::STATUS_APPROVED,
            'file_path' => "providers/{$user->id}/{$type}.pdf",
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_une_piece_perimee_bloque_le_dossier(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-08-01');

        $dossier = app(ProviderDossierSummary::class)->for($user);

        $this->assertContains('Permis de conduire', $dossier['documents']['expired']);
        $this->assertFalse(
            $dossier['can_mark_verified'],
            'Le dossier se déclarait complet pendant que le dispatch excluait la pièce : deux verdicts opposés.'
        );
        $this->assertStringContainsString('périmé', implode(' ', $dossier['blockers']));
    }

    /** LE TÉMOIN : la même pièce, encore valable, ne bloque rien. */
    public function test_une_piece_valable_ne_bloque_pas(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2030-01-01');

        $dossier = app(ProviderDossierSummary::class)->for($user);

        $this->assertSame([], $dossier['documents']['expired']);
    }

    /** LE JOUR MÊME DE L'ÉCHÉANCE, la pièce est ENCORE valable. */
    public function test_le_jour_de_l_echeance_la_piece_vaut_encore(): void
    {
        Carbon::setTestNow('2026-08-14 23:30:00');

        $user = $this->prestataireDeCourse();
        $piece = $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-08-14');

        $this->assertFalse($piece->fresh()->isExpired());
        $this->assertSame([], app(ProviderDossierSummary::class)->for($user)['documents']['expired']);
    }

    public function test_une_echeance_proche_avertit_sans_bloquer(): void
    {
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-09-01');

        $dossier = app(ProviderDossierSummary::class)->for($user);

        $this->assertContains('Permis de conduire', $dossier['documents']['expiring']);
        $this->assertSame(
            [],
            $dossier['documents']['expired'],
            'Bloquer un mois à l’avance priverait de missions quelqu’un dont la pièce est valable aujourd’hui.'
        );
        $this->assertStringContainsString('bientôt périmé', implode(' ', $dossier['warnings']));
    }

    public function test_le_scanner_previent_avant_l_echeance(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-09-01');

        $compte = app(ProviderDocumentExpiryScanner::class)->scanAndNotify();

        $this->assertSame(1, $compte['notified']);
        Notification::assertSentTo($user, ProviderDocumentExpiringNotification::class);
    }

    /** LE TÉMOIN : une pièce lointaine ne déclenche rien. */
    public function test_une_echeance_lointaine_ne_declenche_rien(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2030-01-01');

        $this->assertSame(0, app(ProviderDocumentExpiryScanner::class)->scanAndNotify()['notified']);
        Notification::assertNothingSent();
    }

    public function test_le_scanner_ne_previent_qu_une_fois_par_echeance(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $piece = $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-09-01');

        $scanner = app(ProviderDocumentExpiryScanner::class);

        $this->assertSame(1, $scanner->scanAndNotify()['notified']);
        $this->assertSame(
            0,
            $scanner->scanAndNotify()['notified'],
            'Un cron quotidien enverrait trente courriels pour un même permis — après quoi plus personne ne les lit.'
        );

        // Renouvelée, elle porte une NOUVELLE échéance : la prochaine alerte doit repartir.
        $piece->fresh()->update(['expires_at' => '2029-09-01']);
        Carbon::setTestNow('2029-08-20');

        $this->assertSame(1, $scanner->scanAndNotify()['notified']);
    }

    public function test_la_commande_de_scan_tourne(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-14');

        $user = $this->prestataireDeCourse();
        $this->piece($user, ProviderOnboardingDocument::TYPE_DRIVING_LICENSE, '2026-09-01');

        $this->artisan('provider:scan-expiring-documents')
            ->expectsOutputToContain('1 prévenu')
            ->assertSuccessful();
    }
}
