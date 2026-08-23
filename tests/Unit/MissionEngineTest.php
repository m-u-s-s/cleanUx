<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Support\Domain\MissionEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MissionEngineTest extends TestCase
{
    /** Le résolveur est PUR : il ne lit que des attributs, jamais la base. */
    private function reservation(array $attributs = []): Booking
    {
        return (new Booking)->forceFill($attributs);
    }

    #[Test]
    public function une_depose_fait_une_mission_de_vehicule(): void
    {
        $this->assertSame(
            MissionEngine::VEHICULE,
            MissionEngine::pourReservation($this->reservation([
                'dropoff_lat' => 50.85, 'dropoff_lng' => 4.35,
            ])),
        );
    }

    #[Test]
    public function du_temps_achete_sans_depose_fait_une_mission_horaire(): void
    {
        $this->assertSame(
            MissionEngine::HORAIRE,
            MissionEngine::pourReservation($this->reservation(['purchased_minutes' => 180])),
        );
    }

    #[Test]
    public function le_reste_est_une_mission_a_domicile(): void
    {
        $this->assertSame(MissionEngine::DOMICILE, MissionEngine::pourReservation($this->reservation()));
        $this->assertSame(MissionEngine::DOMICILE, MissionEngine::pourReservation(null));
    }

    #[Test]
    public function le_vehicule_prime_sur_l_horaire_quand_les_deux_sont_vrais(): void
    {
        $this->assertSame(
            MissionEngine::VEHICULE,
            MissionEngine::pourReservation($this->reservation([
                'dropoff_lat' => 50.85, 'dropoff_lng' => 4.35, 'purchased_minutes' => 180,
            ])),
        );
    }

    #[Test]
    public function une_depose_incomplete_ne_fait_pas_une_course(): void
    {
        $this->assertSame(
            MissionEngine::DOMICILE,
            MissionEngine::pourReservation($this->reservation(['dropoff_lat' => 50.85])),
        );
    }

    #[Test]
    public function un_temps_achete_nul_ne_fait_pas_une_mission_horaire(): void
    {
        $this->assertSame(
            MissionEngine::DOMICILE,
            MissionEngine::pourReservation($this->reservation(['purchased_minutes' => 0])),
        );
    }

    #[Test]
    public function chaque_moteur_declare_ce_qu_il_accepte(): void
    {
        $this->assertTrue(MissionEngine::accepteLeNouveauDevis(MissionEngine::DOMICILE));
        $this->assertFalse(MissionEngine::accepteLeNouveauDevis(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLeNouveauDevis(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLaToDoList(MissionEngine::DOMICILE));
        $this->assertTrue(MissionEngine::accepteLaToDoList(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLaToDoList(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLesCodes(MissionEngine::DOMICILE));
        $this->assertFalse(MissionEngine::accepteLesCodes(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLeSupplement(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLeSupplement(MissionEngine::VEHICULE));
    }

    #[Test]
    public function les_trois_moteurs_sont_enumerables(): void
    {
        $this->assertSame(
            [MissionEngine::VEHICULE, MissionEngine::HORAIRE, MissionEngine::DOMICILE],
            MissionEngine::all(),
        );
    }
}
