<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\Trade;
use App\Support\ActivityLogger;
use App\Support\TradeFormSchema;
use Illuminate\Support\Str;

/** Le formulaire d'un métier — champs, règles et persistance — en UN seul endroit. */
trait ManagesTradeForm
{
    public ?int $tradeId = null;

    public string $slug = '';

    public string $code = '';

    public string $name = '';

    public string $icon = '';

    public string $color = '#0EA5E9';

    public string $short_description = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $requires_certification = false;

    public bool $requires_insurance_proof = false;

    /** LE CONTROLE FACIAL SE DECIDE METIER PAR METIER. */
    public bool $requires_face_check = false;

    public bool $is_b2b_default = true;

    public bool $is_personal_default = true;

    public int $sort_order = 0;

    public ?string $default_hourly_rate = null;

    /** LE METIER SE FACTURE AU TEMPS PASSE. */
    public bool $hourly_billing = false;

    public string $emergency_multiplier = '1.00';

    public string $night_multiplier = '1.00';

    public string $weekend_multiplier = '1.00';

    public ?string $quote_validity_days = null;

    public bool $requires_quote_by_default = false;

    /** Règles du transport de personnes : véhicule récent, carte grise, assurance. */
    public bool $taxi_rules = false;

    public ?string $sla_response_minutes = null;

    public string $booking_form_schema_json = '';

    public bool $showFormSchemaPreview = false;

    /** Slug et code déduits du nom, à la CRÉATION seulement. */
    public function updatedName(mixed $value): void
    {
        if ($this->tradeId !== null) {
            return;
        }

        $this->slug = Str::slug((string) $value);
        $this->code = Str::upper(Str::limit(Str::slug((string) $value, '_'), 30, ''));
    }

    public function toggleFormSchemaPreview(): void
    {
        $this->showFormSchemaPreview = ! $this->showFormSchemaPreview;
    }

    public function resetTradeForm(): void
    {
        $this->tradeId = null;
        $this->slug = '';
        $this->code = '';
        $this->name = '';
        $this->icon = '';
        $this->color = '#0EA5E9';
        $this->short_description = '';
        $this->description = '';
        $this->is_active = true;
        $this->requires_certification = false;
        $this->requires_insurance_proof = false;
        $this->requires_face_check = false;
        $this->is_b2b_default = true;
        $this->is_personal_default = true;
        $this->sort_order = 0;
        $this->default_hourly_rate = null;
        $this->hourly_billing = false;
        $this->emergency_multiplier = '1.00';
        $this->night_multiplier = '1.00';
        $this->weekend_multiplier = '1.00';
        $this->quote_validity_days = null;
        $this->requires_quote_by_default = false;
        $this->taxi_rules = false;
        $this->sla_response_minutes = null;
        $this->booking_form_schema_json = '';
        $this->showFormSchemaPreview = false;

        $this->resetErrorBag();
    }

    public function fillTradeForm(Trade $trade): void
    {
        $this->tradeId = $trade->id;
        $this->slug = (string) $trade->slug;
        $this->code = (string) $trade->code;
        $this->name = (string) $trade->name;
        $this->icon = (string) ($trade->icon ?? '');
        $this->color = (string) ($trade->color ?? '#0EA5E9');
        $this->short_description = (string) ($trade->short_description ?? '');
        $this->description = (string) ($trade->description ?? '');
        $this->is_active = (bool) $trade->is_active;
        $this->requires_certification = (bool) $trade->requires_certification;
        $this->requires_insurance_proof = (bool) $trade->requires_insurance_proof;
        $this->requires_face_check = (bool) $trade->requires_face_check;
        $this->is_b2b_default = (bool) $trade->is_b2b_default;
        $this->is_personal_default = (bool) $trade->is_personal_default;
        $this->sort_order = (int) $trade->sort_order;
        $this->default_hourly_rate = $trade->default_hourly_rate !== null ? (string) $trade->default_hourly_rate : null;
        $this->hourly_billing = (bool) $trade->hourly_billing;
        $this->emergency_multiplier = (string) ($trade->emergency_multiplier ?? '1.00');
        $this->night_multiplier = (string) ($trade->night_multiplier ?? '1.00');
        $this->weekend_multiplier = (string) ($trade->weekend_multiplier ?? '1.00');
        $this->quote_validity_days = $trade->quote_validity_days !== null ? (string) $trade->quote_validity_days : null;
        $this->requires_quote_by_default = (bool) $trade->requires_quote_by_default;
        $this->taxi_rules = (bool) $trade->taxi_rules;
        $this->sla_response_minutes = $trade->sla_response_minutes !== null ? (string) $trade->sla_response_minutes : null;
        $this->booking_form_schema_json = $trade->booking_form_schema !== null
            ? (string) json_encode($trade->booking_form_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';
        $this->showFormSchemaPreview = false;
    }

    /** @return array<string, array<int, string>> */
    public function tradeFormRules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/'],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:16'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'requires_certification' => ['boolean'],
            'requires_insurance_proof' => ['boolean'],
            'requires_face_check' => ['boolean'],
            'is_b2b_default' => ['boolean'],
            'is_personal_default' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            // `required_if` : cocher « facture a l'heure » sans donner de tarif produirait un metier qui multiplie des heures par rien.
            'default_hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99', 'required_if:hourly_billing,true'],
            'hourly_billing' => ['boolean'],
            'emergency_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'night_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'weekend_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'quote_validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'requires_quote_by_default' => ['boolean'],
            'taxi_rules' => ['boolean'],
            'sla_response_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'booking_form_schema_json' => ['nullable', 'string', 'max:50000'],
        ];
    }

    /** Valide et enregistre le métier. Rend `null` si quelque chose s'y oppose. */
    protected function persistTradeForm(): ?Trade
    {
        $validated = $this->validate($this->tradeFormRules());

        // Casts explicites : sans eux les décimaux partent en base sous forme de chaînes.
        foreach (['default_hourly_rate', 'emergency_multiplier', 'night_multiplier', 'weekend_multiplier'] as $champ) {
            if ($validated[$champ] !== null && $validated[$champ] !== '') {
                $validated[$champ] = (float) $validated[$champ];
            }
        }

        foreach (['quote_validity_days', 'sla_response_minutes'] as $champ) {
            $validated[$champ] = ($validated[$champ] !== null && $validated[$champ] !== '')
                ? (int) $validated[$champ]
                : null;
        }

        $schemaBrut = trim($this->booking_form_schema_json);

        if ($schemaBrut === '') {
            $validated['booking_form_schema'] = null;
        } else {
            $decode = json_decode($schemaBrut, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('booking_form_schema_json', 'JSON invalide : '.json_last_error_msg());

                return null;
            }

            $verdict = TradeFormSchema::validate($decode);

            if (! $verdict['ok']) {
                $this->addError('booking_form_schema_json', 'Schema invalide : '.implode(' · ', $verdict['errors']));

                return null;
            }

            $validated['booking_form_schema'] = $verdict['normalized'];
        }

        unset($validated['booking_form_schema_json']);

        // L'unicité s'ignore elle-même en édition.
        if ($this->slugOuCodeDejaPris('slug', (string) $validated['slug'], 'Ce slug est déjà utilisé.')) {
            return null;
        }

        if ($this->slugOuCodeDejaPris('code', (string) $validated['code'], 'Ce code est déjà utilisé.')) {
            return null;
        }

        // `taxi_rules_since` n'est PAS posé ici.
        if ($this->tradeId !== null) {
            $trade = Trade::findOrFail($this->tradeId);
            $trade->update($validated);
            ActivityLogger::log('admin.trade.updated', $trade, ['changes' => $validated]);

            return $trade;
        }

        $trade = Trade::create($validated);
        ActivityLogger::log('admin.trade.created', $trade, ['attributes' => $validated]);

        return $trade;
    }

    private function slugOuCodeDejaPris(string $colonne, string $valeur, string $message): bool
    {
        $pris = Trade::query()
            ->where($colonne, $valeur)
            ->when($this->tradeId, fn ($q) => $q->where('id', '!=', $this->tradeId))
            ->exists();

        if ($pris) {
            $this->addError($colonne, $message);
        }

        return $pris;
    }
}
