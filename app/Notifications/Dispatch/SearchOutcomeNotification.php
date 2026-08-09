<?php

namespace App\Notifications\Dispatch;

use App\Models\AsapDispatchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * CE QUI EST ARRIVÉ À VOTRE DEMANDE — relancée, convertie, ou annulée.
 *
 * Le client vient de choisir une suite sur un écran d'attente. Sans cette notification, il ferme
 * l'onglet et n'a plus aucune trace de ce qu'il a décidé : ni dans ses emails, ni dans son
 * application. La demande semble s'être évaporée, et c'est le support qui l'apprend.
 *
 * CANAL BASE DE DONNÉES SEUL. Une conversion en rendez-vous produit déjà sa propre confirmation, et
 * une annulation son propre récapitulatif : doubler d'un email ferait trois messages pour un seul
 * geste.
 */
class SearchOutcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AsapDispatchRequest $search,
        /** relaunched | converted | cancelled */
        public string $outcome,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'asap_search_outcome',
            'outcome' => $this->outcome,
            'search_id' => (int) $this->search->id,
            'booking_id' => $this->search->booking_id,
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }

    protected function title(): string
    {
        return match ($this->outcome) {
            'relaunched' => 'Nous cherchons à nouveau',
            'converted' => 'Votre demande est devenue un rendez-vous',
            'cancelled' => 'Votre demande a été annulée',
            default => 'Votre demande a changé',
        };
    }

    protected function body(): string
    {
        return match ($this->outcome) {
            'relaunched' => 'Nous élargissons la recherche autour de votre adresse. Vous serez prévenu dès qu’un professionnel accepte.',
            'converted' => 'Aucun professionnel n’était disponible immédiatement : votre demande est planifiée, au prix déjà accepté, sans nouveau paiement.',
            'cancelled' => 'Aucun montant n’a été prélevé : le paiement n’est engagé qu’à partir du moment où un professionnel accepte.',
            default => 'L’état de votre demande a changé.',
        };
    }
}
