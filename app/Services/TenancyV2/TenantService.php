<?php

namespace App\Services\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantService
{
    public function create(array $payload): Tenant
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Le nom est requis.']]);
        }
        $plan = (string) ($payload['plan_code'] ?? config('tenancy_v2.default_plan', 'basic'));
        if (! in_array($plan, (array) config('tenancy_v2.allowed_plans', []), true)) {
            throw ValidationException::withMessages(['plan_code' => ['Plan invalide.']]);
        }
        $slug = $payload['slug'] ?? Str::slug($name);
        $code = $payload['code'] ?? Str::slug($name);

        if (Tenant::query()->where('code', $code)->orWhere('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'code' => ["Tenant {$code} existe déjà."],
            ]);
        }

        $trialDays = (int) ($payload['trial_days'] ?? config('tenancy_v2.trial_days_default', 0));
        $now = now();
        $status = $trialDays > 0 ? Tenant::STATUS_TRIAL : Tenant::STATUS_ACTIVE;

        return DB::transaction(function () use ($payload, $name, $slug, $code, $plan, $status, $trialDays, $now) {
            $tenant = Tenant::query()->create([
                'code' => $code,
                'slug' => $slug,
                'name' => $name,
                'plan_code' => $plan,
                'status' => $status,
                'primary_domain' => $payload['primary_domain'] ?? null,
                'contact_email' => $payload['contact_email'] ?? null,
                'billing_owner_user_id' => $payload['billing_owner_user_id'] ?? null,
                'default_locale' => $payload['default_locale'] ?? 'fr',
                'default_currency' => $payload['default_currency'] ?? 'EUR',
                'default_country_code' => $payload['default_country_code'] ?? 'BE',
                'settings' => $payload['settings'] ?? null,
                'theming' => $payload['theming'] ?? null,
                'features' => $payload['features'] ?? null,
                'trial_ends_at' => $trialDays > 0 ? $now->copy()->addDays($trialDays) : null,
                'activated_at' => $status === Tenant::STATUS_ACTIVE ? $now : null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            // Auto-add primary domain row
            if (! empty($payload['primary_domain'])) {
                TenantDomain::query()->create([
                    'tenant_id' => $tenant->id,
                    'domain' => strtolower($payload['primary_domain']),
                    'is_primary' => true,
                    'is_verified' => false,
                ]);
            }

            // Auto-add billing owner as tenant user (owner)
            if (! empty($payload['billing_owner_user_id'])) {
                TenantUser::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => (int) $payload['billing_owner_user_id']],
                    [
                        'role' => TenantUser::ROLE_OWNER,
                        'joined_at' => $now,
                        'is_active' => true,
                    ],
                );
            }

            return $tenant;
        });
    }

    public function activate(Tenant $tenant): Tenant
    {
        $tenant->update([
            'status' => Tenant::STATUS_ACTIVE,
            'activated_at' => $tenant->activated_at ?? now(),
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);
        return $tenant->fresh();
    }

    public function suspend(Tenant $tenant, string $reason): Tenant
    {
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => ['Raison minimum 5 caractères.']]);
        }
        $tenant->update([
            'status' => Tenant::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ]);
        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'tenant.suspended',
            context: ['reason' => $reason, 'tenant_code' => $tenant->code],
            subject: $tenant,
            severity: 'warning',
        );
        return $tenant->fresh();
    }

    public function archive(Tenant $tenant): Tenant
    {
        $tenant->update([
            'status' => Tenant::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
        \App\Support\Audit\CriticalActionAuditor::record(
            eventType: 'tenant.archived',
            context: ['tenant_code' => $tenant->code],
            subject: $tenant,
            severity: 'warning',
        );
        return $tenant->fresh();
    }

    public function changePlan(Tenant $tenant, string $newPlan): Tenant
    {
        if (! in_array($newPlan, (array) config('tenancy_v2.allowed_plans', []), true)) {
            throw ValidationException::withMessages(['plan_code' => ['Plan invalide.']]);
        }
        $tenant->update([
            'plan_code' => $newPlan,
            'metadata' => array_merge((array) $tenant->metadata, [
                'last_plan_change_at' => now()->toIso8601String(),
                'previous_plan' => $tenant->plan_code,
            ]),
        ]);
        return $tenant->fresh();
    }

    public function attachUser(Tenant $tenant, User $user, string $role = TenantUser::ROLE_MEMBER): TenantUser
    {
        if (! in_array($role, (array) config('tenancy_v2.tenant_user_roles', []), true)) {
            throw ValidationException::withMessages(['role' => ['Rôle invalide.']]);
        }
        return TenantUser::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'joined_at' => now(),
                'left_at' => null,
                'is_active' => true,
            ],
        );
    }

    public function detachUser(Tenant $tenant, User $user): void
    {
        TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['is_active' => false, 'left_at' => now()]);
    }
}
