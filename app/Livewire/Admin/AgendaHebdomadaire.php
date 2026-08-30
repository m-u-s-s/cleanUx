<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\MissionReplanifieeNotification;
use App\Services\Booking\SmartDispatchService;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
// `Illuminate\Support\Carbon` et non `Carbon\Carbon` : c'est le type dans lequel Eloquent
// caste `bookings.date`, et le seul que la colonne accepte en ecriture.
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class AgendaHebdomadaire extends Component
{
    use EnforcesAdminAccess;

    /** Les statuts qu'une administration pose depuis cet ecran. */
    private const STATUTS_ADMIN = [
        BookingStatus::EN_ATTENTE,
        BookingStatus::CONFIRME,
        BookingStatus::TERMINE,
    ];

    #[Reactive]
    public string $semaine = '';

    #[Reactive]
    public string $employeId = '';

    #[Reactive]
    public string $status = '';

    #[Reactive]
    public string $priorite = '';

    #[Reactive]
    public string $recherche = '';

    #[Reactive]
    public string $focusDate = '';

    /**
     * LE RENDEZ-VOUS OUVERT — `#[Locked]`, ET C'EST LA GARDE.
     *
     * Sans elle, le navigateur peut retourner la propriete par `$set` et designer une
     * reservation hors de la zone d'un administrateur zone : seules les actions
     * verifieraient la portee, l'affichage, lui, montrerait la fiche.
     */
    #[Locked]
    public ?int $rdvOuvert = null;

    public string $affectationEmploye = '';

    public string $affectationDate = '';

    public string $affectationHeure = '';

    protected function weekStart(): Carbon
    {
        return $this->semaine !== ''
            ? Carbon::parse($this->semaine)->startOfWeek()
            : now()->startOfWeek();
    }

    protected function weekEnd(): Carbon
    {
        return $this->weekStart()->copy()->endOfWeek();
    }

    /**
     * LA PORTEE DE L'ADMINISTRATEUR, EN SQL, ET POUR TOUT LE MONDE.
     *
     * Elle etait appliquee APRES `get()`, pour l'affichage seulement. Les actions ouvertes
     * par la modale prennent un identifiant venu du navigateur : sans la meme porte, un
     * administrateur de zone replanifiait la reservation d'une zone voisine.
     *
     * @return Builder<Booking>
     */
    protected function requeteScopee(): Builder
    {
        /** @var User|null $user */
        $user = auth()->user();

        return Booking::query()
            ->when(
                $user?->isZoneScopedAdmin() && filled($user->managed_service_zone_id),
                fn (Builder $q) => $q->where('service_zone_id', (int) $user->managed_service_zone_id)
            );
    }

    /** Le rendez-vous ouvert dans la modale, ou null. */
    public function getRdvSelectionneProperty(): ?Booking
    {
        if ($this->rdvOuvert === null) {
            return null;
        }

        return $this->requeteScopee()
            ->with(['client', 'employe', 'serviceCatalog', 'serviceZone', 'organizationAccount', 'organizationSite', 'missions'])
            ->find($this->rdvOuvert);
    }

    /** @return Collection<int, User> */
    public function getEmployesProperty(): Collection
    {
        return User::query()->providers()->where('is_active', true)->orderBy('name')->get();
    }

    public function ouvrirRdv(int $id): void
    {
        $rdv = $this->requeteScopee()->find($id);

        if (! $rdv) {
            $this->dispatch('toast', 'Ce rendez-vous est hors de votre périmètre.', 'error');

            return;
        }

        $this->resetErrorBag();
        $this->rdvOuvert = $rdv->id;
        $this->affectationEmploye = (string) ($rdv->intervenantId() ?? '');
        $this->affectationDate = $rdv->date?->format('Y-m-d') ?? (string) $rdv->date;
        $this->affectationHeure = substr((string) $rdv->heure, 0, 5);
    }

    public function fermerRdv(): void
    {
        $this->reset(['rdvOuvert', 'affectationEmploye', 'affectationDate', 'affectationHeure']);
        $this->resetErrorBag();
    }

    /**
     * AFFECTER ET REPLANIFIER — un seul geste, parce que c'est une seule decision.
     *
     * Le conflit se mesure sur le creneau VOULU, tampon de 30 minutes compris : c'est la
     * regle deja appliquee par le tableau de bord, et la seule qui evite de poser deux
     * missions sur le meme intervenant.
     */
    public function enregistrerAffectation(): void
    {
        $rdv = $this->rdvVerrouille();

        $this->validate([
            'affectationEmploye' => ['required', 'exists:users,id'],
            'affectationDate' => ['required', 'date'],
            'affectationHeure' => ['required', 'date_format:H:i'],
        ], attributes: [
            'affectationEmploye' => 'intervenant',
            'affectationDate' => 'date',
            'affectationHeure' => 'heure',
        ]);

        if ($this->creneauEnConflit($rdv, (int) $this->affectationEmploye)) {
            $this->addError('affectationHeure', 'Conflit : cet intervenant a déjà une mission sur ce créneau.');

            return;
        }

        // `->name` et non `?->name` : a gauche d'un `??`, PHP n'emet rien sur un acces de
        // propriete a null. C'est l'idiome du depot, et la regle stricte l'exige.
        $ancienIntervenant = $rdv->intervenant()->name ?? 'Aucun';
        $ancienneDate = $rdv->date?->format('Y-m-d') ?? (string) $rdv->date;
        $ancienneHeure = substr((string) $rdv->heure, 0, 5);

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->employe_id = (int) $this->affectationEmploye;
        // `bookings.date` est castee en Carbon : lui affecter la chaine du formulaire laissait
        // le modele avec deux types possibles selon le chemin d'ecriture.
        $rdv->date = Carbon::parse($this->affectationDate);
        $rdv->heure = $this->affectationHeure;
        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        // `intervenantId()` lit D'ABORD `missions.lead_provider_user_id` : ecrire la seule
        // colonne `employe_id` laisserait l'agenda afficher l'ancien intervenant.
        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($rdv->fresh());

        $rdv->refresh()->load(['client', 'employe', 'missions']);

        if ($rdv->client) {
            $rdv->client->notify(new MissionReplanifieeNotification($rdv, $ancienIntervenant, $ancienneDate, $ancienneHeure));
        }

        ActivityLogger::log('rdv_replanifie_depuis_agenda', $rdv, [
            'ancien_intervenant' => $ancienIntervenant,
            'nouvel_intervenant' => $rdv->intervenant()?->name,
            'ancienne_date' => $ancienneDate,
            'ancienne_heure' => $ancienneHeure,
            'nouvelle_date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'nouvelle_heure' => substr((string) $rdv->heure, 0, 5),
        ]);

        $this->apresAction('Rendez-vous mis à jour.');
    }

    /** L'affectation automatique — le meme moteur que la page Missions. */
    public function dispatchAutomatique(): void
    {
        $rdv = $this->rdvVerrouille();

        $employe = app(SmartDispatchService::class)->assignBestEmployee($rdv);

        if (! $employe) {
            $this->dispatch('toast', 'Aucun intervenant disponible pour ce rendez-vous.', 'error');

            return;
        }

        $ancienId = $rdv->intervenantId();

        $rdv->forceFill([
            'employe_id' => $employe->id,
            'status' => BookingStatus::CONFIRME,
        ])->save();

        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($rdv->fresh());

        ActivityLogger::log('rdv_auto_dispatched', $rdv, [
            'old_employee_id' => $ancienId,
            'new_employee_id' => $employe->id,
            'new_employee_name' => $employe->name,
        ]);

        $this->affectationEmploye = (string) $employe->id;

        $this->apresAction('Rendez-vous assigné à '.$employe->name.'.');
    }

    public function changerStatut(string $statut): void
    {
        $rdv = $this->rdvVerrouille();

        // La liste blanche, et non `$statut` tel quel : la valeur vient du navigateur.
        abort_unless(in_array($statut, self::STATUTS_ADMIN, true), 422);

        $ancien = $rdv->status;
        $rdv->forceFill(['status' => $statut])->save();

        ActivityLogger::log('rdv_statut_change_depuis_agenda', $rdv, [
            'ancien_statut' => $ancien,
            'nouveau_statut' => $statut,
        ]);

        $this->apresAction('Statut mis à jour.');
    }

    public function basculerUrgence(): void
    {
        $rdv = $this->rdvVerrouille();

        $nouvelle = $rdv->priorite === 'urgente' ? 'normale' : 'urgente';
        $rdv->forceFill(['priorite' => $nouvelle])->save();

        ActivityLogger::log('rdv_priorite_changee_depuis_agenda', $rdv, [
            'ancienne_priorite' => $rdv->getOriginal('priorite'),
            'nouvelle_priorite' => $nouvelle,
        ]);

        $this->apresAction($nouvelle === 'urgente' ? 'Rendez-vous marqué urgent.' : 'Priorité ramenée à normale.');
    }

    /** La reservation ouverte, relue SOUS la portee — jamais celle que le navigateur annonce. */
    protected function rdvVerrouille(): Booking
    {
        abort_if($this->rdvOuvert === null, 404);

        return $this->requeteScopee()->findOrFail($this->rdvOuvert);
    }

    protected function creneauEnConflit(Booking $rdv, int $employeId): bool
    {
        $tampon = 30;
        $debut = Carbon::parse($this->affectationDate.' '.$this->affectationHeure);
        $fin = $debut->copy()->addMinutes(($rdv->duree ?? $rdv->estimated_duration_minutes ?? 90) + $tampon);

        return $this->requeteScopee()
            ->whereKeyNot($rdv->id)
            ->intervenantEst($employeId)
            ->whereDate('date', $this->affectationDate)
            ->whereIn('status', BookingStatus::active())
            ->get()
            ->contains(function (Booking $autre) use ($debut, $fin, $tampon) {
                $autreDebut = $this->momentDe($autre);
                $autreFin = $autreDebut->copy()
                    ->addMinutes(($autre->duree ?? $autre->estimated_duration_minutes ?? 90) + $tampon);

                return $debut < $autreFin && $fin > $autreDebut;
            });
    }

    /**
     * LE DEBUT D'UNE RESERVATION, SANS CONCATENER LA DATE BRUTE.
     *
     * `bookings.date` est castee en Carbon : `$rdv->date.' '.$rdv->heure` produit
     * « 2026-08-25 00:00:00 10:00:00 », que Carbon refuse — « Double time specification ».
     * Le defaut ne se declenchait QUE sur une detection de conflit, donc jamais sur le
     * chemin nominal.
     */
    protected function momentDe(Booking $rdv): Carbon
    {
        $jour = $rdv->date instanceof \DateTimeInterface
            ? $rdv->date->format('Y-m-d')
            : substr((string) $rdv->date, 0, 10);

        return Carbon::parse($jour.' '.substr((string) $rdv->heure, 0, 8));
    }

    /** Les compteurs de la page vivent chez le parent : sans cet evenement ils mentent. */
    protected function apresAction(string $message): void
    {
        $this->dispatch('toast', $message, 'success');
        $this->dispatch('planning-mis-a-jour');
    }

    public function render()
    {
        $start = $this->weekStart();
        $end = $this->weekEnd();

        // `missions` : le compteur de non-assignés interroge l'intervenant ligne par ligne.
        $rdvs = $this->requeteScopee()
            ->with(['employe', 'client', 'serviceCatalog', 'serviceZone', 'organizationAccount', 'organizationSite', 'missions'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($this->employeId !== '', fn ($q) => $q->intervenantEst((int) $this->employeId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->priorite !== '', fn ($q) => $q->where('priorite', $this->priorite))
            ->when($this->recherche !== '', fn ($q) => $q->searchStructured($this->recherche))
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        $rdvsGrouped = $rdvs->groupBy(fn (Booking $rdv) => optional($rdv->date)->toDateString() ?? (string) $rdv->date);
        $focusDate = $this->focusDate !== '' ? Carbon::parse($this->focusDate)->toDateString() : now()->toDateString();

        $jours = collect();

        foreach (range(0, 6) as $i) {
            $jour = $start->copy()->addDays($i);
            // `RendezVous` n'existe plus : c'est `Booking` qui porte le rendez-vous depuis la
            // fusion des deux tables. L'annotation était restée, et désignait une classe absente.
            /** @var Collection<int, Booking> $rdvsJour */
            $rdvsJour = $rdvsGrouped->get($jour->toDateString(), collect());
            $totalMinutes = $rdvsJour->sum(fn (Booking $rdv) => ($rdv->duree ?? $rdv->estimated_duration_minutes ?? 90) + 30);

            $jours->push([
                'label' => $jour->translatedFormat('l d/m'),
                'short_label' => $jour->translatedFormat('D d/m'),
                'date' => $jour->toDateString(),
                'is_focus' => $jour->toDateString() === $focusDate,
                'is_today' => $jour->isToday(),
                'rdvs' => $rdvsJour,
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 1),
                'active_count' => $rdvsJour->whereIn('status', BookingStatus::active())->count(),
                'urgent_count' => $rdvsJour->where('priorite', 'urgente')->count(),
                'unassigned_count' => $rdvsJour->filter(fn (Booking $rdv) => blank($rdv->intervenantId()))->count(),
            ]);
        }

        return view('livewire.admin.agenda-hebdomadaire', [
            'jours' => $jours,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
