<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerClaim;
use App\Models\PeerCode;
use App\Models\PeerInspection;
use App\Models\PeerRental;
use App\Models\PeerReview;
use App\Models\PeerVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use App\Services\PeerRental\PeerClaimService;
use App\Services\PeerRental\PeerPaymentService;
use App\Services\PeerRental\PeerRentalService;
use App\Services\PeerRental\PeerReturnCharges;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Stripe\PaymentIntent;
use Tests\TestCase;

/**
 * LE RETOUR, LA CAUTION, ET CE QU'ON RETIENT DESSUS.
 *
 * Sans retenue, la caution retombe. Avec, elle reste bloquee jusqu'a ce que plus rien
 * n'attende — et elle se solde alors EN UNE FOIS, parce qu'une empreinte ne se capture pas
 * deux fois.
 */
class LeRetourEtLaCautionTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $paiement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paiement = $this->mock(PeerPaymentService::class, function (MockInterface $espion): void {
            $espion->shouldReceive('autoriserLeLoyer')->andReturnUsing(function (PeerRental $location) {
                $location->forceFill([
                    'payment_status' => PeerRental::PAIEMENT_AUTORISE,
                    'stripe_payment_intent_id' => 'pi_test',
                    'payment_authorized_at' => now(),
                    'payment_authorized_until' => now()->addDays(7),
                ])->save();

                return new PaymentIntent('pi_test');
            });

            $espion->shouldReceive('capturerLeLoyer')->andReturnUsing(function (PeerRental $location) {
                $location->forceFill([
                    'payment_status' => PeerRental::PAIEMENT_CAPTURE,
                    'payment_captured_at' => now(),
                ])->save();

                return new PaymentIntent('pi_test');
            });

            $espion->shouldReceive('autoriserLaCaution')->andReturnUsing(function (PeerRental $location) {
                $location->forceFill([
                    'deposit_payment_intent_id' => 'pi_caution',
                    'deposit_authorized_at' => now(),
                ])->save();

                return new PaymentIntent('pi_caution');
            });

            // PAR DEFAUT, pour que chaque test n'ait a declarer que ce qu'il MESURE.
            // `byDefault` laisse un test poser sa propre attente sans conflit.
            $espion->shouldReceive('libererLaCaution')->andReturnNull()->byDefault();
            $espion->shouldReceive('retenirSurLaCaution')->andReturnNull()->byDefault();
        });
    }

    private function locationEnCours(): PeerRental
    {
        $service = app(PeerRentalService::class);

        $vehicule = PeerVehicle::factory()->publiee()->create([
            'daily_price_cents' => 5000,
            'deposit_cents' => 50000,
            'included_km_per_day' => 100,
            'extra_km_price_cents' => 25,
            'min_driver_age' => 18,
            'min_license_years' => 0,
        ]);

        $locataire = $this->conducteurEnRegle();

        $location = $service->demander(
            $vehicule,
            $locataire,
            now()->addDays(2)->setTime(10, 0),
            now()->addDays(4)->setTime(10, 0),
            'pm_test',
        );

        $location = $service->accepter($location, $location->owner);

        $this->etatDesLieux($location, PeerInspection::PHASE_DEPART, ['mileage_km' => 50000, 'fuel_eighths' => 8]);

        $code = $service->genererLeCode($location, PeerCode::PHASE_REMISE);
        $service->confirmerLaRemise($location->fresh(), $location->renter);
        $service->confirmerLaRemise($location->fresh(), $location->owner, $code);

        return $location->refresh();
    }

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

    /** @param  array<string, mixed>  $attributs */
    private function etatDesLieux(PeerRental $location, string $phase, array $attributs = []): PeerInspection
    {
        $inspection = PeerInspection::factory()->create(array_merge([
            'peer_rental_id' => $location->id,
            'phase' => $phase,
        ], $attributs));

        foreach (PeerInspection::ANGLES_REQUIS as $angle) {
            $inspection->photos()->create([
                'path' => 'peer-inspections/'.$angle.'.jpg',
                'angle' => $angle,
                'taken_at' => now(),
            ]);
        }

        return $inspection->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributs
     * @param  callable(PeerRental): void|null  $avantLaSecondeSignature  ce que le proprietaire
     *                                                                    declare en rendant les cles — c'est le SEUL moment ou la caution est encore la.
     */
    private function rendre(PeerRental $location, array $attributs = [], ?callable $avantLaSecondeSignature = null): PeerRental
    {
        $service = app(PeerRentalService::class);

        $this->etatDesLieux($location, PeerInspection::PHASE_RETOUR, array_merge([
            'mileage_km' => 50100,
            'fuel_eighths' => 8,
        ], $attributs));

        $code = $service->genererLeCode($location, PeerCode::PHASE_RETOUR);
        $service->confirmerLeRetour($location->fresh(), $location->renter);

        if ($avantLaSecondeSignature !== null) {
            $avantLaSecondeSignature($location->fresh());
        }

        return $service->confirmerLeRetour($location->fresh(), $location->owner, $code);
    }

    public function test_un_retour_conforme_libere_la_caution_et_termine(): void
    {
        $this->paiement->shouldReceive('libererLaCaution')->once()->andReturnNull();
        $this->paiement->shouldNotReceive('retenirSurLaCaution');

        $location = $this->rendre($this->locationEnCours());

        $this->assertSame(PeerRental::STATUT_TERMINEE, $location->status);
        $this->assertNotNull($location->returned_at);
    }

    public function test_une_seule_confirmation_de_retour_ne_termine_rien(): void
    {
        $this->paiement->shouldNotReceive('libererLaCaution');

        $location = $this->locationEnCours();
        $this->etatDesLieux($location, PeerInspection::PHASE_RETOUR, ['mileage_km' => 50100, 'fuel_eighths' => 8]);

        app(PeerRentalService::class)->confirmerLeRetour($location->fresh(), $location->renter);

        $this->assertSame(PeerRental::STATUT_EN_COURS, $location->fresh()->status);
    }

    public function test_une_retenue_ouverte_garde_la_caution_bloquee(): void
    {
        $this->paiement->shouldNotReceive('libererLaCaution');
        $this->paiement->shouldNotReceive('retenirSurLaCaution');

        // LA RETENUE EST DEPOSEE ENTRE LES DEUX SIGNATURES : c'est exactement le moment ou
        // le proprietaire voit le vehicule, et le dernier ou la caution est encore la.
        $location = $this->rendre($this->locationEnCours(), [], function (PeerRental $l): void {
            app(PeerClaimService::class)->ouvrir(
                $l,
                $l->owner,
                PeerClaim::MOTIF_DOMMAGE,
                15000,
                'Rayure sur l’aile avant',
            );
        });

        $this->assertSame(PeerRental::STATUT_LITIGE, $location->fresh()->status);
    }

    public function test_une_retenue_acceptee_se_capture_et_le_solde_retombe(): void
    {
        $this->paiement->shouldReceive('retenirSurLaCaution')
            ->once()
            ->withArgs(fn (PeerRental $l, int $montant): bool => $montant === 15000)
            ->andReturnNull();

        $retenue = null;

        $location = $this->rendre($this->locationEnCours(), [], function (PeerRental $l) use (&$retenue): void {
            $retenue = app(PeerClaimService::class)->ouvrir(
                $l,
                $l->owner,
                PeerClaim::MOTIF_DOMMAGE,
                15000,
                'Rayure',
            );
        });

        app(PeerClaimService::class)->accepter($retenue, $location->renter);

        $this->assertSame(PeerRental::STATUT_TERMINEE, $location->fresh()->status);
        $this->assertSame(15000, $location->fresh()->extra_charges_cents);
    }

    public function test_deux_retenues_se_soldent_en_une_seule_capture(): void
    {
        // UNE EMPREINTE NE SE CAPTURE PAS DEUX FOIS : c'est la somme qui part, en un appel.
        $this->paiement->shouldReceive('retenirSurLaCaution')
            ->once()
            ->withArgs(fn (PeerRental $l, int $montant): bool => $montant === 22000)
            ->andReturnNull();

        $claims = app(PeerClaimService::class);
        $a = null;
        $b = null;

        $location = $this->rendre($this->locationEnCours(), [], function (PeerRental $l) use ($claims, &$a, &$b): void {
            $a = $claims->ouvrir($l, $l->owner, PeerClaim::MOTIF_DOMMAGE, 15000);
            $b = $claims->ouvrir($l->fresh(), $l->owner, PeerClaim::MOTIF_NETTOYAGE, 7000);
        });

        $claims->accepter($a, $location->renter);
        $claims->accepter($b, $location->renter);

        $this->assertSame(22000, $location->fresh()->extra_charges_cents);
    }

    public function test_une_retenue_ne_depasse_jamais_la_caution(): void
    {
        $location = $this->locationEnCours();
        $this->etatDesLieux($location, PeerInspection::PHASE_RETOUR, ['mileage_km' => 50100, 'fuel_eighths' => 8]);

        $this->expectException(RuntimeException::class);

        app(PeerClaimService::class)->ouvrir(
            $location->fresh(),
            $location->owner,
            PeerClaim::MOTIF_DOMMAGE,
            60000,
        );
    }

    public function test_les_kilometres_supplementaires_se_mesurent_sur_les_deux_etats(): void
    {
        $location = $this->locationEnCours();
        $this->etatDesLieux($location, PeerInspection::PHASE_RETOUR, ['mileage_km' => 50500, 'fuel_eighths' => 6]);

        $calcul = app(PeerReturnCharges::class)->calculer($location->fresh());

        $lignes = collect($calcul['lignes'])->keyBy('cle');

        // 500 km parcourus, 200 inclus (100/jour x 2) -> 300 x 0,25 EUR = 75 EUR.
        $this->assertSame(7500, $lignes['kilometrage']['cents']);
        // Deux huitiemes manquants.
        $this->assertSame(2400, $lignes['carburant']['cents']);
    }

    public function test_les_avis_ne_se_revelent_qu_une_fois_les_deux_deposes(): void
    {
        $location = $this->rendre($this->locationEnCours());
        $avis = app(PeerReviewService::class);

        $premier = $avis->deposer($location->fresh(), $location->renter, 5, 'Voiture impeccable');

        $this->assertNull($premier->fresh()->revealed_at, 'Le premier avis ne doit pas être visible seul.');

        $avis->deposer($location->fresh(), $location->owner, 4, 'Locataire soigneux');

        $this->assertNotNull($premier->fresh()->revealed_at);
    }

    public function test_le_delai_passe_un_avis_seul_se_revele(): void
    {
        $location = $this->rendre($this->locationEnCours());
        $avis = app(PeerReviewService::class);

        $seul = $avis->deposer($location->fresh(), $location->renter, 5);
        $seul->forceFill(['submitted_at' => now()->subDays(PeerReview::JOURS_AVANT_REVELATION + 1)])->save();

        $this->assertSame(1, $avis->revelerLesAvisEnAttente());
        $this->assertNotNull($seul->fresh()->revealed_at);
    }

    /** TEMOIN — avant le delai, le meme avis reste cache. */
    public function test_temoin_avant_le_delai_l_avis_reste_cache(): void
    {
        $location = $this->rendre($this->locationEnCours());
        $avis = app(PeerReviewService::class);

        $seul = $avis->deposer($location->fresh(), $location->renter, 5);

        $this->assertSame(0, $avis->revelerLesAvisEnAttente());
        $this->assertNull($seul->fresh()->revealed_at);
    }
}
