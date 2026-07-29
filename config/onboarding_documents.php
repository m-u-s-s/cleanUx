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
    ],

];
