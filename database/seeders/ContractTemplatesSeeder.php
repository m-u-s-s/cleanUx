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
                /*
                 * VERSION INCRÉMENTÉE : la §2.1 ajoute une règle de facturation.
                 *
                 * Un consentement donné sur la version précédente ne couvre pas une majoration
                 * qu'elle ne mentionnait pas. Garder le même numéro laisserait croire que les
                 * clients déjà signataires l'ont acceptée.
                 */
                'version' => '2026-08-v2',
                'body_markdown' => <<<'MD'
# Conditions générales d'utilisation

Bienvenue sur **{{app_name}}**.

## 1. Acceptation

En utilisant nos services, vous, **{{name}}** ({{email}}), acceptez les présentes conditions générales version *{{version}}*.

## 2. Réservation de services

- Vous pouvez réserver des prestations via notre plateforme.
- Les prix sont calculés selon notre grille tarifaire en vigueur.
- Toute réservation entraîne acceptation du tarif affiché.

### 2.1 Prestations facturées au temps passé

Certaines prestations sont vendues à l'heure. Vous en choisissez la durée à la commande et pouvez
la prolonger à tout moment — avant comme pendant l'intervention — au tarif horaire normal ; seules
les heures réellement prestées sont dues.

Si l'intervention se prolonge au-delà du temps acheté sans que vous l'ayez étendu, les premières
minutes de tolérance sont offertes, puis chaque quart d'heure entamé est facturé au tarif horaire
majoré. Cette majoration s'ajoute aux majorations éventuellement déjà appliquées (intervention
immédiate, nuit, week-end). Le dépassement facturable ne peut jamais excéder la durée initialement
commandée.

Le montant exact de la majoration, la durée de tolérance et le plafond vous sont indiqués au moment
où vous choisissez la durée, et rappelés sur l'écran de confirmation de commande.

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
                // Même raison : la §3.1 dit comment le temps supplémentaire est rémunéré.
                'version' => '2026-08-v2',
                'body_markdown' => <<<'MD'
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

### 3.1 Prestations facturées au temps passé

Les prestations vendues à l'heure le sont pour une durée précise, que le client peut prolonger à
tout moment. Au-delà de cette durée, et passé le délai de tolérance, le temps supplémentaire est
facturé au client à un tarif majoré.

Vous êtes rémunéré à votre tarif horaire NORMAL sur ce temps supplémentaire : la majoration revient
à la plateforme. Elle existe pour inciter le client à prolonger en temps voulu, et non pour
rémunérer un dépassement.

Il vous appartient de prévenir le client avant la fin du temps acheté : il peut alors prolonger sans
majoration, ce qui sert son intérêt comme le vôtre.

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
                'body_markdown' => <<<'MD'
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

        /*
         * LA CLÉ EST `code` SEUL, ET C'EST LA SEULE QUI TIENNE.
         *
         * `contract_templates` porte DEUX contraintes d'unicité : `(code, version)` et `code` tout
         * court. La seconde rend la première inopérante — deux versions d'un même contrat ne
         * peuvent pas coexister, quoi qu'en laisse croire la colonne `supersedes_template_id`.
         *
         * Chercher sur `(code, version)` marchait tant que la version ne bougeait jamais : sur une
         * base vierge, on insère. Le jour où l'on incrémente — ce qui vient d'arriver avec la règle
         * de facturation au temps —, aucune ligne ne correspond, l'insertion part, et MySQL la
         * refuse sur l'unicité de `code`. La CI ne l'aurait jamais vu : sa base est neuve à chaque
         * exécution. Staging et production, elles, l'auraient vu au premier déploiement.
         *
         * METTRE À JOUR EN PLACE NE RÉÉCRIT PAS CE QUI A ÉTÉ SIGNÉ : `contract_documents` conserve
         * son propre `body_rendered_html`. Un signataire garde donc le texte exact qu'il a accepté,
         * et c'est ce qui rend cette clé acceptable.
         */
        foreach ($templates as $tpl) {
            ContractTemplate::query()->updateOrCreate(
                ['code' => $tpl['code']],
                $tpl,
            );
        }
    }
}
