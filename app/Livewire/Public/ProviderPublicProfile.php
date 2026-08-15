<?php

namespace App\Livewire\Public;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class ProviderPublicProfile extends Component
{
    use WithPagination;

    public User $provider;

    public ?int $filterMinRating = null;

    public string $sort = 'recent';

    protected $paginationTheme = 'tailwind';

    public function mount(User $provider): void
    {
        abort_unless($provider->isProvider(), 404);

        $providerProfile = $provider->providerProfile;
        if (! $providerProfile || ! $providerProfile->isActive() || ! $providerProfile->isVerified()) {
            abort(404);
        }

        $this->provider = $provider;
    }

    public function setFilter(?int $minRating): void
    {
        $this->filterMinRating = $minRating;
        $this->resetPage();
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['recent', 'highest', 'lowest'], true) ? $sort : 'recent';
        $this->resetPage();
    }

    // Page de vitrine : on y consulte un prestataire, le bouton flottant y mène au bon geste.
    // Il est désormais explicite — la mise en page ne l'affiche plus d'office, pour ne pas le poser
    // par-dessus les formulaires d'authentification qu'elle sert aussi.
    #[Layout('layouts.guest', ['cta' => true])]
    public function render(): View
    {
        $profile = $this->provider->providerProfile;

        $ratingsQuery = Feedback::query()
            ->publiclyVisible()
            ->forProvider($this->provider->id)
            ->with(['client:id,name', 'rendezVous:id,booking_reference']);

        if ($this->filterMinRating) {
            $ratingsQuery->where(function ($q) {
                $q->where('rating', '>=', $this->filterMinRating)
                    ->orWhere('note', '>=', $this->filterMinRating);
            });
        }

        $ratingsQuery = match ($this->sort) {
            'highest' => $ratingsQuery->orderByDesc('rating')->orderByDesc('note')->latest('published_at'),
            'lowest' => $ratingsQuery->orderBy('rating')->orderBy('note')->latest('published_at'),
            default => $ratingsQuery->latest('published_at'),
        };

        return view('livewire.public.provider-public-profile', [
            'profile' => $profile,
            'ratings' => $ratingsQuery->paginate(10),
        ]);
    }
}
