<?php

namespace App\Services\Email;

use App\Models\FinanceInvoice;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProductEmailTemplates
{
    public static function definitions(): array
    {
        return [
            'booking_confirmed' => 'Rendez-vous confirmé',
            'booking_reminder' => 'Rappel de rendez-vous',
            'feedback_request' => 'Demande de feedback',
            'finance_reminder' => 'Rappel de facture',
            'new_booking_admin' => 'Nouvelle demande côté équipe',
            'status_update' => 'Mise à jour de statut',
        ];
    }

    public static function sampleRecipient(): User
    {
        return new User([
            'name' => 'Client Démo',
            'email' => 'client@example.test',
            'role' => User::ROLE_CLIENT,
        ]);
    }

    public static function sampleRendezVous(): RendezVous
    {
        $rdv = new RendezVous([
            'id' => 999,
            'service_type' => 'nettoyage_standard',
            'date' => Carbon::now()->addDays(3)->toDateString(),
            'heure' => '09:30:00',
            'adresse' => 'Rue de la Station 12',
            'ville' => 'Bruxelles',
            'status' => 'confirme',
            'priorite' => 'normale',
            'presence_animaux' => false,
            'acces_parking' => true,
            'materiel_fournit' => true,
            'photos_reference' => [],
        ]);

        $rdv->setRelation('client', self::sampleRecipient());
        $rdv->setRelation('employe', new User([
            'name' => 'Employé Démo',
            'email' => 'employe@example.test',
            'role' => User::ROLE_EMPLOYE,
        ]));

        return $rdv;
    }

    public static function sampleInvoice(): FinanceInvoice
    {
        return new FinanceInvoice([
            'id' => 555,
            'invoice_number' => 'FAC-DEMO-001',
            'total_amount' => 184.75,
            'balance_due' => 124.75,
            'due_at' => Carbon::now()->addDays(7),
            'status' => 'sent',
        ]);
    }

    public static function payload(string $key): array
    {
        $rdv = self::sampleRendezVous();
        $invoice = self::sampleInvoice();

        return match ($key) {
            'booking_confirmed' => [
                'template_key' => $key,
                'subject' => 'Rendez-vous confirmé',
                'eyebrow' => 'Confirmation',
                'title' => 'Votre intervention est confirmée',
                'intro' => 'Bonne nouvelle : votre prestation a bien été validée dans CleanUx.',
                'details' => [
                    ['label' => 'Service', 'value' => 'Nettoyage standard'],
                    ['label' => 'Date', 'value' => Carbon::parse($rdv->date)->format('d/m/Y') . ' à ' . substr($rdv->heure, 0, 5)],
                    ['label' => 'Adresse', 'value' => $rdv->adresse . ', ' . $rdv->ville],
                ],
                'highlight' => 'Vous recevrez un rappel avant l’intervention.',
                'action_text' => 'Voir mon espace client',
                'action_url' => url('/dashboard/client'),
                'outro' => 'Merci pour votre confiance.',
                'tone' => 'success',
            ],
            'booking_reminder' => [
                'template_key' => $key,
                'subject' => 'Rappel de votre intervention',
                'eyebrow' => 'Rappel',
                'title' => 'Votre intervention approche',
                'intro' => 'Petit rappel : votre intervention est prévue dans 24h.',
                'details' => [
                    ['label' => 'Service', 'value' => 'Nettoyage standard'],
                    ['label' => 'Créneau', 'value' => Carbon::parse($rdv->date)->format('d/m/Y') . ' à ' . substr($rdv->heure, 0, 5)],
                    ['label' => 'Lieu', 'value' => $rdv->adresse . ', ' . $rdv->ville],
                ],
                'action_text' => 'Vérifier ma réservation',
                'action_url' => url('/dashboard/client'),
                'outro' => 'À très bientôt.',
                'tone' => 'info',
            ],
            'feedback_request' => [
                'template_key' => $key,
                'subject' => 'Comment s’est passée votre intervention ?',
                'eyebrow' => 'Qualité',
                'title' => 'Votre avis compte vraiment',
                'intro' => 'Prenez 30 secondes pour nous dire comment s’est passée votre prestation.',
                'details' => [
                    ['label' => 'Service', 'value' => 'Nettoyage standard'],
                    ['label' => 'Intervention', 'value' => Carbon::parse($rdv->date)->format('d/m/Y')],
                ],
                'action_text' => 'Laisser un feedback',
                'action_url' => url('/feedback/ajouter/' . $rdv->id),
                'outro' => 'Votre retour nous aide à améliorer la qualité.',
                'tone' => 'warning',
            ],
            'finance_reminder' => [
                'template_key' => $key,
                'subject' => 'Rappel de facture CleanUx',
                'eyebrow' => 'Finance',
                'title' => 'Un solde reste à régler',
                'intro' => 'Nous vous envoyons un rappel concernant une facture encore ouverte.',
                'details' => [
                    ['label' => 'Facture', 'value' => $invoice->invoice_number],
                    ['label' => 'Montant total', 'value' => number_format((float) $invoice->total_amount, 2, ',', ' ') . ' €'],
                    ['label' => 'Reste à payer', 'value' => number_format((float) $invoice->balance_due, 2, ',', ' ') . ' €'],
                    ['label' => 'Échéance', 'value' => optional($invoice->due_at)->format('d/m/Y')],
                ],
                'action_text' => 'Voir mes documents',
                'action_url' => url('/dashboard/client/finance'),
                'outro' => 'Si le paiement a déjà été effectué, ignorez simplement ce message.',
                'tone' => 'danger',
            ],
            'new_booking_admin' => [
                'template_key' => $key,
                'subject' => 'Nouvelle demande de nettoyage',
                'eyebrow' => 'Backoffice',
                'title' => 'Une nouvelle demande nécessite votre attention',
                'intro' => 'Une nouvelle demande vient d’être enregistrée sur la plateforme.',
                'details' => [
                    ['label' => 'Client', 'value' => $rdv->client?->name ?? 'Client Démo'],
                    ['label' => 'Service', 'value' => 'Nettoyage standard'],
                    ['label' => 'Priorité', 'value' => ucfirst((string) $rdv->priorite)],
                ],
                'action_text' => 'Ouvrir le planning',
                'action_url' => url('/admin/planning'),
                'outro' => 'Merci de confirmer ou réaffecter rapidement cette mission.',
                'tone' => 'info',
            ],
            'status_update' => [
                'template_key' => $key,
                'subject' => 'Mise à jour de votre demande',
                'eyebrow' => 'Suivi',
                'title' => 'Le statut de votre intervention a changé',
                'intro' => 'Votre intervention a été mise à jour dans votre espace client.',
                'details' => [
                    ['label' => 'Nouveau statut', 'value' => 'Confirmée'],
                    ['label' => 'Date', 'value' => Carbon::parse($rdv->date)->format('d/m/Y') . ' à ' . substr($rdv->heure, 0, 5)],
                ],
                'action_text' => 'Voir le détail',
                'action_url' => url('/dashboard/client/rendez-vous'),
                'outro' => 'Nous restons disponibles si vous avez une question.',
                'tone' => 'success',
            ],
            default => [
                'template_key' => 'generic',
                'subject' => 'Notification CleanUx',
                'eyebrow' => 'CleanUx',
                'title' => 'Notification',
                'intro' => 'Un message vient d’être généré depuis la plateforme.',
                'details' => [],
                'tone' => 'info',
            ],
        };
    }
}
