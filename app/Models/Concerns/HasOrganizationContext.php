<?php

namespace App\Models\Concerns;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOrganizationContext
{
    /** @return BelongsTo<OrganizationAccount, $this> */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'current_organization_id');
    }

    public function getOrganizationAccountIdAttribute(): ?int
    {
        return $this->attributes['organization_account_id']
            ?? $this->attributes['current_organization_id']
            ?? null;
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function membershipIn(?OrganizationAccount $org = null): ?OrganizationMember
    {
        $orgId = $org?->id ?? $this->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return $this->organizationMemberships()
            ->where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->first();
    }

    public function roleIn(?OrganizationAccount $org = null): ?OrganizationRole
    {
        return $this->membershipIn($org)?->role;
    }

    public function canDoInOrg(string $permission, OrganizationAccount|int $org): bool
    {
        return app(PermissionService::class)->can($this, $permission, $org);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function hasOrganizationContext(): bool
    {
        return filled($this->organization_account_id)
            || filled($this->current_organization_id)
            || filled(data_get($this->metadata, 'organization_account_id'))
            || filled(data_get($this->metadata, 'entreprise_context'));
    }

    /**
     * L'utilisateur appartient-il à une entreprise CLIENTE active (espace société) ?
     * Condition du pont de navigation vers /dashboard/entreprise-client.
     */
    public function belongsToClientCompany(): bool
    {
        $type = OrganizationType::tryFrom(
            (string) $this->currentOrganization?->type
        );

        return $type?->isClient() ?? false;
    }

    /**
     * L'organisation dans laquelle cet utilisateur agit.
     *
     * LES QUATRE PREMIERS NIVEAUX SONT DES POINTEURS. Ils vivent sur `users` et sur `metadata`, et
     * rien ne garantit qu'ils soient écrits : relevé sur la base de développement, le compte
     * `provider@soc.com` était propriétaire ACTIF d'une société prestataire avec
     * `organization_account_id` ET `current_organization_id` à NULL. Quatre comptes dans ce cas, et
     * aucun n'était issu d'un seeder — le parcours d'inscription les produit.
     *
     * L'APPARTENANCE, ELLE, EST LA VÉRITÉ. Elle vit dans `organization_members`, et c'est déjà ce
     * que vérifient les gardes. D'où ce cinquième niveau : une adhésion ACTIVE et UNIQUE désigne
     * l'organisation sans le moindre doute, et s'y fier est plus sûr que de croire un pointeur que
     * personne ne maintient.
     *
     * ON S'ARRÊTE À L'UNIQUE. Avec plusieurs adhésions actives et aucun choix enregistré, on rend
     * `null` : prendre la première par ordre d'identifiant placerait quelqu'un dans la mauvaise
     * entreprise, où il verrait des missions, des membres et une facturation qui ne sont pas les
     * siens. Un 403 se remarque et se corrige ; une confusion silencieuse entre deux sociétés, non.
     */
    public function organizationContextId(): ?int
    {
        $pointeur = $this->organization_account_id
            ?? $this->current_organization_id
            ?? data_get($this->metadata, 'organization_account_id')
            ?? data_get($this->metadata, 'entreprise_context.organization_account_id');

        if ($pointeur !== null) {
            return (int) $pointeur;
        }

        // Sans identifiant, pas d'adhésion à chercher — et pas de requête pour un modèle construit
        // en mémoire, ce que font les tests unitaires du service de permissions.
        if (! $this->exists) {
            return null;
        }

        $adhesions = OrganizationMember::query()
            ->where('user_id', $this->getKey())
            ->where('status', 'active')
            ->limit(2)
            ->pluck('organization_account_id');

        return $adhesions->count() === 1 ? (int) $adhesions->first() : null;
    }

    /** @return HasMany<OrganizationSite, $this> */
    public function organizationSites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class, 'organization_account_id', 'organization_account_id');
    }
}
