<?php

namespace App\Livewire\Client;

use App\Models\ProviderQuote;
use App\Services\Quotes\ProviderQuoteService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LES DEVIS REÇUS, CÔTÉ CLIENT (E24). LA MOITIÉ MANQUANTE DE E24. */
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

    /** Un devis QUI M'EST ADRESSÉ, ou `null`. */
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
