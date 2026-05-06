<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

class ListMySitesTool implements AssistantTool
{
    public function name(): string
    {
        return 'list_my_sites';
    }

    public function description(): string
    {
        return "Liste les locaux/sites enregistrés par l'organisation cliente de l'utilisateur. "
            . "Utile quand l'utilisateur demande 'mes sites', 'mes adresses', 'mes locaux', "
            . "ou avant de créer une réservation depuis l'assistant pour identifier le site cible. "
            . "Réservé aux utilisateurs membres d'une organisation cliente.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(), // pas de paramètres
            'required' => [],
        ];
    }

    public function authorize(User $user): bool
    {
        return $user->organization_account_id !== null;
    }

    public function executesImmediately(): bool
    {
        return true;
    }

    public function execute(User $user, array $input): array
    {
        if (! $user->organization_account_id) {
            return [
                'ok'    => false,
                'error' => "Vous n'êtes pas rattaché à une organisation.",
            ];
        }

        $sites = OrganizationSite::query()
            ->where('organization_account_id', $user->organization_account_id)
            ->orderBy('name')
            ->limit(20)
            ->get();

        return [
            'count' => $sites->count(),
            'sites' => $sites->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'address'     => $s->address ?? null,
                'city'        => $s->city ?? null,
                'postal_code' => $s->postal_code ?? null,
                'is_active'   => (bool) ($s->is_active ?? true),
            ])->all(),
        ];
    }
}
