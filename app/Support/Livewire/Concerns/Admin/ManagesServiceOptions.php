<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ServiceCatalog;
use App\Models\ServiceOption;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Gère le CRUD des ServiceOption (variantes paramétrables d'un service).
 *
 * Doit être branché dans un composant Livewire qui expose $selectedServiceId.
 * Le composant doit appeler $this->loadOptionsForService($id) après chaque
 * selectService() (cf. trait alias dans CatalogueServices).
 */
trait ManagesServiceOptions
{
    /** Liste des options du service sélectionné, keyé par option id. */
    public array $serviceOptions = [];

    /** Formulaire de création d'une nouvelle option. */
    public array $newOption = [];

    /** ID de l'option en cours d'édition inline (null = aucun). */
    public ?int $editingOptionId = null;

    protected function loadOptionsForService(?int $serviceId): void
    {
        $this->resetNewOption();
        $this->editingOptionId = null;

        if (! $serviceId) {
            $this->serviceOptions = [];
            return;
        }

        $this->serviceOptions = ServiceOption::query()
            ->where('service_catalog_id', $serviceId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->mapWithKeys(fn (ServiceOption $o) => [$o->id => $this->serializeOption($o)])
            ->toArray();
    }

    public function resetNewOption(): void
    {
        $this->newOption = [
            'slug'                  => '',
            'label'                 => '',
            'help_text'             => '',
            'type'                  => 'number',
            'values_text'           => '',
            'unit'                  => '',
            'is_required'           => false,
            'price_modifier'        => 'none',
            'price_modifier_value'  => '0',
            'min_value'             => '',
            'max_value'             => '',
            'step'                  => '',
            'sort_order'            => 0,
            'is_active'             => true,
        ];
    }

    protected function serializeOption(ServiceOption $o): array
    {
        return [
            'slug'                 => (string) $o->slug,
            'label'                => (string) $o->label,
            'help_text'            => (string) ($o->help_text ?? ''),
            'type'                 => (string) $o->type,
            'values_text'          => is_array($o->values) ? implode("\n", $o->values) : '',
            'unit'                 => (string) ($o->unit ?? ''),
            'is_required'          => (bool) $o->is_required,
            'price_modifier'       => (string) $o->price_modifier,
            'price_modifier_value' => (string) $o->price_modifier_value,
            'min_value'            => $o->min_value !== null ? (string) $o->min_value : '',
            'max_value'            => $o->max_value !== null ? (string) $o->max_value : '',
            'step'                 => $o->step !== null ? (string) $o->step : '',
            'sort_order'           => (int) $o->sort_order,
            'is_active'            => (bool) $o->is_active,
        ];
    }

    protected function authorizeServiceOptionManagement(): void
    {
        Gate::authorize('manage-services');
        Gate::authorize('perform-critical-admin-actions');
    }

    /**
     * Normalise les valeurs (textarea → array JSON) pour les types select/multiselect.
     */
    protected function normalizeOptionPayload(array $payload): array
    {
        $type = $payload['type'] ?? 'number';
        $values = null;

        if (in_array($type, ['select', 'multiselect'], true)) {
            $raw = (string) ($payload['values_text'] ?? '');
            $values = collect(preg_split('/\r?\n/', $raw))
                ->map(fn ($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        }

        return [
            'slug'                 => Str::slug((string) ($payload['slug'] ?? ''), '_'),
            'label'                => (string) ($payload['label'] ?? ''),
            'help_text'            => filled($payload['help_text'] ?? null) ? $payload['help_text'] : null,
            'type'                 => $type,
            'values'               => $values,
            'unit'                 => filled($payload['unit'] ?? null) ? $payload['unit'] : null,
            'is_required'          => (bool) ($payload['is_required'] ?? false),
            'price_modifier'       => $payload['price_modifier'] ?? 'none',
            'price_modifier_value' => (float) ($payload['price_modifier_value'] ?? 0),
            'min_value'            => filled($payload['min_value'] ?? null) ? (float) $payload['min_value'] : null,
            'max_value'            => filled($payload['max_value'] ?? null) ? (float) $payload['max_value'] : null,
            'step'                 => filled($payload['step'] ?? null) ? (float) $payload['step'] : null,
            'sort_order'           => (int) ($payload['sort_order'] ?? 0),
            'is_active'            => (bool) ($payload['is_active'] ?? true),
        ];
    }

    protected function validateOptionPayload(string $prefix, ?int $ignoreId = null): array
    {
        $rules = [
            "$prefix.slug"                 => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            "$prefix.label"                => ['required', 'string', 'max:160'],
            "$prefix.help_text"            => ['nullable', 'string', 'max:2000'],
            "$prefix.type"                 => ['required', 'in:'.implode(',', ServiceOption::TYPES)],
            "$prefix.values_text"          => ['nullable', 'string'],
            "$prefix.unit"                 => ['nullable', 'string', 'max:20'],
            "$prefix.is_required"          => ['boolean'],
            "$prefix.price_modifier"       => ['required', 'in:'.implode(',', ServiceOption::PRICE_MODIFIERS)],
            "$prefix.price_modifier_value" => ['nullable', 'numeric'],
            "$prefix.min_value"            => ['nullable', 'numeric'],
            "$prefix.max_value"            => ['nullable', 'numeric'],
            "$prefix.step"                 => ['nullable', 'numeric', 'min:0'],
            "$prefix.sort_order"           => ['nullable', 'integer', 'min:0'],
            "$prefix.is_active"            => ['boolean'],
        ];

        return $this->validate($rules);
    }

    public function addOption(): void
    {
        $this->authorizeServiceOptionManagement();
        abort_unless((bool) $this->selectedServiceId, 422, 'Aucun service sélectionné.');

        $this->validateOptionPayload('newOption');

        $service = ServiceCatalog::findOrFail($this->selectedServiceId);
        $payload = $this->normalizeOptionPayload($this->newOption);

        // Unicité slug par service
        if (ServiceOption::where('service_catalog_id', $service->id)
            ->where('slug', $payload['slug'])->exists()) {
            $this->addError('newOption.slug', 'Ce slug est déjà utilisé pour ce service.');
            return;
        }

        $option = ServiceOption::create(array_merge($payload, [
            'service_catalog_id' => $service->id,
        ]));

        ActivityLogger::log('service_option.created', $option, [
            'service_catalog_id' => $service->id,
            'slug'               => $option->slug,
            'type'               => $option->type,
        ]);

        $this->loadOptionsForService($service->id);
        session()->flash('success', 'Option ajoutée.');
    }

    public function editOption(int $optionId): void
    {
        $option = ServiceOption::where('service_catalog_id', $this->selectedServiceId)
            ->findOrFail($optionId);

        $this->editingOptionId = $option->id;
        $this->serviceOptions[$option->id] = $this->serializeOption($option);
    }

    public function cancelEditOption(): void
    {
        if ($this->editingOptionId) {
            $option = ServiceOption::find($this->editingOptionId);
            if ($option) {
                $this->serviceOptions[$option->id] = $this->serializeOption($option);
            }
        }
        $this->editingOptionId = null;
    }

    public function saveOption(int $optionId): void
    {
        $this->authorizeServiceOptionManagement();

        $option = ServiceOption::where('service_catalog_id', $this->selectedServiceId)
            ->findOrFail($optionId);

        $this->validateOptionPayload("serviceOptions.$optionId");

        $payload = $this->normalizeOptionPayload($this->serviceOptions[$optionId] ?? []);

        $duplicate = ServiceOption::where('service_catalog_id', $option->service_catalog_id)
            ->where('slug', $payload['slug'])
            ->where('id', '!=', $option->id)
            ->exists();
        if ($duplicate) {
            $this->addError("serviceOptions.$optionId.slug", 'Ce slug est déjà utilisé pour ce service.');
            return;
        }

        $before = Arr::only($option->toArray(), array_keys($payload));
        $option->update($payload);

        ActivityLogger::log('service_option.updated', $option, [
            'service_catalog_id' => $option->service_catalog_id,
            'slug'               => $option->slug,
            'before'             => $before,
            'after'              => Arr::only($option->fresh()->toArray(), array_keys($payload)),
        ]);

        $this->editingOptionId = null;
        $this->loadOptionsForService($option->service_catalog_id);
        session()->flash('success', 'Option mise à jour.');
    }

    public function toggleOptionActive(int $optionId): void
    {
        $this->authorizeServiceOptionManagement();

        $option = ServiceOption::where('service_catalog_id', $this->selectedServiceId)
            ->findOrFail($optionId);

        $option->update(['is_active' => ! $option->is_active]);

        ActivityLogger::log('service_option.toggled', $option, [
            'service_catalog_id' => $option->service_catalog_id,
            'is_active'          => $option->is_active,
        ]);

        $this->loadOptionsForService($option->service_catalog_id);
        session()->flash('success', 'Statut de l\'option mis à jour.');
    }

    public function deleteOption(int $optionId): void
    {
        $this->authorizeServiceOptionManagement();

        $option = ServiceOption::where('service_catalog_id', $this->selectedServiceId)
            ->findOrFail($optionId);

        $context = [
            'service_catalog_id' => $option->service_catalog_id,
            'slug'               => $option->slug,
        ];

        $option->delete();

        ActivityLogger::log('service_option.deleted', new ServiceOption(['id' => null]), $context);

        $this->loadOptionsForService($context['service_catalog_id']);
        session()->flash('success', 'Option supprimée.');
    }
}
