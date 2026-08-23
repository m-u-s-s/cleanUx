<?php

namespace Tests\Feature\Simulation;

use App\Support\Domain\MissionStatus;
use Tests\TestCase;

/** LE VOCABULAIRE DES STATUTS DOIT ÊTRE LE MÊME DES DEUX CÔTÉS. */
class MissionsStatutsAlignesTest extends TestCase
{
    private const LABELS = 'mobile/provider/src/missions/labels.ts';

    /**
     * Les clés déclarées par la table de libellés du mobile.
     *
     * @return list<string>
     */
    private function statutsDuMobile(): array
    {
        $chemin = base_path(self::LABELS);

        $this->assertFileExists($chemin, 'La table de libellés du mobile a été déplacée : ce garde-fou ne garde plus rien.');

        $source = (string) file_get_contents($chemin);

        $bloc = null;
        if (preg_match('/MISSION_STATUS_LABELS[^{]*\{(.*?)\}/s', $source, $m) === 1) {
            $bloc = $m[1];
        }

        $this->assertNotNull($bloc, 'MISSION_STATUS_LABELS est introuvable dans le fichier de libellés.');

        preg_match_all('/^\s*([a-z_]+)\s*:/m', $bloc, $cles);

        return array_values(array_unique($cles[1]));
    }

    public function test_le_mobile_n_invente_aucun_statut_de_mission(): void
    {
        $inventes = array_diff($this->statutsDuMobile(), MissionStatus::all());

        $this->assertSame(
            [],
            array_values($inventes),
            "Ces statuts sont déclarés côté mobile et n'existent pas dans MissionStatus : "
            .implode(', ', $inventes)
            ."\nUne garde écrite sur l'un d'eux ne se déclenchera jamais, et l'écran restera vide "
            .'sans la moindre erreur.',
        );
    }

    public function test_le_mobile_sait_nommer_chaque_statut_du_serveur(): void
    {
        $manquants = array_diff(MissionStatus::all(), $this->statutsDuMobile());

        $this->assertSame(
            [],
            array_values($manquants),
            'Ces statuts existent au serveur et le mobile ne sait pas les nommer : '
            .implode(', ', $manquants)
            ."\nIls s'afficheront bruts à l'écran du prestataire.",
        );
    }

    /** `pending` N'EST PAS UN STATUT DE MISSION, et du code de production en écrivait. */
    public function test_aucun_code_de_production_n_ecrit_un_statut_de_mission_hors_vocabulaire(): void
    {
        $fautifs = [];

        foreach ([
            'app/Services/Organizations/OrganizationMemberAdministration.php',
        ] as $fichier) {
            $source = (string) file_get_contents(base_path($fichier));

            if (str_contains($source, "'status' => 'pending'")) {
                $fautifs[] = $fichier;
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            'Ces fichiers écrivent un statut de mission hors vocabulaire : '.implode(', ', $fautifs),
        );
    }
}
