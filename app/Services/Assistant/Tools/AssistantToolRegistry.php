<?php

namespace App\Services\Assistant\Tools;

use App\Enums\AssistantContextRole;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;
use App\Services\Assistant\Tools\Implementations\CancelBookingTool;
use App\Services\Assistant\Tools\Implementations\CreateBookingTool;
use App\Services\Assistant\Tools\Implementations\GetInvoiceTool;
use App\Services\Assistant\Tools\Implementations\ListMyBookingsTool;
use App\Services\Assistant\Tools\Implementations\ListMySitesTool;
use App\Services\Assistant\Tools\Implementations\ListServicesCatalogTool;
use App\Services\Assistant\Tools\Implementations\RegisterSiteTool;
use App\Services\Assistant\Tools\Implementations\ReportIssueTool;

/**
 * Phase 5.1 — Registre central avec les nouveaux tools.
 *
 * Ajout par rapport à Phase 5 :
 *   - GetInvoiceTool      (read, immediate)
 *   - RegisterSiteTool    (write, requires confirm)
 *   - ReportIssueTool     (write, requires confirm)
 */
class AssistantToolRegistry
{
    /**
     * @var array<int, class-string<AssistantTool>>
     */
    protected array $allTools = [
        // Phase 5
        ListMyBookingsTool::class,
        ListMySitesTool::class,
        ListServicesCatalogTool::class,
        CreateBookingTool::class,
        CancelBookingTool::class,
        // Phase 5.1
        GetInvoiceTool::class,
        RegisterSiteTool::class,
        ReportIssueTool::class,
    ];

    /**
     * @return array<int, AssistantTool>
     */
    public function toolsForUser(User $user): array
    {
        $role  = $user->assistantContextRole();
        $allow = $this->allowedToolNamesForRole($role);
        $allowedNames = $this->allowedToolNamesForRole($role);

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
        return array_values(array_filter(
            $this->tools,
            fn($tool) => in_array($tool->name(), $allowedNames, true)
        ));
        return $instances;
    }

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
     * @return array<int, array{name:string, description:string, input_schema:array}>
     */
    public function definitionsForUser(User $user): array
    {
        return array_map(
            fn(AssistantTool $t) => [
                'name'         => $t->name(),
                'description'  => $t->description(),
                'input_schema' => $t->inputSchema(),
            ],
            $this->toolsForUser($user)
        );
    }

    /**
     * Whitelist de tool names par rôle.
     */
    private function allowedToolNamesForRole(string|\BackedEnum $role): array
    {
        $role = $role instanceof \BackedEnum ? $role->value : $role;

        return match ($role) {
            'personal_client', 'client', 'client_personal' => [
                'list_my_bookings',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
            ],

            'company_client', 'client_company', 'entreprise', 'client_entreprise' => [
                'list_my_bookings',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
                'list_my_sites',
                'register_site',
            ],

            'provider_independent', 'provider', 'prestataire', 'employee', 'employe' => [
                'list_my_bookings',
                'get_invoice',
                'report_issue',
            ],

            'admin', 'super_admin' => array_keys($this->tools),

            default => [
                'list_my_bookings',
                'get_invoice',
                'report_issue',
            ],
        };
    }
}
