<?php

namespace App\Livewire\Client;

use App\Models\ProviderQuote;
use App\Services\Quotes\ProviderQuoteService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LES DEVIS REÇUS, CÔTÉ CLIENT (E24).
 *
 * LA MOITIÉ MANQUANTE DE E24. Une société qui bâtit un devis et l'envoie à un client qui ne peut
 * pas y répondre n'a rien envoyé : elle a produit un document que le client découvre par une
 * notification et auquel il répond par téléphone — c'est-à-dire hors de toute trace.
 *
 * ACCEPTER CRÉE LE TRAVAIL, pas un accusé de réception. Chaque ligne porte un métier et devient une
 * réservation : un devis accepté qui ne crée rien laisse les deux parties d'accord et personne au
 * travail.
 *
 * UN DEVIS PÉRIMÉ NE S'ACCEPTE PAS, même si le balayage n'est pas passé. Le contraire ferait
 * dépendre la validité d'un prix de l'heure d'exécution d'un cron.
 */
class ReceivedQuotes extends Component
{
    #[Locked]
    public ?string $refus = null;

    #[Locked]
    public ?int $devisOuvertId = null;

    public string $motifRefus = '';

    public function ouvrirLeDevis(int $devisId): void
    {
        $this->devisOuvertId = $this->devisRecu($devisId)?->id;
    }

    public function accepter(int $devisId): void
    {
        $devis = $this->devisRecu($devisId);

        if ($devis === null) {
            return;
        }

        try {
            app(ProviderQuoteService::class)->accepter($devis, Auth::user());
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function refuser(int $devisId): void
    {
        $devis = $this->devisRecu($devisId);

        if ($devis === null) {
            return;
        }

        try {
            app(ProviderQuoteService::class)->refuser(
                $devis,
                Auth::user(),
                $this->motifRefus !== '' ? $this->motifRefus : null,
            );

            $this->reset(['motifRefus', 'refus']);
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    /**
     * Un devis QUI M'EST ADRESSÉ, ou `null`.
     *
     * Le scoping fait partie de la requête : un identifiant forgé ne doit jamais charger le devis
     * d'un autre client — il porte son nom, son adresse et ce qu'il paye.
     */
    private function devisRecu(int $devisId): ?ProviderQuote
    {
        return ProviderQuote::query()
            ->where('client_user_id', Auth::id())
            ->find($devisId);
    }

    public function render(): View
    {
        return view('livewire.client.received-quotes', [
            'devis' => ProviderQuote::query()
                ->where('client_user_id', Auth::id())
                // Un brouillon n'a pas été envoyé : le montrer ferait découvrir un prix que la
                // société n'a pas fini d'écrire.
                ->where('status', '!=', ProviderQuote::STATUS_DRAFT)
                ->with('organizationAccount:id,name')
                ->latest()
                ->get(),
            'devisOuvert' => $this->devisOuvertId
                ? ProviderQuote::query()
                    ->where('client_user_id', Auth::id())
                    ->with(['lines.trade:id,name', 'organizationAccount:id,name'])
                    ->find($this->devisOuvertId)
                : null,
        ])->layout('layouts.app');
    }
}
