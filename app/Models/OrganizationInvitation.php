<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Invitation d'un employé à rejoindre une organisation. */
class OrganizationInvitation extends Model
{
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

    /** Une invitation n'est utilisable qu'en attente ET non périmée. */
    public function estUtilisable(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
