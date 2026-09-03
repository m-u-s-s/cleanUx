<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerStay;
use App\Services\PeerRental\PeerAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LE CATALOGUE DES LOGEMENTS.
 *
 * Il vit à côté de celui des véhicules plutôt que dedans : un voyageur ne filtre pas un studio
 * par sa boîte de vitesses, et une barre de filtres à moitié applicable est pire que deux écrans.
 *
 * LES DATES FILTRENT VRAIMENT. Beaucoup de catalogues affichent tout puis refusent à la
 * réservation ; ici, une annonce déjà prise sur la période demandée ne s'affiche pas — le
 * voyageur ne clique jamais pour rien.
 *
 * @property-read Collection<int, PeerStay> $logements
 */
#[Layout('layouts.app')]
class PeerStayCatalogue extends Component
{
    #[Url(as: 'ou', except: '')]
    public string $lieu = '';

    #[Url(as: 'du', except: '')]
    public string $debut = '';

    #[Url(as: 'au', except: '')]
    public string $fin = '';

    #[Url(as: 'voyageurs', except: 1)]
    public int $voyageurs = 1;

    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'espace', except: '')]
    public string $espace = '';

    #[Url(as: 'prixmax', except: '')]
    public string $prixMax = '';

    #[Url(as: 'chambres', except: '')]
    public string $chambresMin = '';

    /** @var list<string> */
    #[Url(as: 'equipements', except: [])]
    public array $equipements = [];

    #[Url(as: 'immediat', except: false)]
    public bool $reservationImmediate = false;

    #[Url(as: 'tri', except: 'recent')]
    public string $tri = 'recent';

    public function reinitialiserLesFiltres(): void
    {
        $this->reset([
            'lieu', 'debut', 'fin', 'voyageurs', 'type', 'espace',
            'prixMax', 'chambresMin', 'equipements', 'reservationImmediate', 'tri',
        ]);
    }

    /** @return array{debut: Carbon, fin: Carbon}|null */
    #[Computed]
    public function periode(): ?array
    {
        if ($this->debut === '' || $this->fin === '') {
            return null;
        }

        try {
            $debut = Carbon::parse($this->debut)->startOfDay();
            $fin = Carbon::parse($this->fin)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $fin->greaterThan($debut) ? ['debut' => $debut, 'fin' => $fin] : null;
    }

    /** @return Collection<int, PeerStay> */
    #[Computed]
    public function logements(): Collection
    {
        $logements = PeerStay::query()
            ->with('media')
            ->publiees()
            ->when($this->lieu !== '', function (Builder $q) {
                $terme = '%'.$this->lieu.'%';
                $q->where(fn (Builder $s) => $s->where('city', 'like', $terme)->orWhere('postal_code', 'like', $terme));
            })
            ->when($this->type !== '', fn (Builder $q) => $q->where('property_type', $this->type))
            ->when($this->espace !== '', fn (Builder $q) => $q->where('space_type', $this->espace))
            ->when($this->prixMax !== '', fn (Builder $q) => $q->where('nightly_price_cents', '<=', (int) $this->prixMax * 100))
            ->when($this->chambresMin !== '', fn (Builder $q) => $q->where('bedrooms', '>=', (int) $this->chambresMin))
            ->when($this->reservationImmediate, fn (Builder $q) => $q->where('instant_booking', true))
            // LA CAPACITE FILTRE TOUJOURS : proposer un studio pour deux a une famille de six,
            // c'est lui faire perdre son temps et lui donner une mauvaise impression du catalogue.
            ->where('max_guests', '>=', max(1, $this->voyageurs))
            ->when($this->tri === 'prix', fn (Builder $q) => $q->orderBy('nightly_price_cents'))
            ->when($this->tri === 'prix_desc', fn (Builder $q) => $q->orderByDesc('nightly_price_cents'))
            ->when($this->tri === 'recent', fn (Builder $q) => $q->orderByDesc('published_at'))
            ->limit(60)
            ->get();

        $equipements = array_values(array_filter($this->equipements));

        if ($equipements !== []) {
            // TOUS LES EQUIPEMENTS DEMANDES, pas au moins un : cocher « lave-linge » et « parking »
            // veut dire les deux, sinon le filtre ne sert a rien.
            $logements = $logements->filter(
                fn (PeerStay $l): bool => array_diff($equipements, $l->equipements()) === []
            );
        }

        $periode = $this->periode();

        if ($periode !== null) {
            $disponibilite = app(PeerAvailability::class);

            $logements = $logements->filter(
                fn (PeerStay $l): bool => $disponibilite->estLibre($l, $periode['debut'], $periode['fin'])
            );
        }

        return $logements->values();
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-stay-catalogue');
    }
}
