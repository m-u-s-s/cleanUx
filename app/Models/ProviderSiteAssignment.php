<?php

namespace App\Models;

use Database\Factories\ProviderSiteAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le référent d'une société prestataire sur un site client.
 *
 * @property int $provider_organization_id
 * @property int $organization_site_id
 * @property int $user_id
 * @property string $role
 */
class ProviderSiteAssignment extends Model
{
    /** @use HasFactory<ProviderSiteAssignmentFactory> */
    use HasFactory;

    /** Celui qu'on suggère au répartiteur. */
    public const ROLE_LEAD = 'lead';

    /** Celui qu'on suggère quand le titulaire est déjà pris. */
    public const ROLE_BACKUP = 'backup';

    protected $fillable = [
        'provider_organization_id',
        'organization_site_id',
        'user_id',
        'role',
        'notes',
    ];

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }

    /** @return BelongsTo<OrganizationSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
