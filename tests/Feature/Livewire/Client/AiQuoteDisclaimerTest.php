<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\AiQuotePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit LOW — le devis IA photo doit afficher un disclaimer clair (estimation,
 * pas un devis ferme) pour éviter qu'il soit pris pour un engagement contractuel.
 */
class AiQuoteDisclaimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_result_shows_disclaimer(): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test(AiQuotePhoto::class)
            ->set('result', [
                'trade_name' => 'Peinture',
                'prix_min_eur' => 100,
                'prix_max_eur' => 200,
                'confiance' => 80,
            ])
            ->assertSee('Estimation indicative')
            ->assertSee('pas un devis ferme');
    }
}
