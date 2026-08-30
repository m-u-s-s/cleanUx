<?php

namespace Tests\Feature\Automation;

use App\Models\Booking;
use App\Models\PostalCode;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDescriptorTest extends TestCase
{
    use RefreshDatabase;

    private function compter(array $noeud): int
    {
        $entite = app(EntiteRegistre::class)->descripteur('booking');
        $requete = $entite->baseQuery();
        app(RuleTreeEvaluator::class)->apply($requete, $noeud, $entite);

        return $requete->count();
    }

    public function test_le_registre_sert_le_descripteur_des_reservations(): void
    {
        $this->assertContains('booking', app(EntiteRegistre::class)->cles());
        $this->assertNotNull(app(EntiteRegistre::class)->descripteur('booking'));
    }

    public function test_une_cle_inconnue_ne_rend_rien(): void
    {
        $this->assertNull(app(EntiteRegistre::class)->descripteur('licorne'));
    }

    public function test_le_statut_filtre(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        Booking::factory()->create(['status' => 'confirme']);

        $this->assertSame(1, $this->compter(['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente']));
    }

    public function test_la_ville_filtre(): void
    {
        $postalCode1 = PostalCode::factory()->create(['city_name' => 'Ixelles']);
        $postalCode2 = PostalCode::factory()->create(['city_name' => 'Anvers']);

        Booking::factory()->create(['postal_code_id' => $postalCode1->id]);
        Booking::factory()->create(['postal_code_id' => $postalCode2->id]);

        $this->assertSame(1, $this->compter(['field' => 'ville', 'op' => 'eq', 'value' => 'Ixelles']));
    }

    /**
     * CHAQUE CHAMP DECLARE DOIT S'EXECUTER — le garde-fou du lot 1, applique ici.
     * Un champ pointant une colonne absente ne casserait qu'en production.
     */
    public function test_chaque_champ_declare_produit_une_requete_qui_s_execute(): void
    {
        $echecs = [];

        foreach (array_keys(app(EntiteRegistre::class)->descripteur('booking')->fields()) as $champ) {
            $entite = app(EntiteRegistre::class)->descripteur('booking');
            $requete = $entite->baseQuery();

            try {
                app(RuleTreeEvaluator::class)->apply(
                    $requete,
                    ['and' => [
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                        ['field' => $champ, 'op' => 'is_not_null', 'value' => null],
                    ]],
                    $entite
                );
                $requete->count();
            } catch (\Throwable $e) {
                $echecs[] = $champ.' : '.substr($e->getMessage(), 0, 90);
            }
        }

        $this->assertSame([], $echecs, "Ces champs ne s'executent pas :\n".implode("\n", $echecs));
    }

    /** TEMOIN — le descripteur declare bien des champs. */
    public function test_temoin_le_descripteur_declare_des_champs(): void
    {
        $this->assertNotEmpty(app(EntiteRegistre::class)->descripteur('booking')->fields());
    }
}
