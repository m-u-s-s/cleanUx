<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un message dans le fil d'une réclamation client. Propre à `CustomerClaim`. */
class CustomerClaimEvent extends Model
{
    protected $fillable = [
        'customer_claim_id',
        'author_role',
        'author_user_id',
        'body',
    ];

    /** @return BelongsTo<CustomerClaim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(CustomerClaim::class, 'customer_claim_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
