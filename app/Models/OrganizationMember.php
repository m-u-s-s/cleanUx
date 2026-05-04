<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMember extends Model
{
    protected $fillable = [
        'organization_account_id',
        'user_id',
        'role',
        'permissions',
        'status',
        'invited_by',
        'invited_at',
        'joined_at',
    ];

    protected $casts = [
        'role'        => OrganizationRole::class,
        'permissions' => 'array',
        'invited_at'  => 'datetime',
        'joined_at'   => 'datetime',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
        $perms               = $this->permissions ?? [];
        $perms[$permission]  = true;
        $this->permissions   = $perms;
        $this->save();

        app(PermissionService::class)->invalidateCache($this->user_id, $this->organization_account_id);
    }

    public function revokePermission(string $permission): void
    {
        $perms               = $this->permissions ?? [];
        $perms[$permission]  = false;
        $this->permissions   = $perms;
        $this->save();

        app(PermissionService::class)->invalidateCache($this->user_id, $this->organization_account_id);
    }
}
