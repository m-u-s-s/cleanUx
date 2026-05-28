<?php

use App\Models\Company;
use App\Models\MissionVerificationCode;
use App\Models\QualityAudit;
use App\Models\RecurringBookingSeries;
use App\Models\RendezVous;
use App\Models\SubscriptionPlan;

return [

    /*
    | Legitimate exceptions, keyed by model class. Each value is a list of either
    | a column name (suppresses any finding for that column) or 'rule:<rule_name>'
    | (suppresses all findings of that rule for the model). ALWAYS add a comment
    | explaining why an entry is here — the allowlist must not become a dumping ground.
    */
    'ignore' => [

        // RecurringBookingSeries declares optional alternate-name casts that the
        // application reads null-safely and never writes (it uses the real columns
        // starts_at/ends_at/days/metadata, plus template_payload added in migration
        // 100008). See App\Services\Client\Templates\ApplyRecurringTemplateService
        // and App\Console\Commands\ProcessRecurringBookings, which both guard these
        // with Schema::hasColumn / null-coalescing. Adding real columns for these
        // pure aliases would create redundant dead schema.
        RecurringBookingSeries::class => [
            'start_date',
            'end_date',
            'settings',
            'days_of_week',
        ],

        // mission_verification_codes.type is the legacy NOT NULL column; the modern
        // name is code_type (added 2026_05_14). MissionVerificationCode's
        // setCodeTypeAttribute()/setTypeAttribute() mutators keep both columns in
        // sync on every write, so `type` is always populated when the fillable
        // `code_type` is set. The NOT NULL constraint is therefore safe.
        MissionVerificationCode::class => [
            'type',
        ],

        // subscription_plans.slug is a NOT NULL UNIQUE column from the modern
        // marketplace plan schema (2026_05_04 v2 feature extensions). The legacy
        // SubscriptionPlan model (name/frequency_per_month/discount_rate/is_active)
        // is dormant — no application code or seeder creates SubscriptionPlan
        // records via this model (only ClientSubscription references it as a
        // relation, and ClientSubscription's own table is created in migration
        // 100006 for that legacy feature). Nothing inserts through the model, so
        // the NOT NULL slug cannot fail today; relaxing a UNIQUE marketplace column
        // to nullable would be a riskier change than this honest allowlist.
        // NEEDS HUMAN DECISION if the legacy subscription feature is ever revived:
        // either give the model a slug generator (booted creating hook) or drop it.
        //
        // frequency_per_month and discount_rate are the same dormant legacy model's
        // fields: nothing in the application or any seeder writes a SubscriptionPlan
        // record through this model, so adding real columns for them would only
        // create dead schema on the modern marketplace subscription_plans table.
        // Allowlisted for consistency with the dormant `slug` decision above; revisit
        // together if the legacy subscription feature is revived.
        SubscriptionPlan::class => [
            'slug',
            'frequency_per_month',
            'discount_rate',
        ],

    ],

    /*
    | Models excluded from the audit entirely (database views, unmanaged tables).
    */
    'ignore_models' => [

        // RendezVous extends Booking with $guarded = [] but points at the legacy
        // `rendez_vous` compat table, which is INTENTIONALLY a small subset of the
        // `bookings` schema. It inherits Booking's full $fillable/$casts, none of
        // which it needs on its own table: Booking::mirrorIntoLegacyRendezVousTable()
        // explicitly filters its payload to only the columns that exist on
        // `rendez_vous`. Mirroring all ~90 Booking columns onto the legacy table
        // would duplicate `bookings` and defeat the compat-mirror design, so the
        // model is excluded wholesale rather than fixed column-by-column.
        RendezVous::class,

        // Company is an orphan/legacy model: no application code, seeder or test
        // creates or queries it (the only references are the word "company" in
        // unrelated fields), and no migration ever created a `companies` table.
        // Building dead schema for an unused model would add maintenance cost for
        // no benefit. Excluded until the model is either wired up or removed.
        Company::class,

        // QualityAudit is an orphan model: referenced only by its own factory; no
        // live service/controller/test uses it and no migration creates the
        // `quality_audits` table. The modern quality workflow lives in the Quality
        // v2 module (mission_quality_reviews, inspections). Excluded until revived
        // or removed rather than creating dead schema.
        QualityAudit::class,

    ],

];
