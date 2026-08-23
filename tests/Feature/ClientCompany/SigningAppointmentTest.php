<?php

namespace Tests\Feature\ClientCompany;

use App\Models\ContractDocument;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Models\User;
use App\Services\Contracts\SigningAppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** SIGNER UN CONTRAT SUR PLACE SUPPOSE D'ABORD POUVOIR EN FIXER LE RENDEZ-VOUS. */
class SigningAppointmentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeCliente(): array
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        return [$org, $user];
    }

    #[Test]
    public function on_planifie_une_signature_sur_un_local(): void
    {
        [$org, $signataire] = $this->societeCliente();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'site_code' => 'SIEGE',
        ]);

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            $signataire,
            now()->addDays(3),
            $site,
            null,
            'Signature du contrat-cadre au siège.',
        );

        $this->assertNotNull($rdv);
        $this->assertSame('scheduled', $rdv->status);
        $this->assertSame($site->id, $rdv->organization_site_id);
        $this->assertSame($org->id, $rdv->organization_account_id);
    }

    #[Test]
    public function on_ne_planifie_pas_sur_le_local_d_une_autre_societe(): void
    {
        [$org, $signataire] = $this->societeCliente();

        $autreOrg = OrganizationAccount::factory()->clientCompany()->create();
        $etranger = OrganizationSite::factory()->create([
            'organization_account_id' => $autreOrg->id,
            'site_code' => 'AUTRUI',
        ]);

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            $signataire,
            now()->addDays(3),
            $etranger,
        );

        $this->assertNull(
            $rdv,
            "L'identifiant du local vient du navigateur : fixer un rendez-vous chez autrui divulguerait son adresse.",
        );
        $this->assertSame(0, SigningAppointment::count());
    }

    #[Test]
    public function une_date_passee_est_refusee(): void
    {
        [$org, $signataire] = $this->societeCliente();

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            $signataire,
            now()->subDay(),
        );

        $this->assertNull($rdv, 'Un rendez-vous de signature déjà écoulé ne serait jamais honoré.');
    }

    #[Test]
    public function la_signature_effectuee_cloture_le_rendez_vous(): void
    {
        [$org, $signataire] = $this->societeCliente();

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            $signataire,
            now()->addDays(2),
        );

        app(SigningAppointmentService::class)->marquerSigne($rdv);

        $this->assertSame('completed', $rdv->fresh()->status);
        $this->assertNotNull($rdv->fresh()->completed_at);
    }

    #[Test]
    public function le_rendez_vous_expose_son_document_quand_il_y_en_a_un(): void
    {
        [$org, $signataire] = $this->societeCliente();

        $document = ContractDocument::factory()->create();

        $rdv = app(SigningAppointmentService::class)->planifier(
            $org,
            $signataire,
            now()->addDays(2),
            null,
            $document,
        );

        $this->assertSame($document->id, $rdv->contractDocument->id);
    }
}
