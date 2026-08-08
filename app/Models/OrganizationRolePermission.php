<?php

namespace App\Models;

use Database\Factories\OrganizationRolePermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un réglage rôle → permission propre à une organisation.
 *
 * Complète, sans la remplacer, la matrice par défaut codée dans `PermissionService`.
 */
class OrganizationRolePermission extends Model
{
    /** @use HasFactory<OrganizationRolePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'role',
        'permission',
        'granted',
    ];

    protected $casts = [
        'granted' => 'boolean',
    ];

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }
}
