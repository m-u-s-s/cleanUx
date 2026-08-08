<?php

namespace App\Livewire\ProviderCompany;

use App\Events\MissionStatusUpdated;
use App\Jobs\Missions\AutoAssignerMissionsJob;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\ProviderSiteAssignment;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Missions\ReassignmentPolicy;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, Mission> $missions
 * @property-read Collection<int, OrganizationMember> $availableWorkers
 * @property-read Collection<int, OrganizationContract> $partnerContracts
 * @property-read array<int, bool> $disponibilites
 * @property-read bool $modeContinuActif
 */
class DispatchCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $filterDate = '';

    public string $filterStatus = '';

    public int $assigningId = 0;

    public ?int $assigneeId = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $user->currentOrganization),
            403
        );

        $this->filterDate = now()->format('Y-m-d');
    }

    public function getMissionsProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return Mission::where('provider_organization_id', $orgId)
            ->when($this->filterDate, fn ($q) => $q->whereDate('planned_start_at', $this->filterDate)
            )
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus)
            )
            ->with([
                'assignments.provider:id,name,profile_photo_path',
                'bookingSite:id,name,address,city,lat,lng',
            ])
            ->orderBy('planned_start_at')
            ->get();
    }

    public function getAvailableWorkersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->whereIn('role', ['worker', 'team_lead'])
            ->where('status', 'active')
            ->with('user:id,name,profile_photo_path')
            ->get();
    }

    /**
     * Contrats-cadres B2B où MON org est le partenaire prestataire (lecture seule).
     * Isolation stricte : filtré sur provider_organization_id = current_organization_id.
     *
     * @return Collection<int, OrganizationContract>
     */
    public function getPartnerContractsProperty(): Collection
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return OrganizationContract::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationContract::query()
            ->where('provider_organization_id', $orgId)
            ->with(['organizationAccount:id,name', 'rateCards'])
            ->orderByDesc('effective_from')
            ->get();
    }

    /**
     * Ouvrir l'assignation — en proposant le référent du site, quand il y en a un.
     *
     * C'est la raison d'être du référent : une société qui dessert vingt immeubles y place des
     * habitués, et cette connaissance ne servait à rien tant que le répartiteur repartait d'une
     * liste alphabétique à chaque mission. La désignation devient utile ici, ou nulle part.
     *
     * SUGGESTION, PAS DÉCISION : le champ reste modifiable, et l'assignation passe par les mêmes
     * gardes qu'avant. Un référent absent ou déjà pris se remplace d'un geste.
     *
     * Sans référent, on laisse `null` plutôt que de proposer un premier venu — une suggestion au
     * hasard se fait accepter par habitude, ce qui est pire que pas de suggestion du tout.
     */
    public function startAssign(int $missionId): void
    {
        $this->assigningId = $missionId;
        $this->assigneeId = null;

        $orgId = Auth::user()?->organizationContextId();

        if (! $orgId) {
            return;
        }

        // Le site est relu DEPUIS la mission, elle-même scopée sur notre organisation : un
        // identifiant reçu ne désigne jamais seul une ressource.
        $siteId = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->whereKey($missionId)
            ->value('organization_site_id');

        if (! $siteId) {
            return;
        }

        /*
         * Scopé sur NOTRE organisation : deux prestataires peuvent desservir le même immeuble, et
         * suggérer l'employé d'un concurrent serait à la fois absurde et une fuite.
         */
        $this->assigneeId = ProviderSiteAssignment::query()
            ->where('provider_organization_id', $orgId)
            ->where('organization_site_id', $siteId)
            ->where('role', ProviderSiteAssignment::ROLE_LEAD)
            ->value('user_id');
    }

    public function confirmAssign(): void
    {
        if (! $this->assigningId || ! $this->assigneeId) {
            return;
        }

        $user = Auth::user();

        /*
         * LA PERMISSION SE VÉRIFIE AU MOMENT D'AGIR, PAS À L'OUVERTURE DE L'ÉCRAN.
         *
         * `mount()` exige `missions.dispatch` — le droit de CONSULTER le tableau. Rien ne gardait
         * plus rien ensuite, et Livewire ne rejoue pas `mount()` entre deux actions : la
         * vérification avait lieu une fois, puis l'assignation restait ouverte pour toute la durée
         * de vie du composant.
         *
         * `missions.assign` est la clé qui manquait à l'appel — déclarée dans la matrice depuis le
         * début, consultée par personne. La distinction porte un vrai choix de gestion : une
         * société peut vouloir que ses dispatcheurs VOIENT le plan de charge sans redistribuer le
         * travail, et `organization_role_permissions` le lui permet désormais pour de bon.
         */
        $mission = Mission::where('provider_organization_id', $user->current_organization_id)
            ->findOrFail($this->assigningId);

        /*
         * LA GARDE PORTE SUR LA MISSION, PLUS SEULEMENT SUR LA CLÉ.
         *
         * `missions.assign` ouvre la CAPACITÉ ; elle ne dit rien du PÉRIMÈTRE. L'exigence 5 borne le
         * chef d'équipe à SON équipe — ce qu'une matrice de clés ne peut pas exprimer, et ce que le
         * lot 1 avait laissé ouvert en accordant la clé à `team_lead` sans frontière.
         * `ReassignmentPolicy` est la même règle des deux côtés, web et API.
         */
        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner($user, $mission),
            403
        );

        $worker = OrganizationMember::where('organization_account_id', $user->current_organization_id)
            ->where('user_id', $this->assigneeId)
            ->firstOrFail();

        // Créer l'assignment (colonnes réelles de mission_assignments :
        // user_id, role_on_mission, assignment_status, assigned_at —
        // pas de provider_user_id ni assigned_by ; cf. MissionAssignmentStatusService).
        /*
         * RÉASSIGNER, C'EST AUSSI DÉSASSIGNER (corrigé le 2026-08-05).
         *
         * On ne créait que le nouvel assignment : la mission finissait avec DEUX lignes actives,
         * et `lead_provider_user_id` continuait de désigner le travailleur remplacé. En cascade,
         * le tableau de bord affichait l'ancien (il lit `leadProvider`), l'autorisation Reverb
         * `mission.{id}` lui restait ouverte, et le suivi de trajet le visait encore.
         *
         * On libère donc les leads actifs des AUTRES personnes avant d'installer le nouveau.
         * `reassigned` — et non `cancelled` — parce que l'historique doit distinguer un
         * remplacement d'un abandon.
         */
        /*
         * LA RÈGLE D'ASSIGNATION VIT DÉSORMAIS DANS UN SERVICE PARTAGÉ (2026-08-06).
         *
         * L'API mobile en a besoin à son tour. La recopier aurait créé deux versions d'une règle
         * délicate — libérer les leads actifs des autres, puis synchroniser
         * `lead_provider_user_id` — vouées à diverger au premier ajustement.
         */
        app(MissionAssignmentService::class)->assigner($mission, $worker, $user->id);

        // Broadcast du changement de statut
        broadcast(new MissionStatusUpdated($mission));

        $this->assigningId = 0;
        $this->assigneeId = null;
    }

    /**
     * Confier la mission à une ÉQUIPE entière.
     *
     * C'est le geste ordinaire d'une société : on n'envoie pas une personne dans un immeuble de dix
     * étages. Il n'existait sur aucune surface — composer une équipe demandait un responsable puis
     * N renforts, un par un, sans que rien n'enregistre QUELLE équipe.
     */
    public function assignerLEquipe(int $missionId, int $fieldTeamId): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        $mission = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($missionId);

        if ($mission === null) {
            return;
        }

        abort_unless(
            app(ReassignmentPolicy::class)->peutReassigner($user, $mission),
            403
        );

        // Scopé dans la requête : l'équipe d'une autre société n'est jamais chargée.
        $equipe = FieldTeam::query()
            ->where('organization_account_id', $orgId)
            ->find($fieldTeamId);

        if ($equipe === null) {
            return;
        }

        if (app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe, $user->id)) {
            broadcast(new MissionStatusUpdated($mission->fresh()));
        }
    }

    /**
     * Ajouter un renfort sur une mission.
     *
     * Même permission et mêmes gardes que l'assignation : les deux redistribuent du travail, et
     * seule la place occupée diffère. Les deux identifiants viennent du client et ne sont crus ni
     * l'un ni l'autre.
     */
    public function ajouterRenfort(int $missionId, int $userId): void
    {
        [$mission, $membre] = $this->missionEtMembreSousGarde($missionId, $userId);

        if ($mission === null || $membre === null) {
            return;
        }

        app(MissionAssignmentService::class)->ajouterRenfort($mission, $membre);

        broadcast(new MissionStatusUpdated($mission));
    }

    public function retirerRenfort(int $missionId, int $userId): void
    {
        [$mission, $membre] = $this->missionEtMembreSousGarde($missionId, $userId);

        if ($mission === null || $membre === null) {
            return;
        }

        app(MissionAssignmentService::class)->retirerRenfort($mission, $userId);

        broadcast(new MissionStatusUpdated($mission));
    }

    /**
     * La mission et le membre, ou deux `null`.
     *
     * Rend `null` plutôt que d'échouer bruyamment : la différence entre « introuvable » et
     * « refusé » dirait déjà si la mission existe et si cette personne appartient à une autre
     * société.
     *
     * @return array{0: Mission|null, 1: OrganizationMember|null}
     */
    private function missionEtMembreSousGarde(int $missionId, int $userId): array
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return [null, null];
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.assign', $orgId),
            403
        );

        $mission = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->find($missionId);

        $membre = OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        return [$mission, $membre];
    }

    /**
     * Qui est déjà pris sur le créneau de la mission qu'on s'apprête à confier.
     *
     * INDICATIF, JAMAIS BLOQUANT. Un répartiteur qui connaît son équipe passe outre pour de bonnes
     * raisons — un échange entre collègues, une heure supplémentaire consentie, un client qui a
     * décalé sans prévenir. L'outil l'informe ; il ne décide pas à sa place, sans quoi il faudrait
     * lui donner un moyen de forcer, et ce moyen deviendrait le geste ordinaire.
     *
     * POURQUOI PAS `AvailabilityService::isAvailable()`, QUE LE CAHIER DES CHARGES DÉSIGNAIT.
     * Mesuré : il rend `false` pour un employé sans créneaux déclarés, et coûte ~200 ms par
     * personne. Or les créneaux sont un concept de prestataire INDÉPENDANT — celui qui publie ses
     * disponibilités sur la place de marché. Un salarié de société ne s'en déclare aucun : c'est
     * son patron qui le planifie. L'indicateur aurait donc affiché « indisponible » sur toute
     * l'équipe, en permanence, et fait attendre l'écran plusieurs secondes pour cela.
     *
     * La question qu'un répartiteur pose réellement est « cette personne est-elle déjà prise à
     * cette heure-là », et la réponse vit dans SES PROPRES missions. Une seule requête pour toute
     * l'équipe, pas une par personne.
     *
     * @return array<int, bool> user_id => libre
     */
    public function getDisponibilitesProperty(): array
    {
        if (! $this->assigningId) {
            return [];
        }

        $mission = $this->missions->firstWhere('id', $this->assigningId);
        $debut = $mission?->planned_start_at;

        if ($mission === null || $debut === null) {
            return [];
        }

        /*
         * LA REQUÊTE A ÉTÉ EXTRAITE, PAS RECOPIÉE.
         *
         * Le moteur d'auto-assignation et l'API mobile posent la même question ; deux
         * implémentations de « libre » auraient divergé, et la divergence se serait vue du côté
         * le plus permissif — quelqu'un envoyé à deux endroits à la même heure.
         */
        return app(WorkerAvailabilityService::class)->libresPour(
            organisationId: (int) Auth::user()->current_organization_id,
            debut: $debut,
            fin: $mission->planned_end_at,
            userIds: $this->availableWorkers
                ->pluck('user_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            // Se réassigner à soi-même n'est pas un conflit.
            exclureMissionId: $mission->id,
        );
    }

    /**
     * « Assigner tout ce qui n'a personne ».
     *
     * EN FILE, PAS ICI. Deux cents missions, c'est deux cents décisions et autant de notifications :
     * les traiter pendant que le navigateur attend donnerait un écran figé puis un timeout, avec le
     * travail à moitié fait et rien pour dire où il s'est arrêté. Le job est `ShouldBeUnique` par
     * société — un double-clic ne lance pas deux passages sur le même arriéré.
     */
    public function autoAssignerTout(): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $orgId),
            403
        );

        AutoAssignerMissionsJob::dispatch((int) $orgId, $user->id);
    }

    /**
     * Le MODE CONTINU : toute nouvelle mission de la société est auto-assignée.
     *
     * Réglage de société, pas préférence d'écran : il agit sur des missions créées quand personne
     * n'est devant l'application. C'est aussi pourquoi il est faux par défaut — aucune société ne
     * doit se mettre à distribuer son travail toute seule du fait d'un déploiement.
     */
    public function basculerLeModeContinu(): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        if (! $orgId) {
            return;
        }

        abort_unless(
            app(PermissionService::class)->can($user, 'missions.dispatch', $orgId),
            403
        );

        $organisation = OrganizationAccount::find($orgId);

        if ($organisation === null) {
            return;
        }

        $organisation->update(['auto_assign_enabled' => ! $organisation->auto_assign_enabled]);
    }

    public function getModeContinuActifProperty(): bool
    {
        $orgId = Auth::user()?->organizationContextId();

        return $orgId !== null
            && (bool) OrganizationAccount::query()->whereKey($orgId)->value('auto_assign_enabled');
    }

    public function cancelAssign(): void
    {
        $this->assigningId = 0;
        $this->assigneeId = null;
    }

    #[On('echo-private:mission.{assigningId},MissionStatusUpdated')]
    public function onMissionUpdate(): void
    {
        // Rafraîchit automatiquement la vue quand une mission change
    }

    public function render()
    {
        return view('livewire.provider-company.dispatch-center', [
            'missions' => $this->missions,
            'availableWorkers' => $this->availableWorkers,
            'partnerContracts' => $this->partnerContracts,
            'disponibilites' => $this->disponibilites,
        ])->layout('layouts.provider-company');
    }
}
