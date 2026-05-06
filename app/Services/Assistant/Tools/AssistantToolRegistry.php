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
     */
    private function allowedToolNamesForRole(AssistantContextRole $role): array
    {
        return match ($role) {
            AssistantContextRole::CLIENT_PERSONAL => [
                'list_my_bookings',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
            ],
            AssistantContextRole::CLIENT_COMPANY => [
                'list_my_bookings',
                'list_my_sites',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'register_site',
                'report_issue',
            ],
            AssistantContextRole::PROVIDER_INDEPENDENT => [
                'list_my_bookings',
                'report_issue',
            ],
            AssistantContextRole::PROVIDER_COMPANY => [
                'list_my_bookings',
                'report_issue',
            ],
            AssistantContextRole::ADMIN => [
                // Admin a accès à tout
                'list_my_bookings',
                'list_my_sites',
                'list_services_catalog',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'register_site',
                'report_issue',
            ],
        };
    }
}
