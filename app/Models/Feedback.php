<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feedback extends Model
{
    use HasFactory;

    public const DIRECTION_CLIENT_TO_PROVIDER = 'client_to_provider';
    public const DIRECTION_PROVIDER_TO_CLIENT = 'provider_to_client';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'feedback';

    protected $fillable = [
        'rendez_vous_id',
        'booking_id',
        'mission_id',
        'client_id',
        'client_user_id',
        'client_organization_id',
        'employe_id',
        'direction',
        'note',
        'rating',
        'punctuality_score',
        'quality_score',
        'communication_score',
        'value_score',
        'commentaire',
        'comment',
        'reponse_admin',
        'provider_response',
        'provider_responded_at',
        'answered_by',
        'answered_at',
        'status',
        'is_public',
        'is_hidden',
        'hidden_reason',
        'hidden_at',
        'hidden_by_user_id',
        'published_at',
        'reports_count',
        'metadata',
        'feedback',
    ];

    protected $casts = [
        'note' => 'integer',
        'rating' => 'integer',
        'punctuality_score' => 'integer',
        'quality_score' => 'integer',
        'communication_score' => 'integer',
        'value_score' => 'integer',
        'is_public' => 'boolean',
        'is_hidden' => 'boolean',
        'reports_count' => 'integer',
        'answered_at' => 'datetime',
        'provider_responded_at' => 'datetime',
        'hidden_at' => 'datetime',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'rendez_vous_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RatingReport::class);
    }

    public function effectiveRating(): ?int
    {
        return $this->rating ?? $this->note;
    }

    public function effectiveComment(): ?string
    {
        return $this->comment ?: $this->commentaire ?: $this->feedback;
    }

    public function isPubliclyVisible(): bool
    {
        return (bool) $this->is_public
            && ! $this->is_hidden
            && ($this->status === self::STATUS_PUBLISHED || $this->published_at !== null);
    }

    public function isClientToProvider(): bool
    {
        return ($this->direction ?? self::DIRECTION_CLIENT_TO_PROVIDER) === self::DIRECTION_CLIENT_TO_PROVIDER;
    }

    public function isProviderToClient(): bool
    {
        return $this->direction === self::DIRECTION_PROVIDER_TO_CLIENT;
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_public', true)
            ->where('is_hidden', false)
            ->where(function (Builder $q) {
                $q->where('status', self::STATUS_PUBLISHED)
                    ->orWhereNotNull('published_at');
            });
    }

    public function scopeForProvider(Builder $query, int $providerUserId): Builder
    {
        return $query
            ->where('employe_id', $providerUserId)
            ->where('direction', self::DIRECTION_CLIENT_TO_PROVIDER);
    }

    public function scopeForClient(Builder $query, int $clientUserId): Builder
    {
        return $query
            ->where('client_id', $clientUserId)
            ->where('direction', self::DIRECTION_PROVIDER_TO_CLIENT);
    }
}
