<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Sector;
use App\Models\Trade;

/**
 * Les métiers de la plateforme.
 *
 * La GRILLE TARIFAIRE par zone et le questionnaire de commande ne s'éditent pas ici : ce sont
 * deux dimensions et un arbre, que le rendu générique ne sait pas montrer sans mentir sur leur
 * structure. Ils restent sur leurs pages dédiées.
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
        ];
    }

    /**
     * Les champs d'un métier.
     *
     * PAS LES VINGT ET UN DU WEB. La console sert une console : le nécessaire pour créer un métier
     * et le corriger en déplacement. Les réglages fins — multiplicateurs urgence/nuit/week-end,
     * schéma de questionnaire, certifications — restent sur le web, où l'écran montre à quoi ils
     * servent. Ils gardent leur valeur : le moteur n'écrit QUE les champs déclarés.
     */
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
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('toggle-active', 'Activer / désactiver', function (Trade $metier) {
                $metier->forceFill(['is_active' => ! $metier->is_active])->save();

                return ['is_active' => (bool) $metier->fresh()->is_active];
            }),
        ];
    }

    /**
     * Un métier créé par la console naît ACTIF mais sans parcours.
     *
     * `is_active` dit « ce métier existe », pas « il est commandable » : c'est son OUVERTURE par
     * zone qui décide de cela, et son parcours de questions qui décide s'il est publiable.
     */
    public function prepareForCreate(array $data): array
    {
        return $data + ['is_active' => true, 'sort_order' => 0];
    }
}
