<?php

namespace App\Services\Assistant\Tools;

use App\Enums\AssistantContextRole;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;
use App\Services\Assistant\Tools\Implementations\CancelBookingTool;
use App\Services\Assistant\Tools\Implementations\CreateBookingTool;
use App\Services\Assistant\Tools\Implementations\ListMyBookingsTool;
use App\Services\Assistant\Tools\Implementations\ListMySitesTool;
use App\Services\Assistant\Tools\Implementations\ListServicesCatalogTool;

/**
 * Registre central des tools disponibles pour l'assistant.
 *
 * Pour ajouter un tool :
 *   1. Implémenter App\Services\Assistant\Tools\Contracts\AssistantTool
 *   2. L'ajouter dans la propriété $allTools ci-dessous
 *   3. (Optionnel) Restreindre par rôle dans toolsForRole()
 */
class AssistantToolRegistry
{
    /**
     * Liste exhaustive des classes de tools disponibles.
     * Order = priorité d'évaluation.
     *
     * @var array<int, class-string<AssistantTool>>
     */
    protected array $allTools = [
        ListMyBookingsTool::class,
        ListMySitesTool::class,
        ListServicesCatalogTool::class,
        CreateBookingTool::class,
        CancelBookingTool::class,
        // Phase 5.1 : ReportIssueTool, GetInvoiceTool, RegisterSiteTool, etc.
    ];

    /**
     * Retourne les tools accessibles à un utilisateur selon son rôle.
     *
     * @return array<int, AssistantTool>
     */
    public function toolsForUser(User $user): array
    {
        $role  = $user->assistantContextRole();
        $allow = $this->allowedToolNamesForRole($role);

        $instances = [];
        foreach ($this->allTools as $cls) {
            $tool = app($cls);
            if (! in_array($tool->name(), $allow, true)) {
                continue;
            }
            if (! $tool->authorize($user)) {
                continue;
            }
            $instances[] = $tool;
        }
        return $instances;
    }

    /**
     * Retrouve un tool par son nom (sans filtre de rôle, à utiliser pour
     * dispatch après que le LLM a déjà choisi).
     */
    public function find(string $name): ?AssistantTool
    {
        foreach ($this->allTools as $cls) {
            $tool = app($cls);
            if ($tool->name() === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Format Anthropic tool definitions pour l'API Messages.
     *
     * @return array<int, array{name:string, description:string, input_schema:array}>
     */
    public function definitionsForUser(User $user): array
    {
        return array_map(
            fn (AssistantTool $t) => [
                'name'         => $t->name(),
                'description'  => $t->description(),
                'input_schema' => $t->inputSchema(),
            ],
            $this->toolsForUser($user)
        );
    }

    /**
     * Whitelist de tool names par rôle.
     * Aligne sur AssistantContextBuilder::availableActions() pour cohérence.
     */
    private function allowedToolNamesForRole(AssistantContextRole $role): array
    {
        return match ($role) {
            AssistantContextRole::CLIENT_PERSONAL => [
                'list_my_bookings',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
            ],
            AssistantContextRole::CLIENT_COMPANY => [
                'list_my_bookings',
                'list_my_sites',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
            ],
            AssistantContextRole::PROVIDER_INDEPENDENT => [
                'list_my_bookings',
            ],
            AssistantContextRole::PROVIDER_COMPANY => [
                // Le terrain n'a pas accès à la création de booking via assistant
            ],
            AssistantContextRole::ADMIN => [
                // Admin a tout
                'list_my_bookings',
                'list_my_sites',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
            ],
        };
    }
}
