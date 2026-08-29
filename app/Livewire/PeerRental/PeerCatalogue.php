<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerVehicle;
use App\Services\Geo\GeoDistanceService;
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
 * LES VEHICULES DES MEMBRES, PRES DE CHEZ VOUS.
 *
 * La recherche part de DEUX DATES et d'un lieu : un vehicule libre la semaine prochaine mais
 * pris ce week-end n'a rien a faire dans une liste pour ce week-end.
 */
#[Layout('layouts.app')]
class PeerCatalogue extends Component
{
    #[Url(as: 'ou', except: '')]
    public string $lieu = '';

    #[Url(as: 'du', except: '')]
    public string $debut = '';

    #[Url(as: 'au', except: '')]
    public string $fin = '';

    #[Url(as: 'rayon', except: 25)]
    public int $rayonKm = 25;

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    #[Url(as: 'boite', except: '')]
    public string $transmission = '';

    #[Url(as: 'energie', except: '')]
    public string $carburant = '';

    #[Url(as: 'places', except: '')]
    public string $placesMin = '';

    #[Url(as: 'max', except: '')]
    public string $prixMax = '';

    #[Url(as: 'immediat', except: false)]
    public bool $reservationImmediate = false;

    #[Url(as: 'tri', except: 'pertinence')]
    public string $tri = 'pertinence';

    public function reinitialiserLesFiltres(): void
    {
        $this->categorie = '';
        $this->transmission = '';
        $this->carburant = '';
        $this->placesMin = '';
        $this->prixMax = '';
        $this->reservationImmediate = false;
        $this->rayonKm = 25;
    }

    /** @return array{debut: Carbon, fin: Carbon}|null */
    #[Computed]
    public function periode(): ?array
    {
        if ($this->debut === '' || $this->fin === '') {
            return null;
        }

        try {
            $debut = Carbon::parse($this->debut)->setTime(10, 0);
            $fin = Carbon::parse($this->fin)->setTime(10, 0);
        } catch (\Throwable) {
            return null;
        }

        return $fin->greaterThan($debut) ? ['debut' => $debut, 'fin' => $fin] : null;
    }

    /**
     * LE POINT DE DEPART DE LA RECHERCHE.
     *
     * Une ville saisie a la main se resout sur les annonces elles-memes : la plateforme sait
     * ou sont ses vehicules, et une geolocalisation externe pour « Bruxelles » serait un
     * appel reseau pour une reponse qu'elle a deja.
     *
     * @return array{lat: float, lng: float}|null
     */
    #[Computed]
    public function centre(): ?array
    {
        if (trim($this->lieu) === '') {
            return null;
        }

        $reference = PeerVehicle::query()
            ->publiees()
            ->whereNotNull('lat')
            ->where(function (Builder $q): void {
                $q->where('city', 'like', '%'.trim($this->lieu).'%')
                    ->orWhere('postal_code', 'like', trim($this->lieu).'%');
            })
            ->first();

        return $reference === null ? null : ['lat' => (float) $reference->lat, 'lng' => (float) $reference->lng];
    }

    /** @return Collection<int, PeerVehicle> */
    #[Computed]
    public function vehicules(): Collection
    {
        $requete = PeerVehicle::query()
            ->publiees()
            ->with(['media', 'owner:id,name,profile_photo_path'])
            ->when($this->categorie !== '', fn (Builder $q) => $q->where('category', $this->categorie))
            ->when($this->transmission !== '', fn (Builder $q) => $q->where('transmission', $this->transmission))
            ->when($this->carburant !== '', fn (Builder $q) => $q->where('fuel', $this->carburant))
            ->when($this->placesMin !== '', fn (Builder $q) => $q->where('seats', '>=', (int) $this->placesMin))
            ->when($this->prixMax !== '', fn (Builder $q) => $q->where('daily_price_cents', '<=', (int) $this->prixMax * 100))
            ->when($this->reservationImmediate, fn (Builder $q) => $q->where('instant_booking', true));

        $centre = $this->centre();

        $vehicules = $requete->limit(120)->get();

        if ($centre !== null) {
            $distances = app(GeoDistanceService::class);

            $vehicules = $vehicules
                ->map(function (PeerVehicle $v) use ($centre, $distances): PeerVehicle {
                    $v->setAttribute('distance_km', $v->lat === null ? null : round(
                        $distances->haversineKm($centre['lat'], $centre['lng'], (float) $v->lat, (float) $v->lng),
                        1
                    ));

                    return $v;
                })
                ->filter(fn (PeerVehicle $v): bool => $v->getAttribute('distance_km') !== null
                    && $v->getAttribute('distance_km') <= $this->rayonKm);
        }

        // LES DATES FILTRENT EN DERNIER : c'est la mesure la plus chere, on la fait sur le
        // moins de lignes possible.
        $periode = $this->periode();

        if ($periode !== null) {
            $dispo = app(PeerAvailability::class);

            $vehicules = $vehicules->filter(
                fn (PeerVehicle $v): bool => $dispo->estLibre($v, $periode['debut'], $periode['fin'])
            );
        }

        return match ($this->tri) {
            'prix' => $vehicules->sortBy('daily_price_cents')->values(),
            'prix_desc' => $vehicules->sortByDesc('daily_price_cents')->values(),
            'distance' => $vehicules->sortBy(fn (PeerVehicle $v) => $v->getAttribute('distance_km') ?? PHP_INT_MAX)->values(),
            default => $vehicules->sortByDesc('published_at')->values(),
        };
    }

    /** @return list<string> */
    #[Computed]
    public function categories(): array
    {
        return PeerVehicle::query()->publiees()->distinct()->orderBy('category')->pluck('category')->all();
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-catalogue');
    }
}
