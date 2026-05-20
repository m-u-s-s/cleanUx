<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenancyV2\TenantContext;
use App\Services\TenancyV2\TenantService;
use App\Services\TenancyV2\TenantThemingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenancyV2Controller extends Controller
{
    public function __construct(
        protected TenantService $service,
        protected TenantThemingService $theming,
        protected TenantContext $context,
    ) {}

    /* ---- Public ---- */

    public function currentTenant(): JsonResponse
    {
        $tenant = $this->context->current();
        if (! $tenant) {
            return response()->json(['ok' => true, 'tenant' => null, 'theming' => $this->theming->configFor(null)]);
        }
        return response()->json([
            'ok' => true,
            'tenant' => [
                'code' => $tenant->code,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'plan_code' => $tenant->plan_code,
                'status' => $tenant->status,
                'locale' => $tenant->default_locale,
                'currency' => $tenant->default_currency,
            ],
            'theming' => $this->theming->configFor($tenant),
        ]);
    }

    /* ---- Admin ---- */

    public function adminListTenants(Request $request): JsonResponse
    {
        $rows = Tenant::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('plan_code'), fn ($q) => $q->where('plan_code', $request->string('plan_code')))
            ->orderByDesc('created_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();
        return response()->json(['data' => $rows]);
    }

    public function adminCreateTenant(Request $request): JsonResponse
    {
        $allowedPlans = (array) config('tenancy_v2.allowed_plans', []);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'plan_code' => ['nullable', 'string', 'in:' . implode(',', $allowedPlans)],
            'primary_domain' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['nullable', 'email'],
            'billing_owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'default_locale' => ['nullable', 'string', 'size:2'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'default_country_code' => ['nullable', 'string', 'size:2'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        try {
            $tenant = $this->service->create($data);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'tenant' => $tenant], 201);
    }

    public function adminSuspendTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        try {
            $row = $this->service->suspend($tenant, $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'tenant' => $row]);
    }

    public function adminActivateTenant(Tenant $tenant): JsonResponse
    {
        return response()->json(['ok' => true, 'tenant' => $this->service->activate($tenant)]);
    }

    public function adminArchiveTenant(Tenant $tenant): JsonResponse
    {
        return response()->json(['ok' => true, 'tenant' => $this->service->archive($tenant)]);
    }

    public function adminChangePlan(Request $request, Tenant $tenant): JsonResponse
    {
        $allowedPlans = (array) config('tenancy_v2.allowed_plans', []);
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'in:' . implode(',', $allowedPlans)],
        ]);
        try {
            $row = $this->service->changePlan($tenant, $data['plan_code']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'tenant' => $row]);
    }

    public function adminUpdateTheming(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'logo_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:24'],
            'secondary_color' => ['nullable', 'string', 'max:24'],
            'accent_color' => ['nullable', 'string', 'max:24'],
            'font_family' => ['nullable', 'string', 'max:191'],
            'app_name' => ['nullable', 'string', 'max:191'],
            'support_email' => ['nullable', 'email'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
        ]);
        return response()->json([
            'ok' => true,
            'tenant' => $this->theming->updateTheming($tenant, $data),
        ]);
    }

    public function adminListDomains(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $tenant->domains()->orderByDesc('is_primary')->get(),
        ]);
    }

    public function adminAddDomain(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:191', 'unique:tenant_domains,domain'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => strtolower($data['domain']),
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_verified' => false,
            'ssl_status' => TenantDomain::SSL_PENDING,
        ]);
        return response()->json(['ok' => true, 'domain' => $domain], 201);
    }

    public function adminVerifyDomain(TenantDomain $domain): JsonResponse
    {
        $domain->update([
            'is_verified' => true,
            'verified_at' => now(),
            'ssl_status' => TenantDomain::SSL_READY,
        ]);
        return response()->json(['ok' => true, 'domain' => $domain->fresh()]);
    }

    public function adminAttachUser(Request $request, Tenant $tenant): JsonResponse
    {
        $allowedRoles = (array) config('tenancy_v2.tenant_user_roles', []);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:' . implode(',', $allowedRoles)],
        ]);
        $user = User::query()->findOrFail($data['user_id']);
        try {
            $row = $this->service->attachUser($tenant, $user, $data['role']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }
        return response()->json(['ok' => true, 'tenant_user' => $row]);
    }

    public function adminListUsers(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $tenant->users()->with('user:id,email,name')->get(),
        ]);
    }
}
