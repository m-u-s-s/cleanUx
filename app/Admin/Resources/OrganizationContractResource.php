<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\OrganizationContract;

/**
 * Les contrats-cadres des comptes entreprise.
 *
 * LE TROISIÈME MODÈLE de la page « Opérations B2B », après les ordres de travail et les grilles
 * tarifaires. Chacun avait besoin de son descripteur : le moteur en sert un par modèle, et servir
 * trois modèles depuis un seul aurait demandé d'inventer un mécanisme que rien d'autre n'emploie.
 *
 * CE QUE LE FORMULAIRE N'OFFRE PAS, et ce n'est pas un oubli : les règles de service, de prix et de
 * SLA sont des structures JSON que le web édite par des écrans dédiés. Les exposer en champ texte
 * sur un téléphone inviterait à saisir du JSON à la main, avec une chance sérieuse de casser le
 * contrat d'un client en le sauvegardant.
 *
 * @extends EloquentResource<OrganizationContract>
 */
class OrganizationContractResource extends EloquentResource
{
    public function key(): string
    {
        return 'b2b-contracts';
    }

    protected function model(): string
    {
        return OrganizationContract::class;
    }

    protected function columnSpec(): array
    {
        return [
            'contract_reference' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'pricing_model' => ['Modèle tarifaire'],
            'billing_cycle' => ['Facturation'],
            'starts_at' => ['Début', Column::TYPE_DATE],
            'ends_at' => ['Fin', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['contract_reference'];
    }

    protected function searchLabel(): string
    {
        return 'Référence du contrat';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'suspended', 'label' => 'Suspendu'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'approval_mode' => 'Mode d’approbation',
            'payment_terms_days' => 'Délai de paiement (j)',
            'sla_response_hours' => 'SLA réponse (h)',
            'sla_resolution_hours' => 'SLA résolution (h)',
            'monthly_budget' => 'Budget mensuel',
            'notes' => 'Notes',
        ];
    }

    public function formFields(): array
    {
        return [
            Field::make('contract_reference', 'Référence')->rules(['required', 'string', 'max:255']),
            Field::select('status', 'Statut', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'active', 'label' => 'Actif'],
                ['value' => 'suspended', 'label' => 'Suspendu'],
                ['value' => 'expired', 'label' => 'Expiré'],
            ])->rules(['required', 'in:draft,active,suspended,expired']),
            Field::make('pricing_model', 'Modèle tarifaire')->rules(['required', 'string', 'max:50']),
            Field::make('billing_cycle', 'Cycle de facturation')->rules(['required', 'string', 'max:50']),
            Field::make('approval_mode', 'Mode d’approbation')->rules(['required', 'string', 'max:50']),
            Field::make('payment_terms_days', 'Délai de paiement (jours)', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:365']),
            Field::make('sla_response_hours', 'SLA réponse (heures)', Field::TYPE_NUMBER)
                ->rules(['nullable', 'integer', 'min:0', 'max:720']),
            Field::make('notes', 'Notes', Field::TYPE_TEXTAREA)->rules(['nullable', 'string', 'max:3000']),
        ];
    }
}
