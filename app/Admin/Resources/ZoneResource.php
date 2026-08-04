<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Services\Catalog\GeoGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Les zones de service.
 *
 * Le périmètre postal et les règles de couverture ne s'éditent pas ici : ils engagent le
 * matching et la tarification, et se modifient depuis la page web qui montre leurs conséquences.
 *
 * @extends EloquentResource<ServiceZone>
 */
class ZoneResource extends EloquentResource
{
    public function key(): string
    {
        return 'zones';
    }

    protected function model(): string
    {
        return ServiceZone::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Zone'],
            'code' => ['Code'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'is_bookable' => ['Réservable', Column::TYPE_BOOL],
            'priority' => ['Priorité', Column::TYPE_NUMBER],
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
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]],

            /*
             * Le cloisonnement par pays, servi au mobile.
             *
             * Il DOIT vivre ici plutôt que côté client : un filtre appliqué à l'affichage laisse
             * passer les actions, et l'écran des zones belges montrerait Paris dès qu'un second
             * marché ouvrirait. Les options sont calculées, faute de quoi il faudrait rééditer ce
             * fichier à chaque pays ajouté.
             */
            'country_id' => ['Pays', 'country_id', Country::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Country $pays) => ['value' => (string) $pays->id, 'label' => (string) $pays->name])
                ->all()],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'coverage_type' => 'Type de couverture',
            'minimum_notice_hours' => 'Préavis minimal (h)',
            'maximum_daily_jobs' => 'Missions max/jour',
            'notes' => 'Notes',
        ];
    }

    /**
     * Les champs d'une zone.
     *
     * `country_id` EST dans le formulaire : une zone doit appartenir à un pays dès sa création, et
     * l'écran mobile le pré-remplit depuis le contexte de la descente.
     */
    public function formFields(): array
    {
        return [
            Field::select('country_id', 'Pays', Country::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Country $pays) => ['value' => (string) $pays->id, 'label' => (string) $pays->name])
                ->all())->rules(['required', 'integer', 'exists:countries,id']),
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('code', 'Code')->rules(['required', 'string', 'max:32']),
            Field::select('status', 'Statut', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'paused', 'label' => 'En pause'],
                ['value' => 'archived', 'label' => 'Archivée'],
            ])->rules(['nullable', 'in:draft,active,paused,archived']),
            Field::make('priority', 'Priorité', Field::TYPE_NUMBER)->rules(['nullable', 'integer', 'min:1', 'max:9999']),
            Field::make('minimum_notice_hours', 'Préavis minimal (h)', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:720']),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('toggle-bookable', 'Ouvrir / fermer aux réservations', function (ServiceZone $zone) {
                $zone->forceFill(['is_bookable' => ! $zone->is_bookable])->save();

                return ['is_bookable' => (bool) $zone->fresh()->is_bookable];
            }),

            Action::make('toggle-visible', 'Afficher / masquer', function (ServiceZone $zone) {
                $zone->forceFill(['is_visible' => ! $zone->is_visible])->save();

                return ['is_visible' => (bool) $zone->fresh()->is_visible];
            }),
        ];
    }

    public function reasonsToRefuseDelete(Model $model): array
    {
        return app(GeoGuard::class)->raisonsDeNePasSupprimerZone($model);
    }

    /**
     * Une zone créée par l'API naît FERMÉE.
     *
     * Même règle que le web : créer une zone ne doit pas la rendre commandable avant qu'on ait
     * réglé son catalogue et ses prix. Le formulaire n'expose donc pas `is_bookable` — c'est une
     * action séparée et délibérée.
     */
    public function prepareForCreate(array $data): array
    {
        $nom = (string) ($data['name'] ?? 'zone');

        return $data + [
            // Un identifiant lisible et unique, sans le demander à qui remplit le formulaire.
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(5)),
            // Brouillon par défaut : le statut est modifiable, mais ne se demande pas à la
            // création — on vient d'ouvrir une zone, pas de la mettre en service.
            'status' => 'draft',
            'coverage_type' => 'custom',
            'is_bookable' => false,
            'is_visible' => false,
            'travel_surcharge' => 0,
            'time_buffer_minutes' => 0,
        ];
    }
}
