<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'client_tos',
                'name' => 'Conditions générales client',
                'type' => ContractTemplate::TYPE_TOS,
                'role' => ContractTemplate::ROLE_CLIENT,
                'version' => '2026-05-v1',
                'body_markdown' => <<<MD
# Conditions générales d'utilisation

Bienvenue sur **{{app_name}}**.

## 1. Acceptation

En utilisant nos services, vous, **{{name}}** ({{email}}), acceptez les présentes conditions générales version *{{version}}*.

## 2. Réservation de services

- Vous pouvez réserver des prestations via notre plateforme.
- Les prix sont calculés selon notre grille tarifaire en vigueur.
- Toute réservation entraîne acceptation du tarif affiché.

## 3. Annulation

- Annulation > 48h : gratuite.
- Annulation < 48h : frais selon notre politique d'annulation.

## 4. Support

Pour toute question, contactez {{support_email}}.

Signé le {{date}}.
MD,
                'is_active' => true,
            ],
            [
                'code' => 'provider_agreement',
                'name' => 'Contrat prestataire',
                'type' => ContractTemplate::TYPE_PROVIDER_AGREEMENT,
                'role' => ContractTemplate::ROLE_PROVIDER,
                'version' => '2026-05-v1',
                'body_markdown' => <<<MD
# Contrat de prestation indépendante

Entre **{{app_name}}** et **{{name}}** ({{email}}).

## 1. Objet

Le prestataire fournit des services via la plateforme aux clients enregistrés.

## 2. Obligations du prestataire

- Effectuer les prestations avec professionnalisme.
- Respecter les horaires convenus.
- Maintenir une couverture d'assurance valide.
- Compléter son KYC et fournir tous les documents requis.

## 3. Rémunération

- Reversement via Stripe Connect.
- Commission plateforme selon grille en vigueur.

## 4. Résiliation

Chaque partie peut résilier le contrat à tout moment avec un préavis de 30 jours.

Signé le {{date}}, version *{{version}}*.
MD,
                'is_active' => true,
            ],
            [
                'code' => 'nda_b2b',
                'name' => 'NDA B2B',
                'type' => ContractTemplate::TYPE_NDA,
                'role' => ContractTemplate::ROLE_ENTERPRISE,
                'version' => '2026-05-v1',
                'body_markdown' => <<<MD
# Accord de confidentialité (NDA)

Entre **{{app_name}}** et **{{company}}** représentée par {{name}} ({{email}}).

## 1. Information confidentielle

Toute information échangée dans le cadre de notre partenariat B2B est strictement confidentielle.

## 2. Durée

Cet accord est valide pour une durée de 3 ans à compter de la signature.

Signé le {{date}}, version *{{version}}*.
MD,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tpl) {
            ContractTemplate::query()->updateOrCreate(
                ['code' => $tpl['code'], 'version' => $tpl['version']],
                $tpl,
            );
        }
    }
}
