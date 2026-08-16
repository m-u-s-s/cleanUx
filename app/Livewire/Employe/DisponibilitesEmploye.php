<?php

namespace App\Livewire\Employe;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Availability\AvailabilityEditor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LES DISPONIBILITÉS DU PRESTATAIRE — sur la table que TOUT LE MONDE lit.
 *
 * Cette page écrivait dans `disponibilites`, une table à zéro ligne que rien ne lisait hors
 * d'elle-même. Le moteur de disponibilité, le scoring de dispatch, l'application mobile, la
 * console admin et la synchronisation Google Calendar lisent `availability_slots` et
 * `availability_exceptions`. Un prestataire saisissait donc son horaire ici et restait
 * introuvable à la planification — sans erreur, sans message, sans rien.
 *
 * DEUX NOTIONS, ET ELLES NE SE MÉLANGENT PLUS :
 *
 *   - la SEMAINE TYPE, récurrente : « tous les mardis de 8 h à 17 h ». C'est elle qui rend
 *     joignable, semaine après semaine, sans rien ressaisir.
 *   - les EXCEPTIONS, datées : « ce jeudi-là je suis fermé ». Elles l'emportent sur la semaine
 *     pour ce jour-là, et ne la modifient pas.
 *
 * L'ancien bouton « Bloquer » confondait les deux : il SUPPRIMAIT les créneaux du jour. Pour
 * fermer un mardi, il fermait tous les mardis à venir — et sans confirmation, sans retour
 * possible, sur une page où un prestataire sans créneau croyait ne rien risquer.
 */
class DisponibilitesEmploye extends Component
{
    /** Le lundi de la semaine affichée. Chaîne pour rester sérialisable par Livewire. */
    public string $weekStart = '';

    public int $weekday = 1;

    public string $heure_debut = '08:00';

    public string $heure_fin = '17:00';

    /**
     * L'identifiant du créneau en cours d'édition.
     *
     * `#[Locked]` : une propriété publique est modifiable depuis le navigateur par `$set`, et
     * celle-ci désigne la ligne qu'on va écrire. Les requêtes la portent déjà toutes sur
     * `provider_user_id = Auth::id()`, mais un garde qui repose sur DEUX conditions dont une est
     * retournable finit par céder au premier refactor.
     */
    #[Locked]
    public ?int $editingId = null;

    public string $exceptionDate = '';

    public string $exceptionReason = '';

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek()->toDateString();
        $this->exceptionDate = now()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->weekday = 1;
        $this->heure_debut = '08:00';
        $this->heure_fin = '17:00';
        $this->resetErrorBag();
    }

    // ─── Semaine type ────────────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        /*
         * L'ECRITURE VIT DANS `AvailabilityEditor`, PAS ICI.
         *
         * L'administration fait exactement les memes gestes sur les memes tables depuis son
         * propre ecran. Deux implementations divergeraient : la regle de chevauchement se
         * durcirait d'un cote, la fermeture d'un jour se remettrait a supprimer des creneaux de
         * l'autre, et plus personne ne saurait laquelle fait foi.
         */
        $resultat = app(AvailabilityEditor::class)->saveSlot(
            $this->utilisateur(),
            $this->weekday,
            $this->heure_debut,
            $this->heure_fin,
            $this->editingId,
        );

        if ($resultat === AvailabilityEditor::CHEVAUCHEMENT) {
            $this->addError('heure_debut', __('Ce creneau en recouvre un autre le meme jour.'));

            return;
        }

        $message = $this->editingId ? __('Creneau mis a jour.') : __('Creneau ajoute.');

        $this->resetForm();
        $this->dispatch('toast', $message, 'success');
    }

    public function edit(int $id): void
    {
        $slot = AvailabilitySlot::where('provider_user_id', Auth::id())->findOrFail($id);

        $this->editingId = $slot->id;
        $this->weekday = (int) $slot->weekday;
        $this->heure_debut = substr((string) $slot->start_time, 0, 5);
        $this->heure_fin = substr((string) $slot->end_time, 0, 5);
    }

    public function delete(int $id): void
    {
        app(AvailabilityEditor::class)->deleteSlot($this->utilisateur(), $id);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('toast', __('Creneau retire de la semaine type.'), 'success');
    }

    // ─── Exceptions datées ───────────────────────────────────────────────────────────────────

    /**
     * FERMER UN JOUR, C'EST POSER UNE EXCEPTION — pas effacer la semaine.
     *
     * L'ancien `blockDay()` supprimait tous les créneaux de la date. Comme les créneaux sont
     * RÉCURRENTS, fermer « mardi 18 » fermait en réalité tous les mardis, pour toujours. Et le
     * geste était irréversible : rien ne permettait de revenir en arrière.
     */
    public function closeDay(string $date): void
    {
        app(AvailabilityEditor::class)->closeDay($this->utilisateur(), $date, $this->exceptionReason);

        $this->exceptionReason = '';
        $this->dispatch('toast', __('Journee fermee. Votre semaine type reste inchangee.'), 'success');
    }

    public function reopenDay(int $id): void
    {
        app(AvailabilityEditor::class)->reopenDay($this->utilisateur(), $id);

        $this->dispatch('toast', __('Journee rouverte.'), 'success');
    }

    /** Le compte connecte, type : l'editeur veut un `User`, pas un identifiant. */
    protected function utilisateur(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    // ─── Lectures ────────────────────────────────────────────────────────────────────────────

    /**
     * Les sept jours de la semaine affichée, du lundi au dimanche.
     *
     * @return Collection<int, Carbon>
     */
    public function weekDays(): Collection
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();

        return collect(range(0, 6))->map(fn ($offset) => $start->copy()->addDays($offset));
    }

    /**
     * La semaine type, groupée par jour de la semaine.
     *
     * `groupBy` sur une collection Eloquent rend des clés (int|string) : c'est le type réel,
     * pas une approximation à corriger côté vue.
     *
     * Le conteneur EXTÉRIEUR est une collection ordinaire — `Eloquent\Collection` exige que ses
     * éléments soient des modèles, or ici ce sont des collections. L'objet rendu reste bien une
     * collection Eloquent, qui en hérite : le type déclaré est le vrai, pas un élargissement.
     *
     * @return Collection<int|string, EloquentCollection<int, AvailabilitySlot>>
     */
    public function slotsByWeekday(): Collection
    {
        return AvailabilitySlot::query()
            ->where('provider_user_id', Auth::id())
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (AvailabilitySlot $slot) => (int) $slot->weekday);
    }

    /**
     * Les jours fermés de la semaine affichée, indexés par date.
     *
     * @return Collection<string, AvailabilityException>
     */
    public function closedDays(): Collection
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();

        return AvailabilityException::query()
            ->where('provider_user_id', Auth::id())
            ->where('exception_type', AvailabilityException::TYPE_CLOSED)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfWeek()->toDateString()])
            ->get()
            ->keyBy(fn (AvailabilityException $e) => $e->date->format('Y-m-d'));
    }

    public function render(): View
    {
        return view('livewire.employe.disponibilites-employe', [
            'weekDays' => $this->weekDays(),
            'slotsByWeekday' => $this->slotsByWeekday(),
            'closedDays' => $this->closedDays(),
        ]);
    }
}
