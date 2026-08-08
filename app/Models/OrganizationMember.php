<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Services\Audit\Concerns\AuditsEloquentEvents;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    use AuditsEloquentEvents;
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'provider_agency_id',
        'user_id',
        'role',
        'permissions',
        'status',
        'invited_by',
        'invited_at',
        'joined_at',
    ];

    protected $casts = [
        'role' => OrganizationRole::class,
        'permissions' => 'array',
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    /**
     * L'ADHÉSION EST UNE DONNÉE DE SÉCURITÉ, PAS UNE DONNÉE MÉTIER.
     *
     * Ce qu'on y écrit décide de qui répartit les missions, qui voit la facturation et qui peut
     * retirer un collègue. C'est l'objet le plus sensible des espaces société — et rien n'en
     * conservait la trace, alors que le module Audit v2 et ce trait existent depuis 2026-05-19.
     *
     * Domaine `security` : le rôle et les dérogations relèvent du contrôle d'accès, pas de la
     * gestion d'équipe. La distinction gouverne la rétention et qui peut relire ces événements.
     */
    protected function auditEventDomain(): string
    {
        return 'security';
    }

    /**
     * Ce qu'on enregistre, et rien d'autre.
     *
     * `invited_at` / `joined_at` bougent au fil de la vie normale d'une adhésion et noieraient les
     * trois changements qui comptent réellement.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['role', 'permissions', 'status'];
    }

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ──────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithRole($query, OrganizationRole $role)
    {
        return $query->where('role', $role->value);
    }

    // ──────────────────────────────────────────────────────
    // Permission helpers
    // ──────────────────────────────────────────────────────

    public function can(string $permission): bool
    {
        return app(PermissionService::class)->memberCan($this, $permission);
    }

    public function allPermissions(): array
    {
        return app(PermissionService::class)->allPermissionsFor($this);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === OrganizationRole::OWNER;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleLabel(): string
    {
        return $this->role->label();
    }

    /**
     * Définir une permission personnalisée sur ce membre.
     * Passe-droit au-dessus de la matrice du rôle.
     */
    public function grantPermission(string $permission): void
    {
        $perms = $this->permissions ?? [];
        $perms[$permission] = true;
        $this->permissions = $perms;
        $this->save();

        app(PermissionService::class)->invalidateCache($this->user_id, $this->organization_account_id);
    }

    public function revokePermission(string $permission): void
    {
        $perms = $this->permissions ?? [];
        $perms[$permission] = false;
        $this->permissions = $perms;
        $this->save();

        app(PermissionService::class)->invalidateCache($this->user_id, $this->organization_account_id);
    }
}
