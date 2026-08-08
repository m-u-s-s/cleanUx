<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UNE IMPLANTATION DE LA SOCIÉTÉ PRESTATAIRE — le dépôt de Bruxelles, l'antenne d'Anvers.
 *
 * À NE PAS CONFONDRE AVEC `organization_sites`, qui désigne les locaux du CLIENT : un prestataire ne
 * possède pas les immeubles où il intervient. Les deux notions se ressemblent sur le papier — une
 * adresse, une ville — et n'ont rien à voir dans le domaine. Les confondre donnerait à une société
 * un droit sur les locaux de ses clients.
 *
 * Une société qui n'a qu'une implantation n'en déclare aucune : le rattachement reste `null`
 * partout, et le moteur de répartition n'accorde alors aucun point d'agence.
 */
class ProviderAgency extends Model
{
    protected $fillable = [
        'provider_organization_id',
        'name',
        'slug',
        'address',
        'city',
        'postal_code',
        'country_code',
        'lat',
        'lng',
        'service_zone_id',
        'status',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    /** @return HasMany<FieldTeam, $this> */
    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class, 'provider_agency_id');
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'provider_agency_id');
    }
}
