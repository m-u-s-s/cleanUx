<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Booking;
use App\Models\ProviderQuote;
use App\Models\Trade;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Quotes\ProviderQuoteService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LE DEVIS QUE LA SOCIÉTÉ BÂTIT ELLE-MÊME (E24). */
class QuoteBuilder extends Component
{
    use EnforcesActiveOrgMembership;

    public string $titre = '';

    public ?int $clientId = null;

    /** Le devis ouvert. `#[Locked]` : une propriété publique est modifiable par `$set`. */
    #[Locked]
    public ?int $devisOuvertId = null;

    public ?int $ligneTradeId = null;

    public string $ligneLibelle = '';

    public string $ligneQuantite = '1';

    public string $lignePrix = '';

    #[Locked]
    public ?string $refus = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'quotes.view', $acteur->currentOrganization),
            403
        );
    }

    public function creerLeDevis(): void
    {
        $this->autoriserLaGestion();

        $this->validate([
            'titre' => ['required', 'string', 'max:160'],
        ]);

        $devis = app(ProviderQuoteService::class)->ouvrirUnBrouillon(
            (int) Auth::user()->current_organization_id,
            Auth::user(),
            $this->titre,
            $this->clientId,
        );

        $this->devisOuvertId = $devis->id;
        $this->reset(['titre', 'refus']);
    }

    public function ouvrirLeDevis(int $devisId): void
    {
        $this->devisOuvertId = $this->devisDeLaSociete($devisId)?->id;
    }

    public function ajouterUneLigne(): void
    {
        $this->autoriserLaGestion();

        $devis = $this->devisOuvertId ? $this->devisDeLaSociete($this->devisOuvertId) : null;

        if ($devis === null) {
            return;
        }

        $this->validate([
            'ligneTradeId' => ['required', 'integer'],
            'ligneLibelle' => ['required', 'string', 'max:200'],
        ]);

        try {
            app(ProviderQuoteService::class)->ajouterUneLigne(
                $devis,
                (int) $this->ligneTradeId,
                $this->ligneLibelle,
                max(0.01, (float) str_replace(',', '.', $this->ligneQuantite)),
                // Vide = on retient la SUGGESTION du moteur ; une saisie, même égale, marque une
                // décision.
                is_numeric(str_replace(',', '.', $this->lignePrix))
                    ? (int) round(((float) str_replace(',', '.', $this->lignePrix)) * 100)
                    : null,
            );

            $this->reset(['ligneLibelle', 'lignePrix', 'refus']);
            $this->ligneQuantite = '1';
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function retirerUneLigne(int $ligneId): void
    {
        $this->autoriserLaGestion();

        $devis = $this->devisOuvertId ? $this->devisDeLaSociete($this->devisOuvertId) : null;

        if ($devis === null) {
            return;
        }

        try {
            app(ProviderQuoteService::class)->retirerUneLigne($devis, $ligneId);
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function envoyer(int $devisId): void
    {
        $this->autoriserLaGestion();

        $devis = $this->devisDeLaSociete($devisId);

        if ($devis === null) {
            return;
        }

        try {
            app(ProviderQuoteService::class)->envoyer($devis);
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    private function devisDeLaSociete(int $devisId): ?ProviderQuote
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        // Le scoping fait partie de la REQUÊTE : le devis d'une autre société n'est jamais chargé.
        return ProviderQuote::query()
            ->where('organization_account_id', $orgId)
            ->find($devisId);
    }

    private function autoriserLaGestion(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'quotes.manage', $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $orgId = (int) Auth::user()->current_organization_id;

        return view('livewire.provider-company.quote-builder', [
            'devis' => ProviderQuote::query()
                ->where('organization_account_id', $orgId)
                ->with('client:id,name')
                ->latest()
                ->limit(50)
                ->get(),
            'devisOuvert' => $this->devisOuvertId
                ? ProviderQuote::query()
                    ->where('organization_account_id', $orgId)
                    ->with('lines.trade:id,name')
                    ->find($this->devisOuvertId)
                : null,
            'metiers' => Trade::query()->orderBy('name')->get(['id', 'name']),
            // SES CLIENTS, PAS CEUX DE LA PLATEFORME.
            'clients' => User::query()
                ->whereIn('id', Booking::query()
                    ->where('assigned_provider_organization_id', $orgId)
                    ->whereNotNull('client_id')
                    ->distinct()
                    ->pluck('client_id'))
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name']),
            'peutGerer' => app(PermissionService::class)
                ->can(Auth::user(), 'quotes.manage', Auth::user()->currentOrganization),
        ])->layout('layouts.provider-company');
    }
}
