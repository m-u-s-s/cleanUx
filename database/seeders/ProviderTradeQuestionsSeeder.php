<?php

namespace Database\Seeders;

use App\Models\Trade;
use Illuminate\Database\Seeder;

/**
 * Questions posées au prestataire, par métier, au moment de l'inscription.
 *
 * `booking_form_schema` décrit ce qu'on demande au CLIENT qui réserve. Ce schéma-ci décrit ce
 * qu'on demande au PRESTATAIRE qui déclare exercer le métier : un électricien et un babysitter ne
 * relèvent ni des mêmes certifications ni des mêmes assurances.
 *
 * Les deux questions transverses (expérience, disponibilité) sont posées partout. Les questions
 * réglementaires découlent des drapeaux déjà portés par chaque métier — `requires_certification`
 * et `requires_insurance_proof` — plutôt que d'une liste tenue à la main qui divergerait d'eux.
 *
 * Idempotent, et ne réécrit pas un schéma déjà personnalisé à la main en base.
 */
class ProviderTradeQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        Trade::query()->each(function (Trade $trade) {
            if (filled($trade->provider_form_schema)) {
                return;
            }

            $trade->forceFill([
                'provider_form_schema' => ['fields' => $this->fieldsFor($trade)],
            ])->save();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fieldsFor(Trade $trade): array
    {
        $fields = [
            [
                'key' => 'experience_years',
                'type' => 'number',
                'label' => "Années d'expérience dans ce métier",
                'required' => true,
                'min' => 0,
                'max' => 60,
            ],
            [
                'key' => 'intervention_radius_km',
                'type' => 'number',
                'label' => 'Rayon d\'intervention (km)',
                'required' => true,
                'min' => 1,
                'max' => 200,
                'default' => 25,
            ],
        ];

        if ($trade->requires_certification) {
            $fields[] = [
                'key' => 'certification_reference',
                'type' => 'text',
                'label' => 'Référence de votre certification professionnelle',
                'help' => 'Ce métier est réglementé : le justificatif vous sera demandé à l\'étape suivante.',
                'required' => true,
            ];
        }

        if ($trade->requires_insurance_proof) {
            $fields[] = [
                'key' => 'insurance_company',
                'type' => 'text',
                'label' => 'Compagnie d\'assurance responsabilité civile professionnelle',
                'required' => true,
            ];
            $fields[] = [
                'key' => 'insurance_policy_number',
                'type' => 'text',
                'label' => 'Numéro de police',
                'required' => true,
            ];
        }

        $fields[] = [
            'key' => 'has_own_equipment',
            'type' => 'boolean',
            'label' => 'Je dispose de mon propre matériel',
            'required' => false,
            'default' => true,
        ];

        return $fields;
    }
}
