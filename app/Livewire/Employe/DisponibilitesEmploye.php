<?php

namespace App\Livewire\Employe;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
         * LE CHEVAUCHEMENT SE VÉRIFIE AUSSI À LA MODIFICATION.
         *
         * L'ancienne version ne testait que la création : éditer un créneau pour le faire recouvrir
         * un autre passait sans un mot. Le créneau en cours d'édition est exclu de la comparaison,
         * sinon il se chevaucherait lui-même.
         */
        $chevauche = AvailabilitySlot::query()
            ->where('provider_user_id', Auth::id())
            ->where('weekday', $this->weekday)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->where('start_time', '<', $this->heure_fin.':00')
            ->where('end_time', '>', $this->heure_debut.':00')
            ->exists();

        if ($chevauche) {
            $this->addError('heure_debut', __('Ce créneau en recouvre un autre le même jour.'));

            return;
        }

        $donnees = [
            'weekday' => $this->weekday,
            'start_time' => $this->heure_debut.':00',
            'end_time' => $this->heure_fin.':00',
        ];

        if ($this->editingId) {
            $slot = AvailabilitySlot::where('provider_user_id', Auth::id())->findOrFail($this->editingId);
            $slot->update($donnees);
            ActivityLogger::log('disponibilite_modifiee', $slot, $donnees);
            $message = __('Créneau mis à jour.');
        } else {
            $slot = AvailabilitySlot::create($donnees + [
                'provider_user_id' => Auth::id(),
                'timezone' => config('availability.default_timezone', config('app.timezone')),
                'is_active' => true,
            ]);
            ActivityLogger::log('disponibilite_creee', $slot, $donnees);
            $message = __('Créneau ajouté.');
        }

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
        $slot = AvailabilitySlot::where('provider_user_id', Auth::id())->findOrFail($id);

        ActivityLogger::log('disponibilite_supprimee', $slot, [
            'weekday' => $slot->weekday,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
        ]);

        $slot->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('toast', __('Créneau retiré de la semaine type.'), 'success');
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
        $jour = Carbon::parse($date)->toDateString();

        /*
         * `whereDate`, PAS `firstOrCreate` SUR UNE EGALITE DE DATE.
         *
         * `date` est caste sur le modele : la colonne porte `2026-08-18 00:00:00` et la recherche
         * comparait la chaine `2026-08-18`. L'egalite echouait a chaque fois, `firstOrCreate`
         * creait donc une exception de plus A CHAQUE clic. Constate au test : deux fermetures du
         * meme jour donnaient deux lignes.
         */
        $exception = AvailabilityException::query()
            ->where('provider_user_id', Auth::id())
            ->where('exception_type', AvailabilityException::TYPE_CLOSED)
            ->whereDate('date', $jour)
            ->first();

        $exception ??= AvailabilityException::create([
            'provider_user_id' => Auth::id(),
            'date' => $jour,
            'exception_type' => AvailabilityException::TYPE_CLOSED,
            'reason' => $this->exceptionReason !== '' ? $this->exceptionReason : null,
        ]);

        ActivityLogger::log('disponibilite_jour_ferme', $exception, ['date' => $jour]);

        $this->exceptionReason = '';
        $this->dispatch('toast', __('Journée fermée. Votre semaine type reste inchangée.'), 'success');
    }

    public function reopenDay(int $id): void
    {
        $exception = AvailabilityException::where('provider_user_id', Auth::id())->findOrFail($id);
        // `date` est casté en Carbon par le modèle : pas d'instanceof défensif à écrire.
        $date = $exception->date->format('Y-m-d');

        ActivityLogger::log('disponibilite_jour_rouvert', $exception, ['date' => $date]);

        $exception->delete();

        $this->dispatch('toast', __('Journée rouverte.'), 'success');
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
