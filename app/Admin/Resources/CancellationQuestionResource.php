<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\CancellationQuestion;
use App\Services\Cancellation\CancellationQuestionnaireService;

/**
 * LE QUESTIONNAIRE D'ANNULATION, ADMINISTRÉ.
 *
 * ── POURQUOI LES ACTIONS PASSENT PAR LE SERVICE ──────────────────────────────────────────────
 *
 * Règle de cette console : une action passe par le service du domaine, jamais par une écriture de
 * colonne. Basculer `is_active` à la main produirait l'état sans ses effets — sans le journal, et
 * surtout sans le refus qui empêche d'activer une question dépourvue de toute réponse possible.
 * Le questionnaire afficherait alors une question sans case à cocher, et plus personne ne pourrait
 * annuler.
 *
 * ── POURQUOI « SUPPRIMER » NE SUPPRIME PAS ───────────────────────────────────────────────────
 *
 * Une annulation d'il y a six mois porte le `reason_code` d'une option retirée depuis. Le dossier
 * doit rester lisible : la ligne quitte les écrans, elle ne quitte pas la base. C'est la même
 * raison qui rend `cancellation_policies` versionnée.
 *
 * ── LES OPTIONS NE S'ÉDITENT PAS ICI ─────────────────────────────────────────────────────────
 *
 * Une question porte plusieurs réponses, chacune avec sa vérification et son issue : c'est un arbre,
 * que le rendu générique d'une liste ne sait pas montrer sans mentir sur sa structure. Même choix
 * que la grille tarifaire d'un métier, qui reste sur sa page dédiée.
 *
 * @extends EloquentResource<CancellationQuestion>
 */
class CancellationQuestionResource extends EloquentResource
{
    public function key(): string
    {
        return 'cancellation-questions';
    }

    protected function model(): string
    {
        return CancellationQuestion::class;
    }

    protected function columnSpec(): array
    {
        return [
            'label' => ['Question'],
            'code' => ['Code'],
            'audience' => ['Posée à', Column::TYPE_BADGE],
            'engine' => ['Moteur'],
            'is_active' => ['Active', Column::TYPE_BOOL],
            'sort_order' => ['Ordre', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['label', 'code'];
    }

    protected function searchLabel(): string
    {
        return 'Libellé ou code';
    }

    protected function selectFilters(): array
    {
        return [
            'audience' => ['Posée à', 'audience', [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'provider', 'label' => 'Prestataire'],
                ['value' => 'both', 'label' => 'Les deux'],
            ]],
            'engine' => ['Moteur', 'engine', [
                ['value' => 'domicile', 'label' => 'À domicile'],
                ['value' => 'horaire', 'label' => 'À l’heure'],
                ['value' => 'vehicule', 'label' => 'Véhicule'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'help_text' => 'Aide affichée',
            'created_at' => 'Créée le',
        ];
    }

    /**
     * LE CODE EST SAISISSABLE À LA CRÉATION, ET IMMUABLE ENSUITE — le service le garantit :
     * `modifierQuestion()` retire la clé avant d'écrire. Il vit dans les dossiers déjà clos.
     */
    public function formFields(): array
    {
        return [
            Field::make('code', 'Code (stable, jamais réutilisé)')
                ->rules(['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/']),
            Field::make('label', 'Question posée')->rules(['required', 'string', 'max:191']),
            Field::select('audience', 'Posée à', [
                ['value' => 'client', 'label' => 'Client'],
                ['value' => 'provider', 'label' => 'Prestataire'],
                ['value' => 'both', 'label' => 'Les deux'],
            ])->rules(['required', 'in:client,provider,both']),
            /*
             * VIDE = TOUS LES MOTEURS. C'est le cas courant ; on ne restreint que les questions dont
             * l'issue n'existe pas partout — « le travail ne correspond pas » n'a de sens que là où
             * il y a un devis à réviser.
             */
            Field::select('engine', 'Moteur (vide = tous)', [
                ['value' => '', 'label' => 'Tous'],
                ['value' => 'domicile', 'label' => 'À domicile'],
                ['value' => 'horaire', 'label' => 'À l’heure'],
                ['value' => 'vehicule', 'label' => 'Véhicule'],
            ])->rules(['nullable', 'in:domicile,horaire,vehicule']),
            Field::make('help_text', 'Aide affichée', Field::TYPE_TEXTAREA)
                ->rules(['nullable', 'string', 'max:500']),
            Field::make('sort_order', 'Ordre', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:9999']),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('activer', 'Activer', function (CancellationQuestion $question) {
                app(CancellationQuestionnaireService::class)->basculerQuestion($question, true);
            }),

            Action::make('desactiver', 'Désactiver', function (CancellationQuestion $question) {
                app(CancellationQuestionnaireService::class)->basculerQuestion($question, false);
            }),

            /*
             * « RETIRER » ET NON « SUPPRIMER » : le mot compte. La ligne quitte les écrans et reste
             * lisible pour les dossiers d'annulation qui portent encore son code.
             */
            Action::make('retirer', 'Retirer du questionnaire', function (CancellationQuestion $question) {
                app(CancellationQuestionnaireService::class)->retirerQuestion($question);
            }),
        ];
    }
}
