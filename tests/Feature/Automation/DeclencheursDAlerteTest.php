<?php

namespace Tests\Feature\Automation;

use App\Events\BusinessAlertRaised;
use App\Services\Automation\Contracts\Declencheur;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Support\Alerts\BusinessAlerts;
use Tests\TestCase;

class DeclencheursDAlerteTest extends TestCase
{
    /** Les cinq cles emises par BusinessAlerts, relevees dans le code le 2026-08-30. */
    private const CLES_EMISES = [
        'payment_capture_failed',
        'payout_failed',
        'webhook_backlog',
        'stuck_mission_holding_funds',
        'reconciliation_divergence',
    ];

    public function test_chaque_alerte_emise_a_son_declencheur(): void
    {
        $registre = app(DeclencheurRegistre::class);
        $manquants = [];

        foreach (self::CLES_EMISES as $cle) {
            if ($registre->trouver('alerte.'.$cle) === null) {
                $manquants[] = $cle;
            }
        }

        $this->assertSame([], $manquants, 'Alertes sans declencheur : '.implode(', ', $manquants));
    }

    /** L'INVERSE AUSSI : un declencheur qui ecoute une alerte que personne ne leve est mort. */
    public function test_aucun_declencheur_d_alerte_n_ecoute_dans_le_vide(): void
    {
        $source = (string) file_get_contents(app_path('Support/Alerts/BusinessAlerts.php'));
        $orphelins = [];

        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if (! str_starts_with($cle, 'alerte.')) {
                continue;
            }

            $alerte = substr($cle, strlen('alerte.'));

            if (! str_contains($source, "'".$alerte."'")) {
                $orphelins[] = $cle;
            }
        }

        $this->assertSame([], $orphelins, 'Declencheurs sans emetteur : '.implode(', ', $orphelins));
    }

    public function test_un_declencheur_ne_s_applique_qu_a_sa_propre_alerte(): void
    {
        $declencheur = app(DeclencheurRegistre::class)->trouver('alerte.payout_failed');

        $sien = new BusinessAlertRaised('critical', 'payout_failed', 'x');
        $autre = new BusinessAlertRaised('critical', 'webhook_backlog', 'y');

        $this->assertTrue($declencheur->sApplique($sien));
        $this->assertFalse($declencheur->sApplique($autre));
    }

    /** TEMOIN — le registre separe bien les cinq : un evenement n'en reveille qu'UN. */
    public function test_temoin_un_evenement_ne_reveille_qu_un_declencheur(): void
    {
        $trouves = app(DeclencheurRegistre::class)
            ->pourEvenement(new BusinessAlertRaised('critical', 'payout_failed', 'x'));

        $this->assertCount(1, $trouves);
        $this->assertSame('alerte.payout_failed', $trouves[0]->cle());
    }

    public function test_les_cinq_declencheurs_visent_l_entite_alerte(): void
    {
        foreach (app(DeclencheurRegistre::class)->toutes() as $cle => $declencheur) {
            if (str_starts_with($cle, 'alerte.')) {
                $this->assertSame('alerte', $declencheur->entite(), $cle);
            }
        }
    }
}
