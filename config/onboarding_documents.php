<?php

use App\Models\ProviderOnboardingDocument;

return [

    /*
    |--------------------------------------------------------------------------
    | Justificatifs exigés d'un prestataire
    |--------------------------------------------------------------------------
    |
    | La liste dépend des métiers déclarés : un électricien fournit sa certification, un
    | garde d'enfants son extrait de casier, un jardinier ni l'un ni l'autre. Deux exigences
    | se lisent directement sur le métier — `trades.requires_insurance_proof` et
    | `trades.requires_certification` — et n'ont donc rien à faire ici.
    |
    | L'extrait de casier n'a pas de colonne équivalente. Plutôt que d'imposer une migration
    | pour une liste qui tient en quelques codes, elle est déclarée ici : la faire évoluer ne
    | demande alors ni déploiement de schéma ni publication de l'application.
    |
    */

    'criminal_record_trades' => [
        // Garde d'enfants : intervention auprès de mineurs, au domicile, sans témoin.
        'CHD',
        // Sécurité / gardiennage : accès aux locaux et aux biens du client.
        'SEC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Libellés présentés au prestataire
    |--------------------------------------------------------------------------
    |
    | `help` reprend les contraintes de prise de vue, à l'image d'Uber : un document illisible
    | est rejeté à la revue, plusieurs jours plus tard. Le dire avant la photo coûte moins cher
    | que de le dire après.
    |
    */

    'labels' => [
        ProviderOnboardingDocument::TYPE_IDENTITY_CARD => [
            'label' => "Pièce d'identité",
            'help' => "Carte d'identité, passeport ou titre de séjour en cours de validité. Les quatre coins visibles, sans reflet.",
        ],
        ProviderOnboardingDocument::TYPE_INSURANCE => [
            'label' => 'Assurance responsabilité civile professionnelle',
            'help' => 'Attestation en cours de validité, à votre nom ou à celui de votre société.',
        ],
        ProviderOnboardingDocument::TYPE_DIPLOMA => [
            'label' => 'Certification professionnelle',
            'help' => 'Diplôme, attestation de compétence ou agrément exigé pour votre métier.',
        ],
        ProviderOnboardingDocument::TYPE_CRIMINAL_RECORD => [
            'label' => 'Extrait de casier judiciaire',
            'help' => 'De moins de trois mois. Exigé pour les métiers auprès de personnes vulnérables.',
        ],
        ProviderOnboardingDocument::TYPE_TAX_ID => [
            'label' => 'Justificatif fiscal',
            'help' => 'Avis de situation SIRENE, extrait BCE ou équivalent.',
        ],
        ProviderOnboardingDocument::TYPE_DRIVING_LICENSE => [
            'label' => 'Permis de conduire',
            'help' => 'Recto ET verso, en cours de validité. Les quatre coins visibles, sans reflet sur la photo.',
        ],
        ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION => [
            'label' => 'Certificat d’immatriculation (carte grise)',
            'help' => 'Au nom du conducteur ou de sa société. C’est la date de PREMIÈRE immatriculation qui fait foi pour l’âge du véhicule.',
        ],
        ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE => [
            'label' => 'Assurance du véhicule',
            'help' => 'Attestation en cours de validité, portant la plaque d’immatriculation déclarée.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Délai laissé aux prestataires déjà inscrits
    |--------------------------------------------------------------------------
    |
    | Quand un métier devient un service de trajet, ou qu'un administrateur y coche « règles taxi »,
    | de nouvelles pièces deviennent exigibles. Les réclamer le jour même couperait tous les
    | prestataires du métier avant même qu'ils sachent ce qu'on leur demande — et le premier signe
    | serait, pour eux, un téléphone qui cesse de sonner.
    |
    | Le délai court depuis `trades.route_rules_since` / `trades.taxi_rules_since`, jamais depuis
    | « maintenant » : sans quoi il se réarmerait à chaque enregistrement du catalogue.
    |
    | À zéro, l'exigence s'applique immédiatement.
    */
    'trade_requirements_grace_days' => (int) env('ONBOARDING_TRADE_REQUIREMENTS_GRACE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Préavis avant péremption d'une pièce
    |--------------------------------------------------------------------------
    |
    | Un permis ou une assurance qui expire ne prévient personne. Le prestataire découvre la chose
    | au silence de son téléphone, plusieurs jours plus tard, et le support l'apprend par un appel
    | agacé — c'est l'angle mort connu de cette plateforme, transposé aux dates.
    |
    | Trente jours, comme la flotte (`fleet_v2.expiring_soon_days`) : assez pour prendre un
    | rendez-vous en préfecture ou renouveler un contrat d'assurance, assez court pour que l'alerte
    | reste liée à une action à faire plutôt qu'à une échéance lointaine qu'on oublie.
    |
    | À zéro, aucun préavis n'est envoyé — la péremption reste bloquante le jour venu.
    */
    'expiring_soon_days' => (int) env('ONBOARDING_DOCUMENT_EXPIRING_SOON_DAYS', 30),

];
