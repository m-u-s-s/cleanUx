<?php

namespace Tests\Feature\ClientCompany;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bookings\BulkBookingImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'IMPORT CSV NE RÉSOUT AUCUN SITE — IL PLANTE.
 *
 * POURQUOI CE FICHIER EXISTE. `createBookingFromRow()` cherchait le site avec
 * `where('code', $data['site_code'])`, alors que la colonne s'appelle `site_code` :
 * `organization_sites` n'a PAS de colonne `code` (vérifié via le schéma — elle porte
 * `postal_code`, `postal_code_id`, `alarm_code_required` et `site_code`).
 *
 * Toute ligne CSV renseignant un site lève donc une QueryException. Comme l'import enveloppe
 * le lot dans une transaction, une seule ligne avec site fait échouer et annuler TOUT le
 * fichier — l'utilisateur ne voit ni booking créé ni cause.
 */
class BulkBookingImporterSiteCodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function une_ligne_avec_site_code_resout_le_site(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $demandeur = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'site_code' => 'LYON-01',
        ]);

        // `trades.slug` est NOT NULL : passer par la factory plutôt que par un firstOrCreate
        // partiel, qui échouait sur la contrainte et masquait le vrai sujet du test.
        $trade = Trade::factory()->create();

        $csv = "site_code,trade_code,scheduled_at,duration_minutes\n"
            ."LYON-01,{$trade->code},".now()->addDay()->format('Y-m-d H:i').",120\n";

        $rapport = app(BulkBookingImporter::class)->import($demandeur, $org->id, $csv);

        $this->assertSame(
            0,
            count($rapport['errors'] ?? []),
            'Une ligne renseignant un site a échoué : '.json_encode($rapport['errors'] ?? []),
        );

        $this->assertDatabaseHas('bookings', [
            'organization_site_id' => $site->id,
        ]);
    }
}
