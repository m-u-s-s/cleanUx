<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycCheck extends Model
{
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_FACIAL_SIMILARITY = 'facial_similarity';
    public const TYPE_WATCHLIST_AML = 'watchlist_aml';
    public const TYPE_CRIMINAL_RECORD = 'criminal_record';
    public const TYPE_RIGHT_TO_WORK = 'right_to_work';
    public const TYPE_ADDRESS = 'address';
    public const TYPE_TAX_ID = 'tax_id';

    public const RESULT_PENDING = 'pending';
    public const RESULT_CLEAR = 'clear';
    public const RESULT_CONSIDER = 'consider';
    public const RESULT_REJECTED = 'rejected';
    public const RESULT_UNIDENTIFIED = 'unidentified';
    public const RESULT_CAUTION = 'caution';

    protected $fillable = [
        'kyc_verification_id',
        'check_type',
        'result',
        'sub_result',
        'external_id',
        'confidence',
        'breakdown',
        'notes',
        'checked_at',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'confidence' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(KycVerification::class, 'kyc_verification_id');
    }

    public function isPositive(): bool
    {
        return $this->result === self::RESULT_CLEAR;
    }
}
