<?php

namespace App\Livewire\ProviderCompany;

use App\Models\LeaveRequest;
use App\Models\OrganizationMember;
use App\Models\Shift;
use App\Services\PermissionService;
use App\Services\Workforce\LeaveService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * QUI TRAVAILLE QUAND (E19), ET QUI S'ABSENTE (E21).
 *
 * Les deux répondent à la même question et se contredisent si on les sépare : un planning qui ignore
 * les congés envoie une course le premier jour des vacances, et des congés qui ignorent le planning
 * ne bloquent rien. Ils vivent donc sur le même écran, et surtout sur la même lecture —
 * `WorkerAvailabilityService` consulte les deux.
 *
 * LE PLANNING NE S'IMPOSE QUE S'IL EXISTE. Une société qui n'a rien saisi garde le comportement
 * d'avant : sans cette précaution, la mise en service rendrait toute l'équipe indisponible du jour
 * au lendemain — la fonctionnalité créerait la panne qu'elle devait éviter.
 *
 * PUBLIER EST UN GESTE À PART. Un brouillon se corrige ; un planning publié engage. Assigner sur du
 * brouillon reviendrait à faire travailler quelqu'un sur un horaire que personne ne lui a communiqué.
 */
class WorkforcePlanning extends Component
{
    use EnforcesActiveOrgMembership;

    /** Le lundi de la semaine affichée, au format `Y-m-d`. */
    public string $semaine = '';

    public ?int $shiftUserId = null;

    public string $shiftDebut = '';

    public string $shiftFin = '';

    public string $congeDebut = '';

    public string $congeFin = '';

    public string $congeType = 'paid';

    public string $congeMotif = '';

    /** Message de refus renvoyé par le domaine — une règle métier n'est pas une erreur de saisie. */
    #[Locked]
    public ?string $refus = null;

    /**
     * L'ÉCRAN EST OUVERT À TOUT MEMBRE, ET SON CONTENU EST FILTRÉ.
     *
     * Le fermer sur `team.view` serait le réflexe, et il serait faux : poser SON congé est un geste
     * de salarié, pas de responsable. Un exécutant qui ne peut pas atteindre le formulaire pose son
     * absence par SMS à son chef — c'est-à-dire nulle part, et la répartition continue de lui
     * envoyer des courses pendant ses vacances.
     *
     * Ce qui se garde, ce sont les LECTURES ÉLARGIES et les écritures : voir le planning des autres
     * demande `team.view`, planifier ou trancher demande `team.manage`.
     */
    public function mount(): void
    {
        $this->semaine = Carbon::now()->startOfWeek()->toDateString();
    }

    public function semainePrecedente(): void
    {
        $this->semaine = $this->lundi()->subWeek()->toDateString();
    }

    public function semaineSuivante(): void
    {
        $this->semaine = $this->lundi()->addWeek()->toDateString();
    }

    /**
     * Ajouter un créneau au planning — en BROUILLON.
     *
     * On ne publie pas en créant : une semaine se construit ligne à ligne, et publier à chaque
     * ajout communiquerait un planning à moitié fait.
     */
    public function ajouterUnCreneau(): void
    {
        $this->autoriserLaGestion();

        $this->validate([
            'shiftUserId' => ['required', 'integer'],
            'shiftDebut' => ['required', 'date'],
            'shiftFin' => ['required', 'date', 'after:shiftDebut'],
        ]);

        $orgId = (int) Auth::user()->current_organization_id;

        // La cible doit être un membre ACTIF : sans cette garde, on planifierait l'employé d'une
        // autre société — qui verrait apparaître un créneau chez un employeur qui n'est pas le sien.
        $estDeLaMaison = OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('user_id', $this->shiftUserId)
            ->where('status', 'active')
            ->exists();

        if (! $estDeLaMaison) {
            $this->refus = 'Cette personne n’appartient pas à votre société.';

            return;
        }

        Shift::query()->create([
            'organization_account_id' => $orgId,
            'user_id' => $this->shiftUserId,
            'starts_at' => Carbon::parse($this->shiftDebut),
            'ends_at' => Carbon::parse($this->shiftFin),
            'status' => Shift::STATUS_PLANNED,
        ]);

        $this->reset(['shiftDebut', 'shiftFin', 'refus']);
    }

    /** Publier toute la semaine affichée : c'est le geste qui rend l'équipe assignable. */
    public function publierLaSemaine(): void
    {
        $this->autoriserLaGestion();

        Shift::query()
            ->where('organization_account_id', Auth::user()->current_organization_id)
            ->where('status', Shift::STATUS_PLANNED)
            ->whereBetween('starts_at', [$this->lundi(), $this->lundi()->endOfWeek()])
            ->update(['status' => Shift::STATUS_PUBLISHED]);
    }

    public function annulerLeCreneau(int $shiftId): void
    {
        $this->autoriserLaGestion();

        // Scopé sur l'organisation active : un identifiant forgé ne doit pas atteindre le planning
        // d'une autre société.
        Shift::query()
            ->where('organization_account_id', Auth::user()->current_organization_id)
            ->whereKey($shiftId)
            ->update(['status' => Shift::STATUS_CANCELLED]);
    }

    // ── Les absences ─────────────────────────────────────────────────────────

    /** Poser SA propre absence. On ne pose pas un congé pour quelqu'un d'autre. */
    public function poserUneAbsence(): void
    {
        $this->validate([
            'congeDebut' => ['required', 'date'],
            'congeFin' => ['required', 'date'],
        ]);

        try {
            app(LeaveService::class)->demander(
                Auth::user(),
                (int) Auth::user()->current_organization_id,
                Carbon::parse($this->congeDebut),
                Carbon::parse($this->congeFin),
                $this->congeType,
                $this->congeMotif !== '' ? $this->congeMotif : null,
            );

            $this->reset(['congeDebut', 'congeFin', 'congeMotif', 'refus']);
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function statuerSurLAbsence(int $demandeId, bool $approuve): void
    {
        $this->autoriserLaGestion();

        $demande = LeaveRequest::query()
            ->where('organization_account_id', Auth::user()->current_organization_id)
            ->find($demandeId);

        if ($demande === null) {
            return;
        }

        try {
            app(LeaveService::class)->statuer($demande, Auth::user(), $approuve);
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    private function lundi(): Carbon
    {
        return Carbon::parse($this->semaine)->startOfWeek();
    }

    private function autoriserLaGestion(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $acteur = Auth::user();
        $orgId = (int) $acteur->current_organization_id;
        $lundi = $this->lundi();
        $dimanche = $lundi->copy()->endOfWeek();

        $permissions = app(PermissionService::class);
        $peutVoirLEquipe = $permissions->can($acteur, 'team.view', $acteur->currentOrganization);
        $peutGerer = $permissions->can($acteur, 'team.manage', $acteur->currentOrganization);

        return view('livewire.provider-company.workforce-planning', [
            'lundi' => $lundi,
            'creneaux' => Shift::query()
                ->where('organization_account_id', $orgId)
                ->where('status', '!=', Shift::STATUS_CANCELLED)
                ->whereBetween('starts_at', [$lundi, $dimanche])
                // Sans `team.view`, on ne voit QUE son propre planning : celui des collègues est
                // une information d'exploitation, pas un annuaire.
                ->when(! $peutVoirLEquipe, fn ($q) => $q->where('user_id', $acteur->id))
                ->with('user:id,name')
                ->orderBy('starts_at')
                ->get(),
            /*
             * LES ABSENCES DES AUTRES SE GARDENT PLUS SÉVÈREMENT QUE LES CRÉNEAUX. Une absence dit
             * la maladie, la garde d'enfant, l'accompagnement d'un proche : les exposer à toute la
             * société ferait de la pose de congé un aveu, et personne n'en poserait.
             */
            'absences' => app(LeaveService::class)
                ->surLaPeriode($orgId, $lundi, $dimanche)
                ->when(! $peutGerer, fn ($c) => $c->where('user_id', $acteur->id))
                ->values(),
            'enAttente' => $peutGerer
                ? LeaveRequest::query()
                    ->where('organization_account_id', $orgId)
                    ->where('status', LeaveRequest::STATUS_PENDING)
                    ->with('user:id,name')
                    ->orderBy('starts_on')
                    ->get()
                : collect(),
            'collegues' => $peutGerer
                ? OrganizationMember::query()
                    ->where('organization_account_id', $orgId)
                    ->where('status', 'active')
                    ->with('user:id,name')
                    ->get()
                : collect(),
            'peutGerer' => $peutGerer,
        ])->layout('layouts.provider-company');
    }
}
