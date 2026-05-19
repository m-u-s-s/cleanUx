<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStep extends Model
{
    public const TYPE_FORM = 'form';
    public const TYPE_KYC_CHECK = 'kyc_check';
    public const TYPE_INSURANCE_PURCHASE = 'insurance_purchase';
    public const TYPE_PAYOUTS_SETUP = 'payouts_setup';
    public const TYPE_CONTRACT_SIGN = 'contract_sign';
    public const TYPE_PROFILE_COMPLETE = 'profile_complete';
    public const TYPE_SKILL_DECLARE = 'skill_declare';
    public const TYPE_DOCUMENT_UPLOAD = 'document_upload';

    protected $fillable = [
        'journey_id', 'position', 'code', 'label', 'description',
        'step_type', 'required', 'validator_class',
        'depends_on', 'is_skippable', 'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'required' => 'boolean',
        'depends_on' => 'array',
        'is_skippable' => 'boolean',
        'metadata' => 'array',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(OnboardingJourney::class, 'journey_id');
    }
}
