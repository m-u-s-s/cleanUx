<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** L'ÉQUIPE QUI DESSERT HABITUELLEMENT CE SITE. */
class ProviderSiteTeam extends Model
{
    protected $fillable = [
        'provider_organization_id',
        'organization_site_id',
        'field_team_id',
    ];

    /** @return BelongsTo<OrganizationSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OrganizationSite::class, 'organization_site_id');
    }

    /** @return BelongsTo<FieldTeam, $this> */
    public function fieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class);
    }
}
