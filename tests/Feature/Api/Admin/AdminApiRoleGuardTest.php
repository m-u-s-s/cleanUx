<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Le groupe /api/admin/* n'était gardé que par `api_scope`. */
class AdminApiRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un représentant par famille du groupe admin.
     *
     * @return array<string, array{string, string}>
     */
    public static function adminEndpoints(): array
    {
        return [
            'comptabilité' => ['GET', '/api/admin/accounting-v2/entries'],
            'jetons d’API' => ['GET', '/api/admin/api-tokens-v2/tokens'],
            'audit' => ['GET', '/api/admin/audit/events'],
            'flotte' => ['GET', '/api/admin/fleet-v2/vehicles'],
            'webhooks' => ['GET', '/api/admin/webhooks-v2/endpoints'],
            'abonnements' => ['GET', '/api/admin/subscriptions-v2/subscriptions'],
            'risque' => ['GET', '/api/admin/risk/evaluations'],
            'marketing' => ['GET', '/api/admin/marketing/campaigns'],
            'segments' => ['GET', '/api/admin/marketing/segments'],
            'chat' => ['GET', '/api/admin/chat-v2/threads'],
            'géolocalisation' => ['GET', '/api/admin/geolocation-v2/stats'],
            'KYB' => ['GET', '/api/admin/kyb-v2/entities'],
            'assurance' => ['GET', '/api/admin/insurance-v2/claims'],
            'annulations' => ['GET', '/api/admin/cancellations-v2'],
            'contrats' => ['GET', '/api/admin/contracts-v2/templates'],
            'tarification' => ['GET', '/api/admin/pricing-v2/quotes'],
            'onboarding' => ['GET', '/api/admin/onboarding-v2/progress'],
        ];
    }

    #[DataProvider('adminEndpoints')]
    public function test_un_compte_non_admin_est_refuse(string $method, string $uri): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client, ['*']);

        $this->json($method, $uri)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }

    #[DataProvider('adminEndpoints')]
    public function test_un_administrateur_franchit_la_garde(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $response = $this->json($method, $uri);

        // La garde ne dit rien de la santé de l'endpoint : on vérifie qu'elle ne l'arrête pas.
        // Assurer un 200 ici coupleraient ce test à la présence de données de démonstration.
        $this->assertNotSame(
            'forbidden_not_admin',
            $response->json('error'),
            "L'administrateur a été refusé sur {$uri}.",
        );
    }

    public function test_un_visiteur_anonyme_reste_non_authentifie(): void
    {
        // La garde de rôle ne doit pas transformer un 401 en 403 : les deux réponses ne disent pas
        // la même chose à l'application, qui efface son jeton sur 401 et affiche un refus sur 403.
        $this->getJson('/api/admin/audit/events')->assertStatus(401);
    }
}
