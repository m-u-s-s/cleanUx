<?php

namespace Tests\Unit\Conditions;

use App\Services\Conditions\FieldBinding;
use PHPUnit\Framework\TestCase;

class FieldBindingTest extends TestCase
{
    public function test_une_colonne_porte_son_nom_et_est_servable(): void
    {
        $liaison = FieldBinding::colonne('users.locale');

        $this->assertSame('users.locale', $liaison->colonne);
        $this->assertNull($liaison->jointure);
        $this->assertTrue($liaison->servable);
    }

    public function test_une_jointure_porte_sa_fermeture_et_aucune_colonne(): void
    {
        $liaison = FieldBinding::jointe(fn ($racine) => 'agg.valeur');

        $this->assertNull($liaison->colonne);
        $this->assertNotNull($liaison->jointure);
        $this->assertTrue($liaison->servable);
    }

    public function test_une_liaison_indisponible_ne_porte_rien(): void
    {
        $liaison = FieldBinding::indisponible();

        $this->assertNull($liaison->colonne);
        $this->assertNull($liaison->jointure);
        $this->assertFalse($liaison->servable);
    }
}
