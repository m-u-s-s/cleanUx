<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Livewire\Admin\OnboardingV2\OnboardingV2Center;
use App\Livewire\Admin\Providers\ProviderRegistrationsCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LES CINQ PAGES D'ONBOARDING TIENNENT DANS UNE SEULE.
 *
 * Inscriptions, suivi des dossiers, documents, KYC et parcours vivaient sur cinq URL. L'admin
 * changeait de page a chaque geste, et DEUX d'entre elles approuvaient le meme prestataire avec
 * une rigueur tres inegale.
 */
class LOnboardingTientDansUnePageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function urlsFusionnees(): array
    {
        return [
            'inscriptions' => ['/admin/inscriptions-prestataires', 'inscriptions'],
            'suivi' => ['/admin/onboarding-providers', 'suivi'],
            'documents' => ['/admin/onboarding-documents', 'documents'],
            'kyc' => ['/admin/kyc', 'kyc'],
        ];
    }

    /**
     * Les anciennes URL ne meurent pas : elles conduisent au bon onglet. Un signet, un courriel
     * ou un module de la console mobile qui les vise doit atterrir, pas tomber en 404.
     */
    #[DataProvider('urlsFusionnees')]
    public function test_chaque_ancienne_url_conduit_a_son_onglet(string $url, string $espace): void
    {
        $this->actingAs($this->administrateur())
            ->get($url)
            ->assertRedirect('/admin/onboarding-v2?espace='.$espace);
    }

    /** TEMOIN — la page qui les absorbe repond bien, sans quoi les redirections ci-dessus ne prouveraient rien. */
    public function test_temoin_la_page_fusionnee_repond(): void
    {
        $this->actingAs($this->administrateur())
            ->get('/admin/onboarding-v2')
            ->assertOk();
    }

    public function test_la_page_porte_les_cinq_espaces(): void
    {
        Livewire::actingAs($this->administrateur())
            ->test(OnboardingV2Center::class)
            ->assertSee('Inscriptions')
            ->assertSee('Suivi des dossiers')
            ->assertSee('Documents')
            ->assertSee('KYC')
            ->assertSee('Parcours');
    }

    /**
     * UNE SEULE APPROBATION DANS LA PAGE. Celle du suivi ecrivait `verification_status = verified`
     * par un `forceFill` nu, sans trace ni motif ; celle des inscriptions trace qui approuve, avec
     * quels blocages, et n'active la societe que si le dossier le permet. La seconde reste.
     */
    public function test_l_approbation_inferieure_a_disparu_du_suivi(): void
    {
        $this->assertFalse(
            method_exists(AdminOnboardingProvidersList::class, 'approveOnboarding'),
            'Le suivi des dossiers approuve de nouveau en parallele de l’espace « Inscriptions ».'
        );

        // TEMOIN — l'approbation rigoureuse, elle, est toujours la.
        $this->assertTrue(
            method_exists(ProviderRegistrationsCenter::class, 'approve'),
            'L’approbation qui trace le motif a disparu : la fusion a garde la mauvaise.'
        );
    }

    private function administrateur(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-users', 'manage-compliance'],
        ]);
    }
}
