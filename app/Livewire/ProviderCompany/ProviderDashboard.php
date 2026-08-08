<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Services\PermissionService;
use App\Services\Tasks\TaskVisibilityService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read array<string, mixed> $kpis
 * @property-read array<int, array<string, mixed>> $alerts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Mission> $missionsOfDay
 * @property-read Collection<int, array<string, mixed>> $teamStatus
 */
class ProviderDashboard extends Component
{
    use EnforcesActiveOrgMembership;

    public string $period = 'today';

    /**
     * LE TABLEAU DE BORD MONTRE-T-IL LA SOCIÉTÉ, OU SEULEMENT MON TRAVAIL ?
     *
     * Sa seule garde était `isProviderCompanyWorker()` — un test de TYPE DE COMPTE, que tout
     * employé franchit par construction, puisque `OrganizationMembershipService` crée précisément
     * un `ProviderProfile` de type `company_worker` pour chaque membre. Un nettoyeur voyait donc
     * les missions de toute la société, ses retards, et combien de ses collègues n'avaient pas
     * configuré Stripe.
     *
     * `missions.view_all` existait déjà dans la matrice pour dire exactement cela — et rien ne la
     * consultait. Elle est accordée par défaut au propriétaire, au directeur d'opérations, au
     * dispatcheur, au chef d'équipe et au responsable qualité ; refusée au nettoyeur et au lecteur.
     * Une société qui veut ouvrir la vue à ses exécutants le décide chez elle, via
     * `organization_role_permissions`, sans déploiement.
     *
     * Propriété PUBLIQUE et non calculée : la vue en a besoin pour ne pas annoncer « 0 mission
     * aujourd'hui » à qui n'a simplement pas le droit de compter.
     *
     * `#[Locked]` N'EST PAS UNE PRÉCAUTION DE STYLE. Une propriété publique Livewire fait
     * l'aller-retour avec le navigateur, et le client peut demander sa mise à jour — un
     * `$set('peutToutVoir', true)` suffisait à retourner la garde depuis la console, sur la
     * propriété même qui décide si l'on voit les missions de toute la société. L'attribut fait
     * refuser l'écriture côté serveur ; le reste de la classe peut alors s'y fier.
     */
    #[Locked]
    public bool $peutToutVoir = false;

    /**
     * L'effectif et le trombinoscope relèvent de `team.view`, pas de `missions.view_all`.
     *
     * Ce sont deux questions distinctes : combien de missions tournent aujourd'hui, et qui travaille
     * ici. La seconde a déjà sa clé — c'est celle qui garde l'écran Équipe et la case du menu. La
     * faire dépendre de la première aurait ouvert le trombinoscope au responsable qualité, qui
     * suit des rapports, pas des personnes.
     */
    #[Locked]
    public bool $peutVoirLEquipe = false;

    /**
     * Pour les raccourcis du bas de page : un lien vers un écran qu'on ne peut pas ouvrir est un
     * 403 déguisé en invitation. La navbar a déjà été assainie, ces quatre cases y échappaient.
     */
    #[Locked]
    public bool $peutRepartir = false;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->isProviderCompanyWorker(), 403);

        $permissions = app(PermissionService::class);
        $organisation = $user->currentOrganization;

        $this->peutToutVoir = $permissions->can($user, 'missions.view_all', $organisation);
        $this->peutVoirLEquipe = $permissions->can($user, 'team.view', $organisation);
        $this->peutRepartir = $permissions->can($user, 'missions.dispatch', $organisation);
    }

    /**
     * Les missions que l'appelant a le droit de compter.
     *
     * Sans `missions.view_all`, on se limite à CE QU'IL FAIT : les missions dont il est le
     * travailleur désigné. Rendre une liste vide serait plus simple et plus faux — son propre
     * travail le regarde.
     *
     * @return Builder<Mission>
     */
    private function missionsVisibles(): Builder
    {
        $user = Auth::user();

        $requete = Mission::where('provider_organization_id', $user->current_organization_id);

        if (! $this->peutToutVoir) {
            $requete->where('lead_provider_user_id', $user->id);
        }

        return $requete;
    }

    public function getKpisProperty(): array
    {
        $user = Auth::user();
        $orgId = $user->current_organization_id;

        [$from, $to] = $this->periodDates();

        $base = fn () => $this->missionsVisibles();

        return [
            'missions_today' => $base()->whereDate('planned_start_at', today())->count(),
            'missions_active' => $base()->whereIn('status', ['dispatched', 'in_progress'])->count(),
            'missions_done' => $base()->where('status', 'completed')->whereBetween('actual_end_at', [$from, $to])->count(),
            'missions_delayed' => $base()->where('status', '!=', 'completed')->where('planned_start_at', '<', now())->count(),
            /*
             * `null` PLUTÔT QUE `0` : la vue retire la carte au lieu d'afficher un chiffre faux.
             * Un « 0 membre actif » sur l'écran d'un nettoyeur ne serait pas une discrétion, ce
             * serait un mensonge — et il travaille précisément avec les collègues qu'on nie.
             */
            'members_active' => $this->peutVoirLEquipe
                ? OrganizationMember::where('organization_account_id', $orgId)->where('status', 'active')->count()
                : null,
            /*
             * CE COMPTEUR VALAIT `0` EN DUR, avec pour seul commentaire « calculé via Channel si
             * Reverb actif ». Un zéro se lit comme un fait : le gérant en concluait que personne ne
             * lui écrivait, alors que `TeamChannels` savait déjà compter — la requête existait,
             * dix lignes plus loin dans un autre fichier.
             *
             * Trois conditions, et chacune compte : les canaux de MON organisation, ceux dont je
             * suis MEMBRE (un canal privé auquel je n'appartiens pas ne m'est pas « non lu », il ne
             * me regarde pas), et les messages que je n'ai pas écrits — se compter soi-même
             * afficherait un badge à chaque fois qu'on parle.
             */
            'unread_messages' => Message::query()
                ->whereHas('channel', fn ($q) => $q
                    ->where('organization_account_id', $orgId)
                    ->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
                )
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave('readBy', fn ($r) => $r->where('user_id', $user->id))
                ->count(),
            /*
             * LE COMPTEUR SUIT LE TABLEAU. `Task::forOrg()` comptait toutes les tâches de la
             * société, y compris pour qui n'en voit aucune depuis que `TaskVisibilityService`
             * borne le tableau : le chiffre annonçait un travail introuvable une fois la page
             * ouverte. Même règle, même service — ici ce sont MES tâches à faire.
             */
            'pending_tasks' => app(TaskVisibilityService::class)
                ->requetePour($user, $orgId)
                ->todo()
                ->count(),
        ];
    }

    public function getAlertsProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        $alerts = [];

        /*
         * Les alertes sont des alertes de PILOTAGE : « 3 missions en retard », « 2 membres sans
         * Stripe Connect ». La seconde en particulier dit quelque chose de la situation
         * administrative de collègues nommés. Un exécutant n'a rien à en faire, et beaucoup à en
         * déduire.
         */
        if (! $this->peutToutVoir) {
            return [];
        }

        $delayed = Mission::where('provider_organization_id', $orgId)
            ->where('status', '!=', 'completed')
            ->where('planned_start_at', '<', now()->subMinutes(30))
            ->count();

        /*
         * L'ALERTE RESTE, SON LIEN PEUT DISPARAÎTRE. Un chef d'équipe a `missions.view_all` sans
         * `missions.dispatch` : il doit savoir que trois missions sont en retard, et le « Voir → »
         * l'envoyait sur un écran qui lui répond 403. On lui dit la nouvelle sans lui promettre la
         * page.
         */
        if ($delayed > 0) {
            $alerts[] = ['level' => 'red', 'icon' => '🚨',
                'message' => "{$delayed} mission(s) en retard de +30 min",
                'route' => $this->peutRepartir ? 'provider-company.dispatch' : null];
        }

        /*
         * La situation Stripe de collègues nommés est une information d'ÉQUIPE : elle suit
         * `team.view`, comme le trombinoscope, et non le droit de compter les missions.
         */
        if (! $this->peutVoirLEquipe) {
            return $alerts;
        }

        $noStripe = OrganizationMember::where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->whereHas('user.providerProfile', fn ($q) => $q->where('stripe_connect_status', '!=', 'active')
            )->count();

        if ($noStripe > 0) {
            $alerts[] = ['level' => 'orange', 'icon' => '💳',
                'message' => "{$noStripe} membre(s) sans Stripe Connect",
                'route' => 'provider-company.team'];
        }

        return $alerts;
    }

    public function getMissionsOfDayProperty()
    {
        return $this->missionsVisibles()
            ->whereDate('planned_start_at', today())
            /*
             * `assignedWorker` N'EXISTE PAS sur Mission (corrigé le 2026-08-05) : le modèle
             * expose `leadProvider()` — le travailleur assigné, via `lead_provider_user_id` — et
             * `assignments()`. Le charger levait `RelationNotFoundException` à chaque rendu
             * comportant une mission du jour : page blanche pour toute société en activité.
             *
             * Un seul nom pour une seule chose : on lit désormais `leadProvider`.
             */
            ->with(['leadProvider:id,name,profile_photo_path'])
            ->orderBy('planned_start_at')
            ->limit(10)
            ->get();
    }

    /**
     * QUI TRAVAILLE ICI, ET DANS QUEL ÉTAT — un trombinoscope, pas un indicateur.
     *
     * Il n'avait aucune garde : noms, photos et sous-rôles de toute la société s'affichaient sur le
     * tableau de bord d'un nettoyeur. Le sous-rôle en particulier dit qui commande qui, ce qu'un
     * exécutant n'a pas à lire dans un panneau latéral.
     *
     * Le filtre est dans la REQUÊTE : sans `team.view`, aucun membre n'est chargé — plutôt que
     * chargés puis masqués par la vue, où le prochain rendu les aurait ramenés.
     */
    public function getTeamStatusProperty()
    {
        if (! $this->peutVoirLEquipe) {
            return collect();
        }

        return OrganizationMember::where('organization_account_id', Auth::user()->current_organization_id)
            ->where('status', 'active')
            ->with(['user:id,name,profile_photo_path', 'user.providerProfile'])
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->user->name,
                'avatar' => $m->user->profile_photo_url,
                'role' => $m->roleLabel(),
                'status' => 'available', // À enrichir avec GPS
            ]);
    }

    private function periodDates(): array
    {
        return match ($this->period) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    public function render()
    {
        return view('livewire.provider-company.provider-dashboard', [
            'kpis' => $this->kpis,
            'alerts' => $this->alerts,
            'missionsDay' => $this->missionsOfDay,
            'teamStatus' => $this->teamStatus,
        ])->layout('layouts.provider-company');
    }
}
