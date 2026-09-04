<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CancellationV2\CancellationsCenter;
use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Le centre des annulations absorbe le questionnaire et le pivot des raisons. */
class FusionAnnulationsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // `platform_role` et `permissions` ne s'ecrivent que par la porte du siege.
        PorteDuSiege::ouvrir(fn () => $admin->forceFill([
            'platform_role' => 'admin',
            'permissions' => $capacites,
        ])->save());

        return $admin->refresh();
    }

    public function test_les_deux_url_fusionnees_atterrissent_sur_leur_onglet(): void
    {
        $admin = $this->admin(['manage-finance', 'manage-analytics']);

        foreach ([
            '/admin/analytics/cancellations' => 'tab=raisons',
            '/admin/cancellation-questions' => 'tab=questionnaire',
        ] as $ancienne => $attendu) {
            $reponse = $this->actingAs($admin)->get($ancienne);

            $reponse->assertRedirect();
            $this->assertStringContainsString($attendu, $reponse->headers->get('Location'), $ancienne);
            $this->assertStringContainsString('/admin/cancellations-v2', $reponse->headers->get('Location'), $ancienne);
        }
    }

    public function test_les_cinq_onglets_rendent(): void
    {
        $admin = $this->admin(['manage-finance', 'manage-analytics']);

        $preuves = [
            'recent' => 'Tous actors',
            'overrides' => 'Overrides',
            'policies' => 'Politiques',
            'questionnaire' => 'Questionnaire d’annulation',
            'raisons' => 'Raisons d\'annulation',
        ];

        $this->assertSame(array_keys(CancellationsCenter::ONGLETS), array_keys($preuves));

        foreach ($preuves as $onglet => $preuve) {
            Livewire::actingAs($admin)
                ->test(CancellationsCenter::class, ['tab' => $onglet])
                ->assertOk()
                ->assertSet('tab', $onglet)
                ->assertSee($preuve, escape: false);
        }
    }

    public function test_un_analyste_ne_voit_que_l_onglet_des_raisons(): void
    {
        $composant = Livewire::actingAs($this->admin(['manage-analytics']))
            ->test(CancellationsCenter::class);

        $composant->assertSet('tab', 'raisons')
            ->assertSee('Raisons d\'annulation', escape: false)
            ->assertDontSee('Tous actors');

        // TEMOIN POSITIF : le meme ecran s'ouvre en entier pour la finance.
        Livewire::actingAs($this->admin(['manage-finance']))
            ->test(CancellationsCenter::class)
            ->assertSet('tab', 'recent')
            ->assertSee('Tous actors');
    }

    public function test_un_analyste_ne_peut_pas_renoncer_a_des_frais(): void
    {
        $annulation = BookingCancellationV2::factory()->create(['fee_amount_cents' => 5000]);

        // La methode est joignable sans bouton : c'est la garde du composant qu'on mesure.
        Livewire::actingAs($this->admin(['manage-analytics']))
            ->test(CancellationsCenter::class)
            ->call('override', $annulation->id, 'Un motif suffisamment long pour passer.')
            ->assertForbidden();

        $this->assertSame(5000, (int) $annulation->fresh()->fee_amount_cents);

        // TEMOIN POSITIF : la finance, elle, y arrive.
        Livewire::actingAs($this->admin(['manage-finance']))
            ->test(CancellationsCenter::class)
            ->call('override', $annulation->id, 'Un motif suffisamment long pour passer.');

        $this->assertSame(0, (int) $annulation->fresh()->fee_amount_cents);
    }

    public function test_l_onglet_raisons_dit_qu_i_a_annule_par_son_role(): void
    {
        BookingCancellationV2::factory()->create([
            'cancelled_at' => now(),
            'actor_role' => 'provider',
            'reason_text' => 'Créneau plus disponible',
        ]);

        // La carte lisait `bookings.cancelled_by`, une colonne d'IDENTIFIANTS, et affichait
        // « Annulé par 3 ». `actor_role` porte ce qu'elle voulait dire.
        Livewire::actingAs($this->admin(['manage-analytics']))
            ->test(CancellationsCenter::class, ['tab' => 'raisons'])
            ->assertSee('Prestataire')
            ->assertSee('Créneau plus disponible');
    }

    public function test_l_onglet_raisons_et_l_onglet_recentes_annoncent_les_memes_frais(): void
    {
        // LA COLONNE MIROIR MENT EXPRES : c'est le defaut qui a motive l'unification.
        $booking = Booking::factory()->create([
            'status' => 'annule',
            'cancellation_fee_amount' => 87.75,
            'cancellation_reason' => 'motif porte par la reservation',
        ]);

        BookingCancellationV2::factory()->create([
            'booking_id' => $booking->id,
            'cancelled_at' => now(),
            'actor_role' => 'client',
            'reason_text' => 'motif porte par le moteur',
            'fee_amount_cents' => 0,
        ]);

        Livewire::actingAs($this->admin(['manage-finance', 'manage-analytics']))
            ->test(CancellationsCenter::class, ['tab' => 'raisons'])
            ->assertSee('motif porte par le moteur')
            // La colonne miroir de `bookings` ne parle plus : ni son motif, ni ses 87,75 €.
            ->assertDontSee('motif porte par la reservation')
            ->assertDontSee('87,75');
    }

    public function test_renoncer_aux_frais_les_efface_aussi_sur_la_reservation(): void
    {
        $booking = Booking::factory()->create(['cancellation_fee_amount' => 50.0]);
        $annulation = BookingCancellationV2::factory()->create([
            'booking_id' => $booking->id,
            'fee_amount_cents' => 5000,
        ]);

        app(CancellationEngine::class)->override(
            $annulation,
            $this->admin(['manage-finance']),
            'Geste commercial motive et suffisamment long.',
        );

        // L'ONGLET « RAISONS » LIT LA RESERVATION, pas la table d'annulation : sans cette
        // remise a zero, deux onglets de la meme page annoncaient deux montants.
        $this->assertSame(0.0, (float) DB::table('bookings')->where('id', $booking->id)->value('cancellation_fee_amount'));
    }
}
