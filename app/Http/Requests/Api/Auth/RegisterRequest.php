<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', 'string', 'in:fr,nl,en'],
            'accept_terms' => ['required', 'accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'referral_code' => ['nullable', 'string', 'max:64'],
            // Quelle app inscrit : l'app cliente n'envoie rien et reste sur `client`. L'app
            // prestataire demande `provider`, ce qui crée un compte en attente d'approbation —
            // sans quoi un prestataire s'inscrivant depuis son app obtenait un compte client,
            // que le garde `role:employe` enfermait hors de tout, onboarding compris.
            'account_type' => ['nullable', 'string', 'in:client,provider'],
            // Indépendant ou société : deux inscriptions distinctes dans l'app prestataire. Une
            // société n'est pas un drapeau sur le compte, elle donne lieu à un OrganizationAccount
            // `provider_company` dont le signataire est `owner` — c'est ce que consomment déjà
            // l'espace web provider-company et le rattachement des missions.
            'provider_kind' => ['nullable', 'string', 'in:independent,company'],
            'company_name' => ['nullable', 'required_if:provider_kind,company', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            // Métier visé et réponses aux questions propres à ce métier
            // (trades.provider_form_schema). Sans métier déclaré, le matching n'a rien sur quoi
            // travailler : le prestataire ne recevrait jamais la moindre mission.
            'trade_id' => ['nullable', 'integer', 'exists:trades,id'],
            'trade_answers' => ['nullable', 'array'],
        ];
    }
}
