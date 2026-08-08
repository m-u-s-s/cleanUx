<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'ÉQUIPE QUI DESSERT HABITUELLEMENT CE SITE.
 *
 * `provider_site_assignments` nomme des PERSONNES ; une équipe entière est le cas ordinaire d'un
 * grand site, et la désigner personne par personne recommence à chaque changement d'effectif.
 *
 * TOUTE LECTURE EST SCOPÉE `provider_organization_id` : plusieurs sociétés concurrentes peuvent
 * desservir le même immeuble — l'une le nettoyage, l'autre les espaces verts — et chacune y a son
 * équipe habituelle.
 */
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
