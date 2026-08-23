<?php

namespace App\Services\Gdpr;

use App\Models\GdprDataRequest;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\FaceImageStore;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Droit à l'oubli (art. 17 RGPD). */
class DataErasureService
{
    public function schedule(User $user, ?string $reason = null, ?array $context = null): GdprDataRequest
    {
        $gracePeriodDays = (int) config('gdpr.erasure_grace_period_days', 30);

        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => $this->generateReference(),
            'reason' => $reason,
            'requested_at' => now(),
            'confirmed_at' => now(),
            'grace_period_ends_at' => now()->addDays($gracePeriodDays),
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'metadata' => $context['metadata'] ?? null,
        ]);

        $user->forceFill([
            'deletion_scheduled_at' => $request->grace_period_ends_at,
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.erasure_scheduled', $request, [
            'user_id' => $user->id,
            'grace_period_ends_at' => $request->grace_period_ends_at?->toIso8601String(),
        ]);

        return $request;
    }

    public function cancel(GdprDataRequest $request, ?User $actor = null, ?string $reason = null): GdprDataRequest
    {
        if (! in_array($request->status, [
            GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            GdprDataRequest::STATUS_AWAITING_CONFIRMATION,
        ], true)) {
            return $request;
        }

        $request->update([
            'status' => GdprDataRequest::STATUS_CANCELLED,
            'admin_response' => $reason,
            'processed_by_user_id' => $actor?->id,
        ]);

        if ($request->user) {
            $request->user->forceFill(['deletion_scheduled_at' => null])->save();
        }

        ActivityLogger::log('gdpr.erasure_cancelled', $request, [
            'user_id' => $request->user_id,
            'actor_user_id' => $actor?->id,
        ]);

        return $request->fresh();
    }

    public function execute(GdprDataRequest $request): GdprDataRequest
    {
        if (! $request->isReadyForExecution()) {
            throw new \RuntimeException('Erasure request not ready for execution (grace period not over or wrong status).');
        }

        return DB::transaction(function () use ($request) {
            $this->anonymizeUser($request->user);

            $request->update([
                'status' => GdprDataRequest::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);

            ActivityLogger::log('gdpr.erasure_executed', $request, [
                'user_id' => $request->user_id,
                'reference' => $request->reference,
            ]);

            return $request->fresh();
        });
    }

    public function anonymizeUser(User $user): void
    {
        $anonEmail = str_replace('{id}', (string) $user->id, (string) config('gdpr.anonymized_email_template', 'deleted_{id}@anonymized.brio'));
        $anonName = (string) config('gdpr.anonymized_name', 'Utilisateur supprimé');

        $user->forceFill([
            'name' => $anonName,
            'email' => $anonEmail,
            'phone' => null,
            'tva_number' => null,
            'profile_photo_path' => null,
            'metadata' => null,
            'status' => 'deleted',
            'is_active' => false,
            'anonymized_at' => now(),
            'last_gdpr_action_at' => now(),
        ])->save();

        // Anonymiser les profils liés
        if ($user->customerProfile) {
            $user->customerProfile->forceFill([
                'plan_status' => 'deleted',
            ])->save();
        }

        if ($user->providerProfile) {
            $user->providerProfile->forceFill([
                'status' => 'inactive',
                'bio' => null,
                'photo_path' => null,
            ])->save();
        }

        // Supprimer les tokens API actifs pour révoquer l'accès immédiatement
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        $this->eraseBiometrics($user);
        $this->anonymizeCorePii($user);
        $this->anonymizeV2Modules($user);
    }

    /** LA BIOMETRIE SE SUPPRIME, ELLE NE S'ANONYMISE PAS. */
    protected function eraseBiometrics(User $user): void
    {
        if (! Schema::hasTable('provider_face_profiles')) {
            return;
        }

        try {
            $magasin = app(FaceImageStore::class);

            ProviderFaceCheck::query()
                ->where('user_id', $user->id)
                ->whereNotNull('selfie_path')
                ->each(function ($controle) use ($magasin) {
                    $magasin->forget($controle->selfie_path);
                    $controle->forceFill(['selfie_path' => null, 'selfie_purged_at' => now()])->save();
                });

            $profil = ProviderFaceProfile::query()->where('user_id', $user->id)->first();

            if ($profil !== null) {
                $magasin->forget($profil->reference_path);

                $profil->forceFill([
                    'status' => ProviderFaceProfile::STATUS_REVOKED,
                    'reference_path' => null,
                    'reference_hash' => null,
                    'external_face_id' => null,
                    'consent_withdrawn_at' => now(),
                    'metadata' => null,
                ])->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Anonymise the core (Phase 1–14) PII that the original anonymizeUser() missed (D2): booking postal addresses + notes, free-text feedback / complaint cases, notification payloads, analytics behavioural rows, and the readable email/name labels persisted in audit_events. */
    protected function anonymizeCorePii(User $user): void
    {
        $anonName = (string) config('gdpr.anonymized_name', 'Utilisateur supprimé');
        $uid = $user->id;

        // BOOKINGS — L'ADRESSE DU CLIENT, SES CONTACTS ET SES CONSIGNES.
        if (Schema::hasTable('bookings')) {
            $bookingCols = collect([
                // Adresse d'intervention — les deux côtés de chaque paire.
                'adresse', 'address',
                'ville', 'city',
                'code_postal', 'postal_code',
                'address_components',
                // Coordonnées : elles désignent le domicile au mètre près.
                'destination_lat', 'destination_lng',
                // Point de dépose d'une course.
                'dropoff_address', 'dropoff_postal_code', 'dropoff_lat', 'dropoff_lng',
                // Texte libre laissé par le client — c'est là que vivent les codes d'accès.
                'commentaire_client', 'customer_comment',
                'client_absent_instructions', 'live_access_note',
                // Contacts.
                'telephone_client', 'contact_phone', 'contact_email', 'contact_name',
                'backup_contact_name', 'backup_contact_phone',
                // Le bénéficiaire est un TIERS : ses données personnelles partent avec.
                'beneficiary_name', 'beneficiary_phone', 'beneficiary_note',
            ])
                ->filter(fn ($c) => Schema::hasColumn('bookings', $c))
                ->mapWithKeys(fn ($c) => [$c => null])
                ->all();
            if ($bookingCols) {
                DB::table('bookings')
                    ->where(function ($q) use ($uid) {
                        $q->where('client_id', $uid);
                        if (Schema::hasColumn('bookings', 'customer_user_id')) {
                            $q->orWhere('customer_user_id', $uid);
                        }
                    })
                    ->update($bookingCols);
            }
        }

        // Feedback — free text authored by or about the user.
        if (Schema::hasTable('feedback')) {
            $textCols = collect(['commentaire', 'comment', 'provider_response'])
                ->filter(fn ($c) => Schema::hasColumn('feedback', $c))
                ->mapWithKeys(fn ($c) => [$c => null])
                ->all();
            if ($textCols) {
                DB::table('feedback')
                    ->where(function ($q) use ($uid) {
                        $q->where('client_id', $uid);
                        if (Schema::hasColumn('feedback', 'employe_id')) {
                            $q->orWhere('employe_id', $uid);
                        }
                        if (Schema::hasColumn('feedback', 'provider_user_id')) {
                            $q->orWhere('provider_user_id', $uid);
                        }
                    })
                    ->update($textCols);
            }
        }

        // Complaint cases — subject/description free text.
        if (Schema::hasTable('complaint_cases')) {
            $textCols = collect(['subject', 'description'])
                ->filter(fn ($c) => Schema::hasColumn('complaint_cases', $c))
                ->mapWithKeys(fn ($c) => [$c => '[anonymisé]'])
                ->all();
            if ($textCols) {
                DB::table('complaint_cases')
                    ->where(function ($q) use ($uid) {
                        $q->where('client_id', $uid);
                        if (Schema::hasColumn('complaint_cases', 'provider_user_id')) {
                            $q->orWhere('provider_user_id', $uid);
                        }
                        if (Schema::hasColumn('complaint_cases', 'user_id')) {
                            $q->orWhere('user_id', $uid);
                        }
                    })
                    ->update($textCols);
            }
        }

        // Notifications — the serialized payload can embed name/email/address.
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $uid)
                ->update(['data' => json_encode(['anonymized' => true])]);
        }

        // Analytics — detach behavioural events/sessions from the user id.
        foreach (['analytics_events', 'analytics_sessions'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $uid)->update(['user_id' => null]);
            }
        }

        // Audit events — scrub the human-readable email/name labels while keeping the row +
        // numeric ids for traceability. Filter by type so a colliding id of another entity
        // (e.g. a booking with the same numeric id as a subject) is never touched.
        if (Schema::hasTable('audit_events')) {
            if (Schema::hasColumn('audit_events', 'actor_label')) {
                DB::table('audit_events')
                    ->where('actor_id', $uid)
                    ->where('actor_type', 'user')
                    ->update(['actor_label' => $anonName]);
            }
            if (Schema::hasColumn('audit_events', 'subject_label')) {
                DB::table('audit_events')
                    ->where('subject_id', $uid)
                    ->where('subject_type', 'User')
                    ->update(['subject_label' => $anonName]);
            }
        }
    }

    /** Anonymisation des nouveaux modules v2 (KYB/Fleet/Subscriptions/Tenancy/Chat). */
    protected function anonymizeV2Modules(User $user): void
    {
        // KYB B2B : nullify owner/contact + email contact (rows gardés pour audit)
        if (Schema::hasTable('business_entities')) {
            DB::table('business_entities')
                ->where(function ($q) use ($user) {
                    $q->where('owner_user_id', $user->id)
                        ->orWhere('contact_user_id', $user->id)
                        ->orWhere('verified_by_user_id', $user->id);
                })
                ->update([
                    'owner_user_id' => null,
                    'contact_user_id' => null,
                    'contact_email' => null,
                ]);
        }
        // Fleet : libérer les véhicules/équipements + nullify dans assignments
        if (Schema::hasTable('fleet_vehicles')) {
            DB::table('fleet_vehicles')
                ->where('current_provider_id', $user->id)
                ->update(['current_provider_id' => null]);
        }
        if (Schema::hasTable('fleet_equipment')) {
            DB::table('fleet_equipment')
                ->where('current_provider_id', $user->id)
                ->update(['current_provider_id' => null]);
        }
        // Subscriptions : cancel actives + nullify provider link (rows conservées pour compta)
        if (Schema::hasTable('subscriptions_v2')) {
            DB::table('subscriptions_v2')
                ->where('user_id', $user->id)
                ->whereIn('status', ['trialing', 'active', 'paused', 'past_due'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => now(),
                ]);
            DB::table('subscriptions_v2')
                ->where('provider_user_id', $user->id)
                ->update(['provider_user_id' => null]);
        }
        // Chat : retirer participant (set left_at + can_send=false) + anonymiser sender_user_id
        if (Schema::hasTable('chat_participants')) {
            DB::table('chat_participants')
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->update([
                    'left_at' => now(),
                    'can_send' => false,
                ]);
        }
        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')
                ->where('sender_user_id', $user->id)
                ->update(['sender_user_id' => null]);
        }
        // Contracts : conserver les signatures (preuve légale) mais nullify signer_user_id
        if (Schema::hasTable('contract_signatures')) {
            DB::table('contract_signatures')
                ->where('signer_user_id', $user->id)
                ->update(['signer_user_id' => null]);
        }
    }

    public function restrictProcessing(User $user, ?string $reason = null): GdprDataRequest
    {
        $request = GdprDataRequest::create([
            'user_id' => $user->id,
            'type' => GdprDataRequest::TYPE_RESTRICTION,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => $this->generateReference(),
            'reason' => $reason,
            'requested_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $user->forceFill([
            'processing_restricted_at' => now(),
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.processing_restricted', $request, [
            'user_id' => $user->id,
        ]);

        return $request;
    }

    public function liftRestriction(User $user, ?User $actor = null): void
    {
        $user->forceFill([
            'processing_restricted_at' => null,
            'last_gdpr_action_at' => now(),
        ])->save();

        ActivityLogger::log('gdpr.processing_unrestricted', $user, [
            'actor_user_id' => $actor?->id,
        ]);
    }

    protected function generateReference(): string
    {
        $prefix = (string) config('gdpr.reference_prefix', 'GDPR');
        do {
            $candidate = $prefix.'-'.strtoupper(Str::random(10));
        } while (GdprDataRequest::where('reference', $candidate)->exists());

        return $candidate;
    }
}
