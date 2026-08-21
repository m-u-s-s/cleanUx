<?php

namespace App\Livewire\ProviderCompany;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProviderAgency;
use App\Services\Inventory\InventoryService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE STOCK DE CONSOMMABLES (E23).
 *
 * Ce suivi se fait aujourd'hui sur un tableur — quand il se fait : personne ne sait ce qui reste
 * dans quelle agence, et on découvre la rupture le matin où une équipe part sans produit.
 *
 * ON NE SAISIT JAMAIS LE STOCK, on déclare un mouvement. Le compteur est le RÉSULTAT des mouvements :
 * dès qu'on peut l'écrire à la main, le registre et le compteur divergent et plus personne ne sait
 * lequel croire. Corriger un écart reste possible — c'est un ajustement, qui exige un motif.
 *
 * VOIR N'EST PAS COMMANDER. `inventory.view` est accordée jusqu'aux exécutants, qui ont besoin de
 * savoir ce qui reste avant de partir ; `inventory.manage` reste aux gestionnaires. Confondre les
 * deux ferait dépendre l'exactitude du stock de qui a ouvert l'écran en dernier.
 */
class InventoryCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $nom = '';

    public string $unite = 'unité';

    public int $seuil = 5;

    public ?int $agenceId = null;

    public ?string $coutUnitaire = null;

    /** Le mouvement en cours de saisie, par article. */
    public int $quantite = 1;

    public string $motif = '';

    #[Locked]
    public ?string $refus = null;

    #[Locked]
    public ?int $articleOuvertId = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'inventory.view', $acteur->currentOrganization),
            403
        );
    }

    public function creerLArticle(): void
    {
        $this->autoriserLaGestion();

        $this->validate([
            'nom' => ['required', 'string', 'max:120'],
            'unite' => ['required', 'string', 'max:20'],
            'seuil' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $orgId = (int) Auth::user()->current_organization_id;

        // L'agence vient du navigateur : on ne la retient que si elle appartient à cette société.
        $agenceLegitime = $this->agenceId !== null
            && ProviderAgency::query()
                ->where('provider_organization_id', $orgId)
                ->whereKey($this->agenceId)
                ->exists();

        InventoryItem::query()->create([
            'organization_account_id' => $orgId,
            'provider_agency_id' => $agenceLegitime ? $this->agenceId : null,
            'name' => $this->nom,
            'unit' => $this->unite,
            // Le stock part de zéro et monte par une RÉCEPTION : le saisir à la création ferait du
            // compteur une valeur écrite, sans mouvement qui l'explique.
            'quantity' => 0,
            'reorder_threshold' => $this->seuil,
            'unit_cost_cents' => is_numeric($this->coutUnitaire)
                ? (int) round(((float) $this->coutUnitaire) * 100)
                : null,
            'is_active' => true,
        ]);

        $this->reset(['nom', 'coutUnitaire', 'refus']);
        $this->unite = 'unité';
        $this->seuil = 5;
    }

    public function ouvrirLArticle(int $itemId): void
    {
        $this->articleOuvertId = $this->articleDeLaSociete($itemId)?->id;
    }

    public function receptionner(int $itemId): void
    {
        $this->autoriserLaGestion();
        $this->mouvement($itemId, 'reception');
    }

    /** Prélever pour une intervention — le geste de terrain (F7) fait pareil depuis l'application. */
    public function consommer(int $itemId): void
    {
        $this->autoriserLaGestion();
        $this->mouvement($itemId, 'consommation');
    }

    public function ajuster(int $itemId): void
    {
        $this->autoriserLaGestion();
        $this->mouvement($itemId, 'ajustement');
    }

    private function mouvement(int $itemId, string $type): void
    {
        $article = $this->articleDeLaSociete($itemId);

        if ($article === null) {
            return;
        }

        try {
            $service = app(InventoryService::class);

            match ($type) {
                'reception' => $service->receptionner($article, $this->quantite, Auth::user(), $this->motif ?: null),
                'consommation' => $service->consommer($article, $this->quantite, Auth::user(), null, $this->motif ?: null),
                // Le motif est OBLIGATOIRE ici, et seulement ici : c'est le mouvement qu'on relira
                // dans six mois en se demandant ce qui s'est passé.
                default => $service->ajuster($article, $this->quantite, Auth::user(), $this->motif),
            };

            $this->reset(['motif', 'refus']);
            $this->quantite = 1;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    private function articleDeLaSociete(int $itemId): ?InventoryItem
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return InventoryItem::query()
            ->where('organization_account_id', $orgId)
            ->find($itemId);
    }

    private function autoriserLaGestion(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'inventory.manage', $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $orgId = (int) Auth::user()->current_organization_id;

        return view('livewire.provider-company.inventory-center', [
            'articles' => InventoryItem::query()
                ->where('organization_account_id', $orgId)
                ->where('is_active', true)
                ->with('agency:id,name')
                ->orderBy('name')
                ->get(),
            'aReappro' => app(InventoryService::class)->aReapprovisionner($orgId),
            'agences' => ProviderAgency::query()
                ->where('provider_organization_id', $orgId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'mouvements' => $this->articleOuvertId === null
                ? collect()
                : InventoryMovement::query()
                    ->where('inventory_item_id', $this->articleOuvertId)
                    ->with('user:id,name')
                    ->latest()
                    ->limit(30)
                    ->get(),
            'peutGerer' => app(PermissionService::class)
                ->can(Auth::user(), 'inventory.manage', Auth::user()->currentOrganization),
        ])->layout('layouts.provider-company');
    }
}
