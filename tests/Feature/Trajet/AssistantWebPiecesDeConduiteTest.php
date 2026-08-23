<?php

namespace Tests\Feature\Trajet;

use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** L'ASSISTANT WEB NE DOIT PAS LAISSER CROIRE QU'UN DOSSIER EST COMPLET. */
class AssistantWebPiecesDeConduiteTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(bool $conduit): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $user->id, 'status' => 'active']);

        $trade = Trade::factory()->create();

        if ($conduit) {
            foreach ([LocationRole::PICKUP, LocationRole::DROPOFF] as $role) {
                Question::create([
                    'trade_id' => $trade->id,
                    'code' => $role,
                    'label' => LocationRole::label($role),
                    'type' => QuestionType::LOCATION,
                    'location_role' => $role,
                    'is_active' => true,
                ]);
            }
        }

        $user->trades()->attach($trade->id);

        return $user->fresh();
    }

    public function test_l_assistant_nomme_les_pieces_de_conduite_manquantes(): void
    {
        $this->actingAs($this->prestataire(conduit: true));

        Livewire::test(ProviderOnboardingWizard::class)
            ->assertSee('Permis de conduire')
            ->assertSee('Déposer mes pièces de conduite');
    }

    /** LE TÉMOIN : un métier ordinaire ne voit aucun bandeau supplémentaire. */
    public function test_un_metier_ordinaire_ne_voit_aucun_bandeau(): void
    {
        $this->actingAs($this->prestataire(conduit: false));

        Livewire::test(ProviderOnboardingWizard::class)
            ->assertDontSee('Déposer mes pièces de conduite');
    }

    public function test_une_piece_deposee_disparait_de_la_liste(): void
    {
        $user = $this->prestataire(conduit: true);
        $this->actingAs($user);

        $composant = Livewire::test(ProviderOnboardingWizard::class);
        $this->assertContains('Permis de conduire', $composant->instance()->piecesDeConduiteManquantes);

        ProviderOnboardingDocument::create([
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
            'file_path' => "providers/{$user->id}/permis.pdf",
        ]);

        // En relecture compte comme DÉPOSÉE : le prestataire a fait sa part, et lui redemander la
        // pièce pendant qu'un administrateur la regarde le ferait déposer deux fois la même chose.
        $this->assertNotContains(
            'Permis de conduire',
            Livewire::test(ProviderOnboardingWizard::class)->instance()->piecesDeConduiteManquantes,
        );
    }

    /** Une pièce REFUSÉE ne compte pas comme déposée : il faut la remplacer. */
    public function test_une_piece_refusee_reste_dans_la_liste(): void
    {
        $user = $this->prestataire(conduit: true);
        $this->actingAs($user);

        ProviderOnboardingDocument::create([
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
            'status' => ProviderOnboardingDocument::STATUS_REJECTED,
            'rejection_reason' => 'Illisible',
            'file_path' => "providers/{$user->id}/permis.pdf",
        ]);

        $this->assertContains(
            'Permis de conduire',
            Livewire::test(ProviderOnboardingWizard::class)->instance()->piecesDeConduiteManquantes,
        );
    }
}
