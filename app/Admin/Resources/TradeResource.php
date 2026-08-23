<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\Catalog\CatalogOrdering;

/**
 * Les métiers de la plateforme.
 *
 * @extends EloquentResource<Trade>
 */
class TradeResource extends EloquentResource
{
    public function key(): string
    {
        return 'trades';
    }

    protected function model(): string
    {
        return Trade::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Métier'],
            'code' => ['Code'],
            'base_price_cents' => ['Prix de base (cents)', Column::TYPE_NUMBER],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'sort_order' => ['Ordre', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'slug'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou code';
    }

    protected function selectFilters(): array
    {
        return [
            'pricing_unit' => ['Unité de prix', 'pricing_unit', [
                ['value' => 'hour', 'label' => 'Heure'],
                ['value' => 'm2', 'label' => 'Mètre carré'],
                ['value' => 'fixed', 'label' => 'Forfait'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'short_description' => 'Description',
            'estimated_duration_min' => 'Durée estimée (min)',
            'requires_certification' => 'Certification requise',
            'requires_insurance_proof' => 'Assurance requise',
            'taxi_rules' => 'Règles taxi (véhicule récent exigé)',
        ];
    }

    /** Les champs d'un métier. PAS LES VINGT ET UN DU WEB. */
    public function formFields(): array
    {
        return [
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:120']),
            Field::make('slug', 'Identifiant (slug)')->rules(['required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/']),
            Field::make('code', 'Code')->rules(['required', 'string', 'max:60']),
            Field::select('sector_id', 'Secteur', Sector::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Sector $s) => ['value' => (string) $s->id, 'label' => (string) $s->name])
                ->all())->rules(['nullable', 'integer', 'exists:sectors,id']),
            Field::make('short_description', 'Description courte')->rules(['nullable', 'string', 'max:500']),
            Field::make('base_price_cents', 'Prix de base (centimes)', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:100000000']),
            Field::make('sort_order', 'Ordre', Field::TYPE_NUMBER)->rules(['nullable', 'integer', 'min:0', 'max:9999']),
            // Celui-ci fait exception à la règle « le nécessaire seulement » ci-dessus, et pour une raison précise : il ne règle pas un prix, il décide de ce qu'on exige d'un prestataire avant de lui confier une mission.
            Field::make('taxi_rules', 'Règles taxi (véhicule de moins de 4 ans)', Field::TYPE_BOOL)
                ->rules(['nullable', 'boolean']),
        ];
    }

    public function actions(): array
    {
        return [

            Action::make('move-up', 'Monter dans le secteur', function (Trade $metier) {
                app(CatalogOrdering::class)->deplacer(
                    Trade::query()->where('sector_id', $metier->sector_id),
                    $metier->id,
                    -1,
                );

                return ['ok' => true];
            }),

            Action::make('move-down', 'Descendre dans le secteur', function (Trade $metier) {
                app(CatalogOrdering::class)->deplacer(
                    Trade::query()->where('sector_id', $metier->sector_id),
                    $metier->id,
                    1,
                );

                return ['ok' => true];
            }),

            // Rattacher est ce qui fait ENTRER un métier dans le parcours client : sans secteur, il n'apparaît nulle part et rien ne le signale.
            Action::make('attach-sector', 'Rattacher à un secteur', function (Trade $metier, array $valeurs) {
                $secteur = Sector::findOrFail((int) $valeurs['sector_id']);

                $metier->forceFill([
                    'sector_id' => $secteur->id,
                    'sort_order' => (int) $secteur->trades()->max('sort_order') + 1,
                ])->save();

                return ['sector_id' => $secteur->id];
            })->requires([
                Field::select('sector_id', 'Secteur', Sector::query()
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (Sector $s) => ['value' => (string) $s->id, 'label' => (string) $s->name])
                    ->all())->rules(['required', 'integer', 'exists:sectors,id']),
            ]),
            Action::make('toggle-active', 'Activer / désactiver', function (Trade $metier) {
                $metier->forceFill(['is_active' => ! $metier->is_active])->save();

                return ['is_active' => (bool) $metier->fresh()->is_active];
            }),
        ];
    }

    /** Un métier créé par la console naît ACTIF mais sans parcours. */
    public function prepareForCreate(array $data): array
    {
        return $data + ['is_active' => true, 'sort_order' => 0];
    }
}
