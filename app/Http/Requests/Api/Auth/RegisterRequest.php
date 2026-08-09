<?php

namespace App\Http\Requests\Api\Auth;

use App\Models\Trade;
use App\Rules\ValidBusinessNumber;
use App\Support\Validation\TradeFormSchema;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Jeton rendu par POST /api/auth/phone/verify-confirm. C'est lui — et non un booléen
            // envoyé par le client — qui autorise à marquer le téléphone comme vérifié sur le
            // compte créé. Il est à usage unique côté service.
            'phone_verification_token' => ['nullable', 'string', 'max:512'],
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
            // Symétrique côté client : un particulier ou une société qui commande des services.
            // Une société cliente donne lieu à un OrganizationAccount `client_company` — c'est ce
            // que consomment le multi-sites, les contrats B2B et la facturation centralisée.
            'client_kind' => ['nullable', 'string', 'in:individual,company'],
            'company_name' => [
                'nullable',
                'required_if:provider_kind,company',
                'required_if:client_kind,company',
                'string',
                'max:255',
            ],
            // Ce numéro n'est pas décoratif : la vérification KYB le soumettra à l'INSEE et à
            // VIES. Accepté sans contrôle, il n'échouait qu'à la revue du dossier, plusieurs
            // jours plus tard — la clé est donc vérifiée dès la saisie.
            'vat_number' => ['nullable', 'string', 'max:32', new ValidBusinessNumber],
            // Métier visé et réponses aux questions propres à ce métier
            // (trades.provider_form_schema). Sans métier déclaré, le matching n'a rien sur quoi
            // travailler : le prestataire ne recevrait jamais la moindre mission.
            'trade_id' => ['nullable', 'integer', 'exists:trades,id'],
            'trade_answers' => ['nullable', 'array'],
            /*
             * MÉTIERS ET ZONES — les deux tables que lit la requête candidate du dispatch.
             *
             * `trade_id` seul ne dit QUE ce que le prestataire fait, jamais où : le dispatch devait
             * alors deviner son périmètre à partir de sa position du jour. Ces deux listes viennent
             * du catalogue (`GET /api/catalog/registration-options`) et sont revalidées à
             * l'écriture — l'application peut envoyer n'importe quoi.
             */
            'trade_ids' => ['nullable', 'array'],
            'trade_ids.*' => ['integer'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer'],
        ], TradeFormSchema::rulesFor($this->declaredTradeSchema()));
    }

    /**
     * Les libellés du schéma nomment les erreurs comme la question est posée à l'écran.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return TradeFormSchema::attributesFor($this->declaredTradeSchema());
    }

    /**
     * Schéma du métier déclaré, ou null si aucun métier valide n'est demandé — auquel cas aucune
     * règle par question n'est ajoutée et `trade_answers` reste un tableau libre.
     *
     * @return array<string, mixed>|null
     */
    private function declaredTradeSchema(): ?array
    {
        $tradeId = $this->input('trade_id');

        if (! is_numeric($tradeId)) {
            return null;
        }

        $schema = Trade::query()->whereKey((int) $tradeId)->value('provider_form_schema');

        return is_array($schema) ? $schema : null;
    }
}
