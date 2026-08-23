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

    /** L'ADHÉSION EST UNE DONNÉE DE SÉCURITÉ, PAS UNE DONNÉE MÉTIER. */
    protected function auditEventDomain(): string
    {
        return 'security';
    }

    /**
     * Ce qu'on enregistre, et rien d'autre.
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

    /** Définir une permission personnalisée sur ce membre. */
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
