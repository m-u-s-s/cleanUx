<?php

namespace App\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class HelpCenter extends Component
{
    public string $search = '';

    public string $category = '';

    /** FAQ structure : à terme à stocker en DB. Pour MVP, hardcoded. */
    public function faqs(): array
    {
        return [
            'general' => [
                'label' => 'Général',
                'items' => [
                    [
                        'q' => "Qu'est-ce que Brio ?",
                        'a' => 'Brio est une marketplace multi-métiers (nettoyage, peinture, babysitting, toiturier, etc.) qui met en relation des clients avec des prestataires vérifiés.',
                    ],
                    [
                        'q' => 'Comment créer un compte ?',
                        'a' => "Cliquez sur 'S'inscrire' dans le menu, choisissez votre type de compte (particulier, entreprise, prestataire) puis remplissez vos informations.",
                    ],
                ],
            ],
            'booking' => [
                'label' => 'Réservation & mission',
                'items' => [
                    [
                        'q' => 'Comment réserver une prestation ?',
                        'a' => "Depuis votre tableau de bord, cliquez sur 'Nouveau RDV', choisissez votre métier, l'adresse, la date et confirmez. Vous serez mis en relation avec un prestataire disponible.",
                    ],
                    [
                        'q' => 'Puis-je modifier ou annuler ma réservation ?',
                        'a' => 'Oui. Plus de 24h avant : remboursement intégral. Entre 24h et 2h : 50% remboursé. Moins de 2h ou no-show : 100% facturé.',
                    ],
                    [
                        'q' => 'Comment voir où en est mon prestataire ?',
                        'a' => 'Lorsque la mission est en cours, vous accédez au suivi temps réel depuis le détail de la mission (carte + ETA).',
                    ],
                ],
            ],
            'payment' => [
                'label' => 'Paiement & facturation',
                'items' => [
                    [
                        'q' => 'Quels moyens de paiement acceptez-vous ?',
                        'a' => 'Cartes bancaires (Visa, Mastercard, AMEX) via Stripe. Apple Pay et Google Pay sur mobile. Virement SEPA pour B2B.',
                    ],
                    [
                        'q' => 'Quand suis-je débité ?',
                        'a' => 'Votre carte est empreintée à la réservation : la somme est bloquée, pas prélevée. '
                            .'Le débit a lieu à la clôture de la mission. Vous pouvez ajouter un pourboire ensuite.',
                    ],
                    [
                        'q' => 'Comment télécharger ma facture ?',
                        'a' => "Depuis 'Mes documents financiers' dans votre espace, vous pouvez télécharger devis et factures en PDF.",
                    ],
                ],
            ],
            'safety' => [
                'label' => 'Sécurité & confiance',
                'items' => [
                    [
                        'q' => 'Comment vérifiez-vous les prestataires ?',
                        'a' => 'Chaque prestataire passe une vérification KYC (identité officielle), KYB (entreprise si applicable), et fournit ses assurances. Une note moyenne et des avis publics sont affichés sur son profil.',
                    ],
                    [
                        'q' => 'Que faire en cas de problème pendant une mission ?',
                        'a' => 'Vous pouvez ouvrir un litige depuis le détail de la mission. Notre équipe SAV intervient sous 24h pour médiation gratuite.',
                    ],
                    [
                        'q' => 'Mes données sont-elles protégées ?',
                        'a' => 'Oui. Conforme RGPD, données chiffrées en transit (TLS 1.3) et au repos. Voir notre Politique de confidentialité.',
                    ],
                ],
            ],
            'provider' => [
                'label' => 'Pour les prestataires',
                'items' => [
                    [
                        'q' => 'Comment devenir prestataire ?',
                        'a' => 'Créez un compte prestataire, puis renseignez votre identité, vos métiers, vos zones '
                            .'et votre compte bancaire. Vous recevez des missions une fois ce dossier validé.',
                    ],
                    [
                        'q' => 'Quel est le pourcentage de commission ?',
                        'a' => "15 % sur chaque mission, avec un minimum de 2 € qui couvre les frais d'encaissement. "
                            .'0 % sur les pourboires : ils vous sont reversés en entier.',
                    ],
                    [
                        'q' => 'Quand suis-je payé ?',
                        'a' => 'Votre part part sur votre compte Stripe dès la clôture. Stripe la vire ensuite vers '
                            .'votre banque selon son propre calendrier, en général sous sept jours.',
                    ],
                ],
            ],
        ];
    }

    public function render(): View
    {
        $faqs = $this->faqs();

        if ($this->category && isset($faqs[$this->category])) {
            $faqs = [$this->category => $faqs[$this->category]];
        }

        if ($this->search) {
            $term = mb_strtolower($this->search);
            foreach ($faqs as $catKey => $cat) {
                $filtered = array_filter($cat['items'], function ($item) use ($term) {
                    return str_contains(mb_strtolower($item['q']), $term)
                        || str_contains(mb_strtolower($item['a']), $term);
                });
                $faqs[$catKey]['items'] = array_values($filtered);
                if (empty($faqs[$catKey]['items'])) {
                    unset($faqs[$catKey]);
                }
            }
        }

        return view('livewire.public.help-center', [
            'faqs' => $faqs,
            'allCategories' => $this->faqs(),
        ])->layout('layouts.app');
    }
}
