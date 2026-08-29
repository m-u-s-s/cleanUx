<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerCode;
use App\Models\PeerInspection;
use App\Models\PeerRental;
use App\Models\PeerVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use App\Services\PeerRental\PeerPaymentService;
use App\Services\PeerRental\PeerRentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RuntimeException;
use Stripe\PaymentIntent;
use Tests\TestCase;

/**
 * LA REGLE QUI TIENT TOUT LE MODULE.
 *
 * Les fonds sont bloques a la reservation et ne sont captures qu'a la remise des cles, quand
 * LES DEUX parties ont confirme. Une seule signature ne prend rien : c'est la promesse faite
 * au proprietaire comme au locataire, et elle se mesure ici.
 */
class LArgentNeBougeQuALaRemiseTest extends TestCase
{
    use RefreshDatabase;

    private function conducteurEnRegle(): User
    {
        $utilisateur = User::factory()->client()->create();

        foreach ([
            ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
            ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
        ] as $type) {
            ProviderOnboardingDocument::create([
                'user_id' => $utilisateur->id,
                'document_type' => $type,
                'status' => ProviderOnboardingDocument::STATUS_APPROVED,
                'file_path' => 'documents/'.$type.'.pdf',
                'reviewed_at' => now(),
            ]);
        }

        return $utilisateur->fresh();
    }

    private function vehiculePublie(?User $proprietaire = null): PeerVehicle
    {
        return PeerVehicle::factory()->publiee()->create([
            'owner_id' => ($proprietaire ?? User::factory()->client()->create())->id,
            'daily_price_cents' => 5000,
            'deposit_cents' => 50000,
            'min_driver_age' => 18,
            'min_license_years' => 0,
        ]);
    }

    /** L'etat des lieux exige ses six angles : sans eux, aucun dommage ne serait opposable. */
    private function etatDesLieux(PeerRental $location, string $phase): PeerInspection
    {
        $inspection = PeerInspection::factory()->create([
            'peer_rental_id' => $location->id,
            'phase' => $phase,
        ]);

        foreach (PeerInspection::ANGLES_REQUIS as $angle) {
            $inspection->photos()->create([
                'path' => 'peer-inspections/'.$angle.'.jpg',
                'angle' => $angle,
                'taken_at' => now(),
            ]);
        }

        return $inspection->fresh();
    }

    /** Stripe ne repond pas en test : on mesure QUI est appele, et quand. */
    private function paiementEspion(): MockInterface
    {
        return $this->mock(PeerPaymentService::class, function (MockInterface $espion): void {
            $espion->shouldReceive('autoriserLeLoyer')->andReturnUsing(function (PeerRental $location) {
                $location->forceFill([
                    'payment_status' => PeerRental::PAIEMENT_AUTORISE,
                    'stripe_payment_intent_id' => 'pi_test',
                    'payment_authorized_at' => now(),
                    'payment_authorized_until' => now()->addDays(7),
                ])->save();

                // Un objet Stripe se construit sans appel reseau : il porte le type attendu
                // sans qu'aucune requete ne parte.
                return new PaymentIntent('pi_test');
            });
        });
    }

    public function test_une_demande_bloque_les_fonds_sans_rien_encaisser(): void
    {
        $this->paiementEspion()->shouldNotReceive('capturerLeLoyer');

        $locataire = $this->conducteurEnRegle();
        $vehicule = $this->vehiculePublie();

        $location = app(PeerRentalService::class)->demander(
            $vehicule,
            $locataire,
            now()->addDays(5)->setTime(10, 0),
            now()->addDays(8)->setTime(10, 0),
            'pm_test',
        );

        $this->assertSame(PeerRental::STATUT_EN_ATTENTE, $location->status);
        $this->assertSame(PeerRental::PAIEMENT_AUTORISE, $location->payment_status);
        $this->assertNull($location->payment_captured_at);
    }

    public function test_la_commission_est_prelevee_sur_le_total_et_le_reste_va_au_proprietaire(): void
    {
        $this->paiementEspion();

        $location = app(PeerRentalService::class)->demander(
            $this->vehiculePublie(),
            $this->conducteurEnRegle(),
            now()->next('Monday')->setTime(10, 0),
            now()->next('Monday')->addDays(2)->setTime(10, 0),
            'pm_test',
        );

        $this->assertSame(
            $location->total_cents,
            $location->platform_fee_cents + $location->owner_payout_cents,
            'La commission et le versement doivent redonner exactement le total.'
        );
        $this->assertGreaterThan(0, $location->platform_fee_cents);
    }

    public function test_une_seule_confirmation_ne_capture_rien(): void
    {
        $espion = $this->paiementEspion();
        $espion->shouldNotReceive('capturerLeLoyer');
        $espion->shouldNotReceive('autoriserLaCaution');

        $service = app(PeerRentalService::class);
        $location = $this->locationConfirmee($service);
        $this->etatDesLieux($location, PeerInspection::PHASE_DEPART);

        $service->confirmerLaRemise($location->fresh(), $location->renter);

        $location->refresh();

        $this->assertSame(PeerRental::STATUT_CONFIRMEE, $location->status);
        $this->assertNotNull($location->handover_renter_confirmed_at);
        $this->assertNull($location->handover_owner_confirmed_at);
        $this->assertNull($location->handed_over_at);
    }

    public function test_les_deux_confirmations_capturent_et_bloquent_la_caution(): void
    {
        $espion = $this->paiementEspion();
        $espion->shouldReceive('capturerLeLoyer')->once()->andReturnUsing(function (PeerRental $location) {
            $location->forceFill([
                'payment_status' => PeerRental::PAIEMENT_CAPTURE,
                'payment_captured_at' => now(),
            ])->save();

            return new PaymentIntent('pi_test');
        });
        $espion->shouldReceive('autoriserLaCaution')->once()->andReturnNull();

        $service = app(PeerRentalService::class);
        $location = $this->locationConfirmee($service);
        $this->etatDesLieux($location, PeerInspection::PHASE_DEPART);

        $code = $service->genererLeCode($location, PeerCode::PHASE_REMISE);

        $service->confirmerLaRemise($location->fresh(), $location->renter);
        $service->confirmerLaRemise($location->fresh(), $location->owner, $code);

        $location->refresh();

        $this->assertSame(PeerRental::STATUT_EN_COURS, $location->status);
        $this->assertSame(PeerRental::PAIEMENT_CAPTURE, $location->payment_status);
        $this->assertNotNull($location->handed_over_at);
    }

    public function test_un_code_faux_ne_confirme_rien(): void
    {
        $espion = $this->paiementEspion();
        $espion->shouldNotReceive('capturerLeLoyer');

        $service = app(PeerRentalService::class);
        $location = $this->locationConfirmee($service);
        $this->etatDesLieux($location, PeerInspection::PHASE_DEPART);
        $service->genererLeCode($location, PeerCode::PHASE_REMISE);

        try {
            $service->confirmerLaRemise($location->fresh(), $location->owner, '000000');
            $this->fail('Un code faux aurait dû être refusé.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Code', $e->getMessage());
        }

        $this->assertNull($location->fresh()->handover_owner_confirmed_at);
    }

    /** TEMOIN — le meme chemin avec le BON code confirme bien. Sans lui, le refus ci-dessus pourrait mesurer une panne. */
    public function test_temoin_le_bon_code_confirme(): void
    {
        $this->paiementEspion()->shouldReceive('capturerLeLoyer', 'autoriserLaCaution')->andReturnNull();

        $service = app(PeerRentalService::class);
        $location = $this->locationConfirmee($service);
        $this->etatDesLieux($location, PeerInspection::PHASE_DEPART);
        $code = $service->genererLeCode($location, PeerCode::PHASE_REMISE);

        $service->confirmerLaRemise($location->fresh(), $location->owner, $code);

        $this->assertNotNull($location->fresh()->handover_owner_confirmed_at);
    }

    public function test_sans_etat_des_lieux_la_remise_est_refusee(): void
    {
        $espion = $this->paiementEspion();
        $espion->shouldNotReceive('capturerLeLoyer');

        $service = app(PeerRentalService::class);
        $location = $this->locationConfirmee($service);

        $this->expectException(RuntimeException::class);

        $service->confirmerLaRemise($location->fresh(), $location->renter);
    }

    public function test_un_conducteur_sans_permis_valide_ne_reserve_pas(): void
    {
        $this->paiementEspion();

        $sansPermis = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        app(PeerRentalService::class)->demander(
            $this->vehiculePublie(),
            $sansPermis,
            now()->addDays(5)->setTime(10, 0),
            now()->addDays(7)->setTime(10, 0),
            'pm_test',
        );
    }

    public function test_on_ne_loue_pas_son_propre_vehicule(): void
    {
        $this->paiementEspion();

        $proprietaire = $this->conducteurEnRegle();
        $vehicule = $this->vehiculePublie($proprietaire);

        $this->expectException(ValidationException::class);

        app(PeerRentalService::class)->demander(
            $vehicule,
            $proprietaire,
            now()->addDays(5)->setTime(10, 0),
            now()->addDays(7)->setTime(10, 0),
            'pm_test',
        );
    }

    public function test_deux_locataires_ne_prennent_pas_les_memes_dates(): void
    {
        $this->paiementEspion();

        $service = app(PeerRentalService::class);
        $vehicule = $this->vehiculePublie();
        $debut = now()->addDays(5)->setTime(10, 0);
        $fin = now()->addDays(8)->setTime(10, 0);

        $service->demander($vehicule, $this->conducteurEnRegle(), $debut, $fin, 'pm_test');

        $this->expectException(ValidationException::class);

        $service->demander($vehicule, $this->conducteurEnRegle(), $debut, $fin, 'pm_test');
    }

    private function locationConfirmee(PeerRentalService $service): PeerRental
    {
        $location = $service->demander(
            $this->vehiculePublie(),
            $this->conducteurEnRegle(),
            now()->addDays(5)->setTime(10, 0),
            now()->addDays(8)->setTime(10, 0),
            'pm_test',
        );

        return $service->accepter($location, $location->owner);
    }
}
