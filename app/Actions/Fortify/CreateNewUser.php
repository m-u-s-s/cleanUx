<?php

namespace App\Actions\Fortify;

use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'role' => ['nullable', 'string', Rule::in([
                User::ROLE_CLIENT,
                User::ROLE_EMPLOYE,
                User::ROLE_ENTREPRISE,
                User::ROLE_ADMIN,
            ])],
            'tva_number' => ['required_if:role,' . User::ROLE_ENTREPRISE, 'nullable', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $normalizedRole = $input['role'] ?? User::ROLE_CLIENT;

        $organizationAccountId = null;

        if ($normalizedRole === User::ROLE_ENTREPRISE) {
            $orgName = trim((string) ($input['organization_name'] ?? $input['name'] ?? 'Entreprise'));
            $baseSlug = Str::slug($orgName) ?: 'entreprise';
            $slug = $baseSlug;
            $i = 1;
            while (OrganizationAccount::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $i++;
            }

            $organization = OrganizationAccount::create([
                'name' => $orgName,
                'legal_name' => $orgName,
                'slug' => $slug,
                'type' => 'entreprise',
                'tva_number' => $input['tva_number'] ?? null,
                'email' => $input['email'],
                'status' => 'active',
                'is_multisite' => false,
            ]);

            $organizationAccountId = $organization->id;
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => $normalizedRole,
            'tva_number' => $normalizedRole === User::ROLE_ENTREPRISE ? ($input['tva_number'] ?? null) : null,
            'organization_account_id' => $organizationAccountId,
            'is_active' => true,
            'status' => 'active',
            'locale' => app()->getLocale(),
        ]);
    }
}
