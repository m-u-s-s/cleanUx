<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\EnterpriseBookingApproval;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Enterprise\EnterpriseBookingApprovalService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * LA DOUBLE VALIDATION B2B, CONDUITE PLUTOT QUE DEVINEE.
 *
 * Deux defauts la rendaient trompeuse. Le service SORT EN SILENCE quand le statut n'autorise plus
 * le geste — deux administrateurs sur la meme demande, et le second lisait « Validation effectuee »
 * alors que rien ne s'etait produit. Et la note interne etait UNE SEULE propriete partagee par
 * toutes les cartes : saisie sur l'une, elle partait avec le clic sur une autre.
 *
 * L'ecran taisait par ailleurs ce que la table porte : bon de commande, centre de cout, et les
 * trois horodatages qui racontent le parcours de la demande.
 */
class EnterpriseApprovalsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'societe', except: '')]
    public string $organisation = '';

    /**
     * UNE NOTE PAR DEMANDE, indexee par identifiant.
     *
     * @var array<int, string>
     */
    public array $notes = [];

    #[Locked]
    public ?int $selectedApprovalId = null;

    #[Locked]
    public ?int $demandeOuverte = null;

    public string $rejectionReason = '';

    protected $paginationTheme = 'tailwind';

    /**
     * LA CAPACITE EN PLUS DU ROLE. `module_gate` pose `manage-entreprises` sur la route, mais
     * `/livewire/update` ne rejoue aucun middleware : sans cette garde, tout administrateur
     * pouvait valider ou refuser une demande par un simple appel de composant.
     */
    public function boot(): void
    {
        Gate::authorize('manage-entreprises');
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingOrganisation(): void
    {
        $this->resetPage();
    }

    public function reinitialiserLesFiltres(): void
    {
        $this->reset(['search', 'status', 'organisation']);
        $this->resetPage();
    }

    // ── Gestes ─────────────────────────────────────────────────────────────

    public function approveManager(int $approvalId, EnterpriseBookingApprovalService $service): void
    {
        $approval = EnterpriseBookingApproval::findOrFail($approvalId);
        $avant = $approval->status;

        $apres = $service->approveManager($approval, $this->administrateur(), $this->noteDe($approvalId))->status;

        $this->apresUnGeste($approvalId, $avant, $apres,
            'Validation manager effectuée.',
            'Cette demande n’attendait plus la validation manager.');
    }

    public function approveFinance(int $approvalId, EnterpriseBookingApprovalService $service): void
    {
        $approval = EnterpriseBookingApproval::findOrFail($approvalId);
        $avant = $approval->status;

        $apres = $service->approveFinance($approval, $this->administrateur(), $this->noteDe($approvalId))->status;

        $this->apresUnGeste($approvalId, $avant, $apres,
            'Validation finance effectuée.',
            'Cette demande n’attendait plus la validation finance.');
    }

    public function openRejectModal(int $approvalId): void
    {
        $this->selectedApprovalId = $approvalId;
        $this->rejectionReason = '';
    }

    public function closeRejectModal(): void
    {
        $this->selectedApprovalId = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function reject(EnterpriseBookingApprovalService $service): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $approval = EnterpriseBookingApproval::findOrFail($this->selectedApprovalId);
        $avant = $approval->status;

        $apres = $service->reject($approval, $this->administrateur(), $this->rejectionReason)->status;

        $this->closeRejectModal();

        $this->apresUnGeste((int) $approval->id, $avant, $apres,
            'Demande refusée.',
            'Cette demande était déjà close : elle n’a pas été modifiée.');
    }

    /**
     * DIRE CE QUI S'EST REELLEMENT PASSE.
     *
     * Le service sort en silence quand le statut interdit le geste. Sans cette comparaison,
     * l'ecran annonce un succes pour une action qui n'a rien fait.
     */
    private function apresUnGeste(int $approvalId, string $avant, string $apres, string $succes, string $sansEffet): void
    {
        unset($this->reperes);

        if ($avant === $apres) {
            $this->dispatch('toast', $sansEffet, 'warning');

            return;
        }

        unset($this->notes[$approvalId]);
        $this->dispatch('toast', $succes, 'success');
    }

    private function noteDe(int $approvalId): ?string
    {
        $note = trim((string) ($this->notes[$approvalId] ?? ''));

        return $note !== '' ? $note : null;
    }

    private function administrateur(): User
    {
        $utilisateur = Auth::user();

        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }

    // ── Detail ─────────────────────────────────────────────────────────────

    public function ouvrirLaDemande(int $approvalId): void
    {
        $this->demandeOuverte = $approvalId;
    }

    public function fermerLaDemande(): void
    {
        $this->demandeOuverte = null;
    }

    #[Computed]
    public function demandeDetaillee(): ?EnterpriseBookingApproval
    {
        if ($this->demandeOuverte === null) {
            return null;
        }

        return EnterpriseBookingApproval::query()
            ->with(['rendezVous.client', 'rendezVous.serviceCatalog', 'organizationAccount',
                'organizationSite', 'requestedBy', 'managerApprovedBy', 'financeApprovedBy'])
            ->find($this->demandeOuverte);
    }

    // ── Reperes ────────────────────────────────────────────────────────────

    /**
     * Ce que la file d'attente represente, filtres compris.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function reperes(): array
    {
        $base = $this->requeteFiltree();

        $enAttente = (clone $base)->whereIn('status', ['pending_manager', 'pending_finance'])->pluck('rendez_vous_id');

        return [
            'attente_manager' => (clone $base)->where('status', 'pending_manager')->count(),
            'attente_finance' => (clone $base)->where('status', 'pending_finance')->count(),
            'approuvees' => (clone $base)->where('status', 'approved')->count(),
            'refusees' => (clone $base)->where('status', 'rejected')->count(),
            'montant_en_attente' => round((float) Booking::query()
                ->whereIn('id', $enAttente)
                ->sum('devis_estime'), 2),
        ];
    }

    /** @return Collection<int, OrganizationAccount> */
    #[Computed]
    public function organisations(): Collection
    {
        return OrganizationAccount::query()
            ->whereIn('id', EnterpriseBookingApproval::query()->distinct()->pluck('organization_account_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return Builder<EnterpriseBookingApproval> */
    private function requeteFiltree(): Builder
    {
        return EnterpriseBookingApproval::query()
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->organisation !== '', fn (Builder $q) => $q->where('organization_account_id', $this->organisation))
            ->when($this->search !== '', function (Builder $query) {
                $terme = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($terme) {
                    $q->whereHas('rendezVous.client', fn (Builder $c) => $c->where('name', 'like', $terme))
                        ->orWhereHas('organizationAccount', fn (Builder $o) => $o->where('name', 'like', $terme))
                        ->orWhereHas('organizationSite', fn (Builder $s) => $s->where('name', 'like', $terme))
                        ->orWhere('purchase_order_number', 'like', $terme)
                        ->orWhere('cost_center', 'like', $terme);
                });
            });
    }

    public function render(): View
    {
        return view('livewire.admin.enterprise-approvals-center', [
            'approvals' => $this->requeteFiltree()
                ->with([
                    'rendezVous.client',
                    'rendezVous.serviceCatalog',
                    'organizationAccount',
                    'organizationSite',
                    'requestedBy',
                    'managerApprovedBy',
                    'financeApprovedBy',
                ])
                ->latest()
                ->paginate(10),
        ]);
    }
}
