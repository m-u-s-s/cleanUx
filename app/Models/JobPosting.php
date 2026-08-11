<?php

namespace App\Models;

use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * UNE OFFRE D'EMPLOI PUBLIÉE PAR UNE SOCIÉTÉ PRESTATAIRE (E25).
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $trade_id
 * @property string $reference
 * @property string $title
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    /** Rédigée, invisible : une offre à moitié écrite attire des candidatures à moitié pertinentes. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** Fermée mais conservée : les candidatures reçues restent lisibles. */
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_account_id',
        'trade_id',
        'provider_agency_id',
        'reference',
        'title',
        'description',
        'employment_type',
        'city',
        'salary_min_cents',
        'salary_max_cents',
        'status',
        'created_by_user_id',
        'published_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'salary_min_cents' => 'integer',
        'salary_max_cents' => 'integer',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function genererUneReference(): string
    {
        return 'JOB-'.now()->format('Ym').'-'.Str::upper(Str::random(6));
    }

    /** @return HasMany<JobApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** Accepte-t-elle encore des candidatures ? */
    public function accepteDesCandidatures(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
