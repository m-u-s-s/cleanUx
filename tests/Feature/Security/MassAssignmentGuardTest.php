<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\RecurringBookingSeries;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** LES ENTITÉS CENTRALES NE DOIVENT PAS ÊTRE REMPLISSABLES DEPUIS UNE REQUÊTE. */
class MassAssignmentGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Les formes qui font entrer une requête entière dans une écriture de modèle. */
    private const FORMES_INTERDITES = [
        '/->fill\(\s*\$request->all\(\)/',
        '/->fill\(\s*request\(\)->all\(\)/',
        '/->update\(\s*\$request->all\(\)/',
        '/->update\(\s*request\(\)->all\(\)/',
        '/::create\(\s*\$request->all\(\)/',
        '/::create\(\s*request\(\)->all\(\)/',
        '/->fill\(\s*\$this->all\(\)/',

        // `validated()` EST LA FORME LA PLUS DANGEREUSE, parce qu'elle a l'air prudente.
        '/->fill\(\s*\$request->validated\(\)\s*\)/',
        '/->update\(\s*\$request->validated\(\)\s*\)/',
        '/::create\(\s*\$request->validated\(\)\s*\)/',
        '/->fill\(\s*\$this->validated\(\)\s*\)/',
        '/->update\(\s*\$this->validated\(\)\s*\)/',
        '/::create\(\s*\$this->validated\(\)\s*\)/',
        '/->fill\(\s*\$validated\s*\)/',
        '/->update\(\s*\$validated\s*\)/',
        '/::create\(\s*\$validated\s*\)/',
    ];

    public function test_aucune_requete_entiere_n_atteint_une_assignation_en_masse(): void
    {
        $coupables = [];

        foreach (['app/Http', 'app/Livewire'] as $dossier) {
            $racine = base_path($dossier);

            if (! is_dir($racine)) {
                continue;
            }

            $fichiers = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            foreach ($fichiers as $fichier) {
                if (! $fichier->isFile() || $fichier->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($fichier->getPathname());

                foreach (self::FORMES_INTERDITES as $forme) {
                    if (preg_match($forme, $source)) {
                        $coupables[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $fichier->getPathname());
                        break;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $coupables,
            'Une requête entière alimente une assignation en masse. Sur `Booking`, cela ouvre `status`, '.
            '`client_id`, `employe_id` et `payment_status` au premier champ ajouté au formulaire. '.
            'Passez un tableau explicite, ou les seules clés validées.',
        );
    }

    public function test_recurring_series_allows_its_columns_but_not_id(): void
    {
        $series = (new RecurringBookingSeries)->fill([
            'frequency' => 'weekly',
            'customer_user_id' => 5,
            'status' => 'active',
        ]);

        $this->assertSame('weekly', $series->frequency);
        $this->assertSame(5, $series->customer_user_id);
        $this->assertSame('active', $series->status);

        // L'identifiant est REFUSÉ, et non plus écarté sans bruit : `preventSilentlyDiscardingAttributes`
        // transforme une perte muette en erreur. La garantie est plus forte qu'un attribut resté nul.
        $this->expectException(MassAssignmentException::class);

        (new RecurringBookingSeries)->fill(['id' => 999]);
    }

    /**
     * Les colonnes par lesquelles l'argent passe.
     *
     * @return array<int, array{0: string, 1: mixed}>
     */
    public static function colonnesDArgent(): array
    {
        return [
            ['payment_status', 'captured'],
            ['payout_status', 'transferred'],
            ['payment_amount_cents', 999900],
            ['provider_amount_cents', 999900],
            ['provider_payout_cents', 999900],
            ['platform_fee_cents', 0],
            ['final_price', 9999],
            ['id', 999],
        ];
    }

    #[DataProvider('colonnesDArgent')]
    public function test_les_colonnes_d_argent_sont_hors_de_portee(string $colonne, mixed $valeur): void
    {
        // Les treize points d'écriture légitimes emploient `forceFill()`, dans les services de
        // paiement : l'intention y est écrite en toutes lettres. Partout ailleurs, c'est un refus.
        $this->expectException(MassAssignmentException::class);

        (new Booking)->fill([$colonne => $valeur]);
    }

    public function test_user_tenant_id_is_not_mass_assignable(): void
    {
        $user = (new User)->fill(['name' => 'X']);

        $this->assertSame('X', $user->name);

        // M7 — la colonne morte de Tenancy v2 n'est pas assignable, et la tentative est désormais
        // refusée plutôt qu'ignorée.
        $this->expectException(MassAssignmentException::class);

        (new User)->fill(['tenant_id' => 99]);
    }

    public function test_users_table_has_no_tenant_id_column(): void
    {
        // M7 column drop — the dead Tenancy-v2 column is gone (audit_events.tenant_id stays).
        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('audit_events', 'tenant_id'));
    }
}
