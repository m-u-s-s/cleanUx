<?php

namespace App\Livewire\Admin;

use App\Models\CommissionRule;
use App\Models\CommissionRuleRevision;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Services\Commission\ConseillerDeCommission;
use App\Services\Commission\ContexteDeCommission;
use App\Services\Commission\GestionDesCommissions;
use App\Services\Commission\ResolveurDeCommission;
use App\Services\Commission\TauxDeCommission;
use App\Services\Payments\CommissionService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE CENTRE DES COMMISSIONS — régler, simuler, comprendre.
 *
 * RÉSERVÉ AU TITULAIRE DU SIÈGE. Un taux de commission décide de ce que gagnent des milliers de
 * prestataires : ce n'est pas une capacité qu'on accorde, c'est la propriété de la plateforme.
 * La garde est dans `boot()` — `/livewire/update` ne rejoue aucun middleware de route, et une
 * garde posée au premier rendu laisserait les ACTIONS ouvertes.
 *
 * TROIS ONGLETS, TROIS QUESTIONS :
 *   — quelles règles existent, et laquelle gagne ?
 *   — que se passe-t-il si je règle CE cas à CE taux ? (le simulateur, avant d'écrire)
 *   — que disent les chiffres, et que devrais-je faire ? (le conseiller)
 *
 * LE SIMULATEUR N'EST PAS UN CONFORT. Sans lui, on découvre qu'une règle en masque une autre en
 * regardant une facture, un mois plus tard.
 *
 * @property-read Collection<int, CommissionRule> $regles
 */
#[Layout('layouts.app')]
class CentreDesCommissions extends Component
{
    use EnforcesAdminAccess;

    public string $onglet = 'regles';

    public ?string $message = null;

    public ?string $erreur = null;

    // ── La règle en cours d'édition ────────────────────────────────────────
    #[Locked]
    public ?int $regleEnEdition = null;

    public string $libelle = '';

    public string $module = CommissionRule::MODULE_PRESTATION;

    public string $typeDeBien = '';

    public ?int $metier = null;

    public ?int $zone = null;

    public ?int $dureeMinimum = null;

    public string $pourcentage = '';

    public ?int $plancherCents = null;

    public string $debut = '';

    public string $fin = '';

    public int $priorite = 0;

    public string $noteInterne = '';

    // ── Le simulateur ──────────────────────────────────────────────────────
    public string $simModule = CommissionRule::MODULE_PRESTATION;

    public string $simTypeDeBien = '';

    public ?int $simMetier = null;

    public ?int $simZone = null;

    public ?int $simDuree = null;

    public int $simMontantEuros = 100;

    public function boot(): void
    {
        // SEUL LE TITULAIRE DU SIÈGE, ET À CHAQUE REQUÊTE.
        abort_unless(auth()->user()?->isSuperAdmin() === true, 403);
    }

    /** @return Collection<int, CommissionRule> */
    #[Computed]
    public function regles(): Collection
    {
        return CommissionRule::query()
            ->with(['trade', 'serviceZone'])
            ->orderByDesc('is_active')
            ->orderBy('module')
            ->orderByDesc('priority')
            ->get();
    }

    /** @return Collection<int, Trade> */
    #[Computed]
    public function metiers(): Collection
    {
        return Trade::query()->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, ServiceZone> */
    #[Computed]
    public function zones(): Collection
    {
        return ServiceZone::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * LE SIMULATEUR : ce qui s'appliquerait, et ce que ça donne sur un montant réel.
     *
     * @return array{taux: TauxDeCommission, applicables: Collection<int, CommissionRule>, partage: array<string, mixed>}
     */
    #[Computed]
    public function simulation(): array
    {
        $contexte = new ContexteDeCommission(
            module: $this->simModule,
            typeDeBien: $this->simTypeDeBien === '' ? null : $this->simTypeDeBien,
            tradeId: $this->simMetier,
            zoneId: $this->simZone,
            dureeJours: $this->simDuree,
        );

        $resolveur = app(ResolveurDeCommission::class);

        return [
            'taux' => $resolveur->pour($contexte),
            'applicables' => $resolveur->reglesApplicables($contexte),
            'partage' => app(CommissionService::class)->calculateForAmount(
                max(0, $this->simMontantEuros) * 100,
                null,
                null,
                null,
                $contexte,
            ),
        ];
    }

    /** @return list<array{ton: string, titre: string, constat: string, geste: string, trade_id: int|null}> */
    #[Computed]
    public function conseils(): array
    {
        return app(ConseillerDeCommission::class)->conseils();
    }

    /**
     * @return Collection<int, array{trade_id: int, metier: string, volume: int,
     *     commission_cents: int, volume_affaires_cents: int, taux_effectif: float,
     *     taux_regle: float, annulees: int, part_annulee: float, sans_prestataire: int,
     *     part_sans_prestataire: float}>
     */
    #[Computed]
    public function lectureParMetier(): Collection
    {
        return app(ConseillerDeCommission::class)->parMetier();
    }

    /** @return Collection<int, CommissionRuleRevision> */
    #[Computed]
    public function historique(): Collection
    {
        return CommissionRuleRevision::query()
            ->with(['acteur:id,name', 'regle:id,label'])
            ->latest()
            ->limit(50)
            ->get();
    }

    // ── Les gestes ─────────────────────────────────────────────────────────

    public function nouvelleRegle(): void
    {
        $this->reset([
            'regleEnEdition', 'libelle', 'typeDeBien', 'metier', 'zone', 'dureeMinimum',
            'pourcentage', 'plancherCents', 'debut', 'fin', 'priorite', 'noteInterne',
        ]);
        $this->module = CommissionRule::MODULE_PRESTATION;
        $this->onglet = 'regles';
    }

    public function editerLaRegle(int $regleId): void
    {
        $regle = CommissionRule::findOrFail($regleId);

        $this->regleEnEdition = $regle->id;
        $this->libelle = (string) $regle->label;
        $this->module = (string) ($regle->module ?? CommissionRule::MODULE_PRESTATION);
        $this->typeDeBien = (string) $regle->asset_type;
        $this->metier = $regle->trade_id;
        $this->zone = $regle->service_zone_id;
        $this->dureeMinimum = $regle->min_duration_days;
        $this->pourcentage = (string) $regle->percent;
        $this->plancherCents = $regle->min_cents;
        $this->debut = $regle->starts_on?->toDateString() ?? '';
        $this->fin = $regle->ends_on?->toDateString() ?? '';
        $this->priorite = (int) $regle->priority;
        $this->noteInterne = (string) $regle->note;
        $this->onglet = 'regles';
    }

    public function enregistrerLaRegle(): void
    {
        $this->message = $this->erreur = null;

        $this->validate([
            'libelle' => ['required', 'string', 'max:120'],
            'module' => ['required', 'in:'.implode(',', array_keys(CommissionRule::MODULES))],
            'pourcentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'plancherCents' => ['nullable', 'integer', 'min:0'],
            'dureeMinimum' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'priorite' => ['required', 'integer', 'min:0', 'max:999'],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
        ]);

        $valeurs = [
            'label' => $this->libelle,
            'note' => $this->noteInterne ?: null,
            'module' => $this->module,
            'asset_type' => $this->typeDeBien ?: null,
            'trade_id' => $this->metier,
            'service_zone_id' => $this->zone,
            'min_duration_days' => $this->dureeMinimum,
            'starts_on' => $this->debut ?: null,
            'ends_on' => $this->fin ?: null,
            'percent' => (float) $this->pourcentage,
            'min_cents' => $this->plancherCents,
            'priority' => $this->priorite,
        ];

        $service = app(GestionDesCommissions::class);

        try {
            if ($this->regleEnEdition === null) {
                $service->creer(auth()->user(), $valeurs);
            } else {
                $service->modifier(auth()->user(), CommissionRule::findOrFail($this->regleEnEdition), $valeurs);
            }
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->nouvelleRegle();
        $this->message = __('Règle enregistrée. Elle s’applique au prochain devis.');
        $this->oublierLesLectures();
    }

    public function basculerLaRegle(int $regleId): void
    {
        $this->erreur = null;
        $regle = CommissionRule::findOrFail($regleId);

        try {
            app(GestionDesCommissions::class)->modifier(auth()->user(), $regle, [
                'label' => $regle->label,
                'module' => $regle->module,
                'percent' => (float) $regle->percent,
                'is_active' => ! $regle->is_active,
            ]);
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->message = $regle->fresh()?->is_active ? __('Règle réactivée.') : __('Règle suspendue.');
        $this->oublierLesLectures();
    }

    public function supprimerLaRegle(int $regleId): void
    {
        $this->erreur = null;

        try {
            app(GestionDesCommissions::class)->supprimer(auth()->user(), CommissionRule::findOrFail($regleId));
        } catch (DomainException $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $this->nouvelleRegle();
        $this->message = __('Règle supprimée. Les missions déjà conclues gardent leur taux.');
        $this->oublierLesLectures();
    }

    private function oublierLesLectures(): void
    {
        unset($this->regles, $this->simulation, $this->conseils, $this->lectureParMetier, $this->historique);
    }

    public function render(): View
    {
        return view('livewire.admin.centre-des-commissions');
    }
}
