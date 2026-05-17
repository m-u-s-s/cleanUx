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
        $role         = $user->assistantContextRole();
        $allowedNames = $this->allowedToolNamesForRole($role);

        $userRole = strtolower((string) ($user->role ?? ''));

        if (in_array($userRole, [
            'prestataire',
            'provider',
            'independent_provider',
            'provider_independent',
            'employe',
            'employee',
        ], true)) {
            $allowedNames = [
                'list_my_bookings',
                'get_invoice',
                'report_issue',
            ];
        }

        $isCompanyClient = (bool) ($user->organization_account_id ?? null)
            || in_array($userRole, [
                'entreprise',
                'enterprise',
                'client_entreprise',
                'company',
                'client_company',
                'b2b',
            ], true);

        if ($isCompanyClient) {
            $allowedNames = array_values(array_unique(array_merge($allowedNames, [
                'list_my_bookings',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
                'list_my_sites',
                'register_site',
            ])));
        } elseif (in_array($userRole, ['client', 'particulier', 'personal_client'], true)) {
            $allowedNames = array_values(array_diff($allowedNames, [
                'list_my_sites',
                'register_site',
            ]));
        }

        $instances = [];
        foreach ($this->allTools as $cls) {
            $tool = app($cls);
            if (! in_array($tool->name(), $allowedNames, true)) {
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
            'company_client',
            'enterprise_client',
            'entreprise',
            'client_company',
            'organization_client' => [
                'list_my_bookings',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
                'list_my_sites',
                'register_site',
            ],

            'provider',
            'provider_independent',
            'independent_provider',
            'provider_company',
            'prestataire',
            'employee',
            'employe' => [
                'list_my_bookings',
                'get_invoice',
                'report_issue',
            ],

            'personal_client',
            'client_personal',
            'client',
            'individual_client',
            'particulier' => [
                'list_my_bookings',
                'create_booking',
                'cancel_booking',
                'get_invoice',
                'report_issue',
            ],

            default => [
                'list_my_bookings',
                'get_invoice',
                'report_issue',
            ],
        };
    }

    private function normalizeAssistantRole(mixed $role): string
    {
        $parts = [];

        if ($role instanceof \BackedEnum) {
            $parts[] = (string) $role->value;
            $parts[] = $role->name;
        } elseif ($role instanceof \UnitEnum) {
            $parts[] = $role->name;
        } elseif (is_object($role)) {
            $parts[] = class_basename($role);

            if (method_exists($role, '__toString')) {
                $parts[] = (string) $role;
            }
        } else {
            $parts[] = (string) $role;
        }

        $text = strtolower(implode('_', array_filter($parts)));

        return str_replace(['-', '.', ' ', '\\', ':'], '_', $text);
    }
}
