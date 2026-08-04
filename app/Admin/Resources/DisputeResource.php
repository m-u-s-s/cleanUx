<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\AdminResource;
use App\Admin\Console\Column;
use App\Admin\Console\DefaultsResourceWrites;
use App\Admin\Console\Field;
use App\Admin\Console\Filter;
use App\Models\ComplaintCase;
use App\Models\User;
use App\Services\Disputes\DisputeResolutionService;
use App\Services\Disputes\DisputeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les litiges à traiter.
 *
 * DEUX MODÈLES DE LITIGE COEXISTENT dans la plateforme. Celui-ci est `ComplaintCase`
 * (`complaint_cases`) — celui de la page admin « Litiges » et du `DisputeResolutionService`.
 * `CustomerClaim` est un modèle parallèle : servir l'un en croyant servir l'autre donnerait une
 * file d'attente qui ne correspond à rien de ce qu'un administrateur peut résoudre.
 *
 * AUCUNE RÈGLE N'EST REJOUÉE ICI. L'escalade passe par le service, qui journalise l'événement,
 * recalcule le SLA et notifie. Écrire `status = 'escalated'` à la main produirait un litige
 * escaladé dont personne n'est prévenu et dont l'horloge ne tourne pas.
 *
 * LA CLÔTURE SANS SUITE est servie ici : l'action déclare le motif qu'elle exige, et le moteur
 * le demande avant d'appeler le service. La résolution AVEC indemnisation, elle, reste hors
 * console — elle exige un type, un montant ET une explication, et le montant d'un dédommagement
 * ne se saisit pas entre deux portes.
 *
 * @implements AdminResource<ComplaintCase>
 */
class DisputeResource implements AdminResource
{
    use DefaultsResourceWrites;

    /**
     * DEUX services, parce que la plateforme les a séparés : `DisputeService` porte le cycle de
     * vie (escalade, SLA), `DisputeResolutionService` porte les issues (clôture, indemnisation).
     * Les confondre ici reviendrait à réécrire l'un des deux.
     */
    public function __construct(
        private readonly DisputeService $disputes,
        private readonly DisputeResolutionService $resolution,
    ) {}

    public function key(): string
    {
        return 'disputes';
    }

    /** @return Builder<ComplaintCase> */
    public function query(): Builder
    {
        return ComplaintCase::query();
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    public function columns(): array
    {
        return [
            Column::make('subject', 'Objet'),
            Column::make('status', 'Statut', Column::TYPE_BADGE),
            Column::make('priority', 'Priorité', Column::TYPE_BADGE),
            Column::make('category', 'Catégorie'),
            Column::make('due_at', 'Échéance', Column::TYPE_DATETIME),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::search('q', 'Objet ou description'),
            Filter::select('status', 'Statut', [
                ['value' => ComplaintCase::STATUS_OPEN, 'label' => 'Ouvert'],
                ['value' => ComplaintCase::STATUS_ASSIGNED, 'label' => 'Assigné'],
                ['value' => ComplaintCase::STATUS_INVESTIGATING, 'label' => 'En investigation'],
                ['value' => ComplaintCase::STATUS_ESCALATED, 'label' => 'Escaladé'],
                ['value' => ComplaintCase::STATUS_RESOLVED, 'label' => 'Résolu'],
                ['value' => ComplaintCase::STATUS_CLOSED, 'label' => 'Clôturé'],
            ]),
            Filter::bool('ouverts', 'À traiter seulement'),
            Filter::bool('en_retard', 'Échéance dépassée'),
        ];
    }

    public function sorts(): array
    {
        return ['id', 'due_at', 'created_at'];
    }

    public function actions(): array
    {
        return [
            Action::make('reject', 'Clore sans suite', function (ComplaintCase $model, array $saisie) {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return ['ok' => false];
                }

                $this->resolution->dismiss($model, $admin, (string) $saisie['reason']);

                return ['ok' => true];
            })
                ->destructive('Le litige sera clos sans indemnisation et le client en sera informé.')
                ->requires([
                    // Le motif est OBLIGATOIRE et long : un refus sans explication écrite n'est
                    // ni contestable par la personne concernée, ni auditable six mois plus tard.
                    Field::make('reason', 'Motif du refus', Field::TYPE_TEXTAREA)
                        ->rules(['required', 'string', 'min:10', 'max:1000']),
                ]),

            // La fermeture est typée sur le modèle du domaine, pas sur `Model` : le moteur ne lui
            // passe jamais autre chose, et un jour où ce serait le cas, PHP le dirait fort plutôt
            // que de laisser le service travailler sur une entité qui n'est pas la sienne.
            Action::make('escalate', 'Escalader', function (ComplaintCase $model) {
                // Délégation stricte : le service journalise l'événement, recalcule le SLA et
                // notifie. Écrire `status = 'escalated'` à la main produirait un litige escaladé
                // dont personne n'est prévenu et dont l'horloge ne tourne pas.
                $this->disputes->escalate($model, 'Escaladé depuis la console mobile.');

                return ['ok' => true];
            })->destructive('Le litige passera au niveau supérieur et les personnes concernées seront prévenues.'),
        ];
    }

    public function formFields(): array
    {
        // Une file de décision ne se remplit pas à la main : les litiges naissent des clients.
        return [];
    }

    /**
     * @param  Builder<ComplaintCase>  $query
     * @return Builder<ComplaintCase>
     */
    public function applyFilter(Builder $query, string $key, mixed $value): Builder
    {
        return match ($key) {
            'q' => $query->where(function (Builder $sub) use ($value) {
                $sub->where('subject', 'like', '%'.$value.'%')
                    ->orWhere('description', 'like', '%'.$value.'%');
            }),
            'status' => $query->where('status', $value),
            'ouverts' => $query->whereNotIn('status', [
                ComplaintCase::STATUS_RESOLVED,
                ComplaintCase::STATUS_CLOSED,
            ]),
            'en_retard' => $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', [
                    ComplaintCase::STATUS_RESOLVED,
                    ComplaintCase::STATUS_CLOSED,
                ]),
            default => $query,
        };
    }

    /** @param  ComplaintCase  $model */
    public function toRow(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'subject' => $model->subject,
            'status' => $model->status,
            'priority' => $model->priority,
            'category' => $model->category,
            'due_at' => $model->due_at?->toIso8601String(),
        ];
    }

    /** @param  ComplaintCase  $model */
    public function toDetail(Model $model): array
    {
        return $this->toRow($model) + [
            'description' => $model->description,
            'sla_policy' => $model->sla_policy,
            'created_at' => $model->created_at?->toIso8601String(),
        ];
    }
}
