<?php

namespace Tests\Feature\Automation;

use App\Models\AlerteMetier;
use App\Models\AutomationReevaluation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FileEtAlertesSocleTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_tables_existent_avec_leurs_colonnes(): void
    {
        $attendues = [
            'automation_reevaluations' => ['evenement', 'entite_type', 'entite_id', 'depose_le'],
            'business_alertes' => ['cle', 'niveau', 'message', 'contexte', 'entite_type', 'entite_id', 'levee_le'],
        ];

        $manquantes = [];

        foreach ($attendues as $table => $colonnes) {
            foreach ($colonnes as $colonne) {
                if (! Schema::hasColumn($table, $colonne)) {
                    $manquantes[] = "{$table}.{$colonne}";
                }
            }
        }

        $this->assertSame([], $manquantes, 'Colonnes manquantes : '.implode(', ', $manquantes));
    }

    /** L'IDEMPOTENCE EST DANS L'INDEX. Deux fois le meme evenement sur la meme entite = une ligne. */
    public function test_deux_depots_identiques_ne_font_qu_une_ligne(): void
    {
        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);

        $this->expectException(QueryException::class);

        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);
    }

    /** TEMOIN — l'unicite porte sur le TRIPLET, pas sur l'evenement seul. */
    public function test_temoin_deux_entites_differentes_font_deux_lignes(): void
    {
        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 7,
            'depose_le' => now(),
        ]);

        AutomationReevaluation::create([
            'evenement' => 'alerte.payout_failed',
            'entite_type' => 'alerte',
            'entite_id' => 8,
            'depose_le' => now(),
        ]);

        $this->assertSame(2, AutomationReevaluation::count());
    }

    public function test_le_contexte_d_une_alerte_se_relit_en_tableau(): void
    {
        $alerte = AlerteMetier::create([
            'cle' => 'webhook_backlog',
            'niveau' => 'critical',
            'message' => 'File de webhooks trop profonde',
            'contexte' => ['count' => 412],
            'levee_le' => now(),
        ]);

        $this->assertSame(412, $alerte->fresh()->contexte['count']);
    }
}
