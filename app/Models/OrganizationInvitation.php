<?php

namespace App\Models;

use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Invitation d'un employé à rejoindre une organisation.
 *
 * À ne pas confondre avec `team_invitations`, vestige inerte de Jetstream (voir la migration
 * `create_organization_invitations_table` pour le détail).
 */
class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'email',
        'role',
        'invited_by',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public static function genererJeton(): string
    {
        return Str::random(64);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Une invitation n'est utilisable qu'en attente ET non périmée. Les deux conditions comptent :
     * un jeton laissé traîner dans une boîte mail reste sinon valable indéfiniment.
     */
    public function estUtilisable(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
