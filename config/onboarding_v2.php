<?php

use App\Services\OnboardingV2\Validators\ContractSignValidator;
use App\Services\OnboardingV2\Validators\DocumentUploadValidator;
use App\Services\OnboardingV2\Validators\FormStepValidator;
use App\Services\OnboardingV2\Validators\InsurancePurchaseValidator;
use App\Services\OnboardingV2\Validators\KycCheckValidator;
use App\Services\OnboardingV2\Validators\PayoutsSetupValidator;
use App\Services\OnboardingV2\Validators\ProfileCompleteValidator;
use App\Services\OnboardingV2\Validators\SkillDeclareValidator;

return [
    'enabled' => env('ONBOARDING_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default journey code par role (auto-start on first request)
    |--------------------------------------------------------------------------
    */
    'default_journey_per_role' => [
        'client' => 'client_default',
        'provider' => 'provider_default',
        'employe' => 'provider_default',
        'enterprise' => 'enterprise_default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validators registry — step_type → FQCN
    |--------------------------------------------------------------------------
    | Le validator résolu reçoit (User, OnboardingStep, payload[]) et retourne
    | un OnboardingStepValidation (ok bool, errors, normalized_data).
    */
    'validators' => [
        'form' => FormStepValidator::class,
        'kyc_check' => KycCheckValidator::class,
        'insurance_purchase' => InsurancePurchaseValidator::class,
        'payouts_setup' => PayoutsSetupValidator::class,
        'contract_sign' => ContractSignValidator::class,
        'profile_complete' => ProfileCompleteValidator::class,
        'skill_declare' => SkillDeclareValidator::class,
        'document_upload' => DocumentUploadValidator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Comportement skip
    |--------------------------------------------------------------------------
    */
    'allow_skip_optional_steps' => true,

    /*
    |--------------------------------------------------------------------------
    | Quand un journey est complete : action additionnelle ?
    |--------------------------------------------------------------------------
    | mark_user_field : nom de la colonne users.X à mettre à now() (ex: 'onboarded_at')
    */
    'on_complete_user_field' => env('ONBOARDING_USER_COMPLETE_FIELD', null),
];
