<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

/**
 * Phase 5.1 — Tool: enregistrer un nouveau site pour une organisation cliente.
 *
 * Réservé aux membres d'une organisation cliente avec permission sites.create.
 * Demande confirmation avant exécution (action d'écriture).
 */
class RegisterSiteTool implements AssistantTool
{
    public function name(): string
    {
        return 'register_site';
    }

    public function description(): string
    {
        return "Enregistre un nouveau local/site pour l'organisation de l'utilisateur. "
            . "Réservé aux entreprises clientes. Demande TOUJOURS le nom, l'adresse complète, "
            . "la ville et le code postal AVANT d'appeler ce tool. "
            . "Le tool ne crée pas immédiatement : il prépare une demande qui sera confirmée par "
            . "l'utilisateur dans l'UI.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type'        => 'string',
                    'description' => "Nom interne du site (ex: 'Bureaux Bruxelles', 'Magasin Anvers').",
                ],
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['office', 'shop', 'school', 'warehouse', 'restaurant', 'other'],
                    'description' => "Type de local.",
                ],
                'address' => [
                    'type'        => 'string',
                    'description' => "Adresse complète (rue + numéro).",
                ],
                'city' => [
                    'type'        => 'string',
                    'description' => "Ville.",
                ],
                'postal_code' => [
                    'type'        => 'string',
                    'description' => "Code postal.",
                ],
                'country' => [
                    'type'        => 'string',
                    'pattern'     => '^[A-Z]{2}$',
                    'description' => "Code pays ISO 2 lettres (BE, FR, NL, LU). Défaut: BE.",
                ],
                'notes' => [
                    'type'        => 'string',
                    'maxLength'   => 1000,
                    'description' => "Notes libres (accès, contact, particularités).",
                ],
            ],
            'required' => ['name', 'address', 'city', 'postal_code'],
        ];
    }

    public function authorize(User $user): bool
    {
        $orgId = $user->organization_account_id;
        if (! $orgId) {
            return false;
        }

        $org = $user->currentOrganization;
        if (! $org) {
            return false;
        }

        return app(\App\Services\PermissionService::class)
            ->can($user, 'sites.create', $org);
    }

    public function executesImmediately(): bool
    {
        return false;
    }

    public function execute(User $user, array $input): array
    {
        $site = OrganizationSite::create([
            'organization_account_id' => $user->organization_account_id,
            'name'                    => $input['name'],
            'type'                    => $input['type'] ?? null,
            'address'                 => $input['address'],
            'city'                    => $input['city'],
            'postal_code'             => $input['postal_code'],
            'country'                 => $input['country'] ?? 'BE',
            'notes'                   => $input['notes'] ?? null,
            'is_active'               => true,
        ]);

        return [
            'ok'         => true,
            'site_id'    => $site->id,
            'site_name'  => $site->name,
            'message'    => "Site \"{$site->name}\" enregistré avec succès.",
        ];
    }
}
