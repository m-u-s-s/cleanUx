<?php

namespace Tests\Feature\Navigation;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** UNE CASE DOIT ÊTRE VISIBLE EXACTEMENT QUAND SON ÉCRAN S'OUVRE. */
class CoherenceDesTuilesEtDesEcransTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tous les sous-rôles d'organisation — pas une sélection.
     *
     * @return list<OrganizationRole>
     */
    /**
     * UNE CASE PEUT DEPENDRE DES DONNEES, PAS D'UNE PERMISSION.
     *
     * `provider.face-check` suit `estSoumisAuControleFacial` — une cadence tiree au sort, pas
     * un droit. La page d'enrolement, elle, reste ouverte a tout prestataire : elle lui dit
     * justement s'il est concerne. Le sens inverse (case visible, ecran refuse) reste un defaut.
     */
    private const CASES_CONDITIONNEES_AUX_DONNEES = ['provider.face-check'];

    private function tousLesSousRoles(): array
    {
        return OrganizationRole::cases();
    }

    private function membre(OrganizationAccount $org, OrganizationRole $role, bool $prestataire): User
    {
        $user = $prestataire
            ? User::factory()->employe()->create()
            : User::factory()->entreprise()->create();

        $user->forceFill([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ])->save();

        if ($prestataire) {
            $user->providerProfile()->create([
                'organization_account_id' => $org->id,
                'provider_type' => ProviderType::COMPANY_WORKER->value,
                'status' => 'active',
            ]);
        }

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * @return list<array{route: string, label: string}>
     */
    private function casesDuContexte(string $contexte): array
    {
        // Le registre AUGMENTE : c'est celui que la navigation lit depuis la fusion des espaces.
        return ModuleCatalogue::catalogueComplet()
            ->filter(fn (array $m): bool => $m['context'] === $contexte)
            // Les transversaux (`*`) ne portent pas de permission d'organisation : les inclure
            // mesurerait la garde d'authentification, pas celle du registre.
            ->map(fn (array $m): array => ['route' => $m['route'], 'label' => $m['label']])
            ->values()
            ->all();
    }

    /**
     * Le cœur : pour chaque sous-rôle, l'ensemble des cases visibles doit être exactement l'ensemble des écrans qui répondent 200.
     *
     * @param  list<array{route: string, label: string}>  $cases
     * @return list<string> les incohérences, en clair
     */
    private function incoherences(User $utilisateur, string $contexte, array $cases): array
    {
        $this->actingAs($utilisateur);

        $visibles = ModuleCatalogue::pourContexte($contexte)
            ->flatMap(fn (array $groupe): array => $groupe['modules'])
            ->pluck('route')
            ->all();

        $ecarts = [];

        foreach ($cases as $case) {
            $laCaseEstVisible = in_array($case['route'], $visibles, true);

            // ON SUIT LES REDIRECTIONS, PARCE QU'UN 302 N'EST PAS UN REFUS.
            $statut = $this->followingRedirects()->get(route($case['route']))->getStatusCode();
            $lEcranSOuvre = $statut === 200;

            if ($laCaseEstVisible === $lEcranSOuvre) {
                continue;
            }

            if (! $laCaseEstVisible && in_array($case['route'], self::CASES_CONDITIONNEES_AUX_DONNEES, true)) {
                continue;
            }

            $ecarts[] = $laCaseEstVisible
                ? sprintf('%s : la case est visible mais l’écran rend %d', $case['label'], $statut)
                : sprintf('%s : l’écran s’ouvre (200) mais aucune case n’y mène', $case['label']);
        }

        return $ecarts;
    }

    #[Test]
    public function chaque_sous_role_de_societe_prestataire_voit_exactement_ce_qu_il_peut_ouvrir(): void
    {
        $cases = $this->casesDuContexte('provider-company');
        $this->assertNotEmpty($cases, 'Le contexte provider-company n’a aucune case : le test ne mesurerait rien.');

        $ecarts = [];

        foreach ($this->tousLesSousRoles() as $role) {
            $org = OrganizationAccount::factory()->providerCompany()->create();
            $utilisateur = $this->membre($org, $role, prestataire: true);

            foreach ($this->incoherences($utilisateur, 'provider-company', $cases) as $ecart) {
                $ecarts[] = $role->value.' — '.$ecart;
            }
        }

        $this->assertSame([], $ecarts, sprintf(
            "%d incohérence(s) entre les cases et les écrans :\n  %s",
            count($ecarts),
            implode("\n  ", $ecarts),
        ));
    }

    #[Test]
    public function chaque_sous_role_de_societe_cliente_voit_exactement_ce_qu_il_peut_ouvrir(): void
    {
        $cases = $this->casesDuContexte('client-company');
        $this->assertNotEmpty($cases, 'Le contexte client-company n’a aucune case : le test ne mesurerait rien.');

        $ecarts = [];

        foreach ($this->tousLesSousRoles() as $role) {
            $org = OrganizationAccount::factory()->clientCompany()->create();
            $utilisateur = $this->membre($org, $role, prestataire: false);

            foreach ($this->incoherences($utilisateur, 'client-company', $cases) as $ecart) {
                $ecarts[] = $role->value.' — '.$ecart;
            }
        }

        $this->assertSame([], $ecarts, sprintf(
            "%d incohérence(s) entre les cases et les écrans :\n  %s",
            count($ecarts),
            implode("\n  ", $ecarts),
        ));
    }
}
