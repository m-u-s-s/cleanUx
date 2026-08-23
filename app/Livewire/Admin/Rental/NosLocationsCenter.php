<?php

namespace App\Livewire\Admin\Rental;

use App\Models\RentalBooking;
use App\Models\RentalPickupPoint;
use App\Models\RentalVehicle;
use App\Models\RentalVehicleMedia;
use App\Services\Rental\RentalBookingService;
use App\Support\International\Devise;
use App\Support\International\DeviseParPays;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** NOS LOCATIONS — TOUT CE QUE L'ADMINISTRATEUR PILOTE, AU MÊME ENDROIT. */
#[Layout('layouts.app')]
class NosLocationsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithFileUploads;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    /** parc | medias | agences | locations */
    public string $tab = 'parc';

    public string $recherche = '';

    public string $filtreStatut = '';

    // ── Fiche véhicule ───────────────────────────────────────────────────

    /** L'identifiant en cours d'édition. */
    #[Locked]
    public ?int $vehiculeEnEdition = null;

    /** @var array<string, mixed> */
    public array $fiche = [];

    /** @var array<int, UploadedFile> */
    public array $photos = [];

    /** @var array<int, UploadedFile> */
    public array $rotation = [];

    /**
     * Le fichier glTF en cours de televersement.
     *
     * @var mixed
     */
    public $modele3d = null;

    #[Locked]
    public ?int $vehiculePourMedias = null;

    // ── Fiche agence ─────────────────────────────────────────────────────

    #[Locked]
    public ?int $agenceEnEdition = null;

    /** @var array<string, mixed> */
    public array $agence = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->reinitialiserLaFiche();
        $this->reinitialiserLAgence();
    }

    /** LA CAPACITÉ EST VÉRIFIÉE SUR LE COMPOSANT, PAS SEULEMENT DANS LE MENU. */
    public function boot(): void
    {
        abort_unless(Gate::allows('manage-rentals'), 403);
    }

    // ── Le parc ──────────────────────────────────────────────────────────

    public function editerLeVehicule(int $id): void
    {
        $vehicule = RentalVehicle::query()->findOrFail($id);

        $this->vehiculeEnEdition = $vehicule->id;
        $this->fiche = [
            'brand' => $vehicule->brand,
            'model' => $vehicule->model,
            'plate' => $vehicule->plate,
            'year' => $vehicule->year,
            'color' => $vehicule->color,
            'category' => $vehicule->category,
            'transmission' => $vehicule->transmission,
            'fuel' => $vehicule->fuel,
            'seats' => $vehicule->seats,
            'doors' => $vehicule->doors,
            'luggage' => $vehicule->luggage,
            'features' => $vehicule->features ?? [],
            'daily_price' => $this->enUnites($vehicule->daily_price_cents),
            'deposit' => $this->enUnites($vehicule->deposit_cents),
            'waiver_daily_price' => $this->enUnites($vehicule->waiver_daily_price_cents),
            'waiver_deposit' => $this->enUnites($vehicule->waiver_deposit_cents),
            'included_km_per_day' => $vehicule->included_km_per_day,
            'extra_km_price' => $this->enUnites($vehicule->extra_km_price_cents),
            'min_rental_days' => $vehicule->min_rental_days,
            'max_rental_days' => $vehicule->max_rental_days,
            'min_driver_age' => $vehicule->min_driver_age,
            'min_license_years' => $vehicule->min_license_years,
            'pickup_point_id' => $vehicule->pickup_point_id,
            'description' => $vehicule->description,
            'sort_order' => $vehicule->sort_order,
        ];
    }

    public function enregistrerLeVehicule(): void
    {
        $valide = $this->validate($this->reglesDuVehicule(), [], $this->libellesDuVehicule())['fiche'];

        $attributs = [
            'brand' => $valide['brand'],
            'model' => $valide['model'],
            'plate' => $valide['plate'] ?? null,
            'year' => $valide['year'] ?? null,
            'color' => $valide['color'] ?? null,
            'category' => $valide['category'],
            'transmission' => $valide['transmission'],
            'fuel' => $valide['fuel'],
            'seats' => (int) $valide['seats'],
            'doors' => (int) $valide['doors'],
            'luggage' => (int) $valide['luggage'],
            'features' => array_values(array_filter((array) ($valide['features'] ?? []))),
            'daily_price_cents' => $this->enCentimes($valide['daily_price']),
            'deposit_cents' => $this->enCentimes($valide['deposit'] ?? 0),
            'waiver_daily_price_cents' => $this->enCentimes($valide['waiver_daily_price'] ?? 0),
            'waiver_deposit_cents' => $this->enCentimes($valide['waiver_deposit'] ?? 0),
            'included_km_per_day' => $valide['included_km_per_day'] ?? null,
            'extra_km_price_cents' => $this->enCentimes($valide['extra_km_price'] ?? 0),
            'min_rental_days' => (int) ($valide['min_rental_days'] ?? 1),
            'max_rental_days' => $valide['max_rental_days'] ?? null,
            'min_driver_age' => (int) ($valide['min_driver_age'] ?? 21),
            'min_license_years' => (int) ($valide['min_license_years'] ?? 1),
            'pickup_point_id' => $valide['pickup_point_id'] ?? null,
            'description' => $valide['description'] ?? null,
            'sort_order' => (int) ($valide['sort_order'] ?? 0),
        ];

        if ($this->vehiculeEnEdition !== null) {
            RentalVehicle::query()->findOrFail($this->vehiculeEnEdition)->update($attributs);
            $this->flash = 'Véhicule mis à jour.';
        } else {
            // UNE VOITURE NEUVE ARRIVE FERMÉE.
            RentalVehicle::query()->create($attributs + [
                'code' => RentalVehicle::genererUnCode(),
                'currency' => $this->deviseDeLAgence($attributs['pickup_point_id']),
                'is_active' => false,
            ]);
            $this->flash = 'Véhicule créé — il reste fermé tant que vous ne l’ouvrez pas.';
        }

        $this->reinitialiserLaFiche();
    }

    public function basculerLActivation(int $id): void
    {
        $vehicule = RentalVehicle::query()->findOrFail($id);
        $vehicule->update(['is_active' => ! $vehicule->is_active]);

        $this->flash = $vehicule->is_active
            ? $vehicule->nomComplet().' est en vitrine.'
            : $vehicule->nomComplet().' est retiré du catalogue.';
    }

    /** SUPPRIMER UN VÉHICULE NE DÉTRUIT PAS SON HISTOIRE. */
    public function supprimerLeVehicule(int $id): void
    {
        $vehicule = RentalVehicle::query()->findOrFail($id);

        if ($vehicule->bookings()->quiBloque()->exists()) {
            $this->flash = 'Impossible : ce véhicule porte une location en cours.';

            return;
        }

        $vehicule->delete();
        $this->flash = 'Véhicule retiré du parc.';
    }

    public function reinitialiserLaFiche(): void
    {
        $this->vehiculeEnEdition = null;
        $this->fiche = [
            'brand' => '', 'model' => '', 'plate' => '', 'year' => null, 'color' => '',
            'category' => 'citadine', 'transmission' => RentalVehicle::TRANSMISSION_MANUELLE,
            'fuel' => 'essence', 'seats' => 5, 'doors' => 5, 'luggage' => 2, 'features' => [],
            'daily_price' => '', 'deposit' => '', 'waiver_daily_price' => '', 'waiver_deposit' => '',
            'included_km_per_day' => null, 'extra_km_price' => '',
            'min_rental_days' => 1, 'max_rental_days' => null,
            'min_driver_age' => 21, 'min_license_years' => 1,
            'pickup_point_id' => null, 'description' => '', 'sort_order' => 0,
        ];
        $this->resetValidation();
    }

    // ── Les médias ───────────────────────────────────────────────────────

    public function choisirLeVehiculePourMedias(int $id): void
    {
        $this->vehiculePourMedias = RentalVehicle::query()->findOrFail($id)->id;
    }

    /** LES PHOTOS PASSENT PAR LA RÈGLE PARTAGÉE, comme partout ailleurs. */
    public function ajouterDesPhotos(): void
    {
        $this->exigerUnVehiculePourMedias();

        $this->validate(
            ['photos.*' => ImagesTeleversees::regles(tailleMaxKo: 8192)],
            ['photos.*.mimes' => 'Seules les photos sont acceptées (JPEG, PNG, WebP, HEIC).'],
        );

        $vehicule = RentalVehicle::query()->findOrFail($this->vehiculePourMedias);
        $depart = (int) ($vehicule->media()->where('type', RentalVehicleMedia::TYPE_GALERIE)->max('position') ?? -1);

        foreach ($this->photos as $index => $photo) {
            $vehicule->media()->create([
                'type' => RentalVehicleMedia::TYPE_GALERIE,
                'path' => $photo->store('rental/'.$vehicule->code, 'public'),
                'position' => $depart + 1 + $index,
            ]);
        }

        $this->photos = [];
        $this->flash = 'Photos ajoutées.';
    }

    /** LA SÉQUENCE DE ROTATION — L'ORDRE DES FICHIERS EST LE SENS DE ROTATION. */
    public function remplacerLaRotation(): void
    {
        $this->exigerUnVehiculePourMedias();

        $this->validate(
            ['rotation.*' => ImagesTeleversees::regles(tailleMaxKo: 4096)],
            ['rotation.*.mimes' => 'La rotation se compose de photos (JPEG, PNG, WebP, HEIC).'],
        );

        $vehicule = RentalVehicle::query()->findOrFail($this->vehiculePourMedias);

        $this->effacerLesMedias($vehicule, RentalVehicleMedia::TYPE_ROTATION);

        foreach (array_values($this->rotation) as $position => $image) {
            $vehicule->media()->create([
                'type' => RentalVehicleMedia::TYPE_ROTATION,
                'path' => $image->store('rental/'.$vehicule->code.'/spin', 'public'),
                'position' => $position,
            ]);
        }

        $this->rotation = [];
        $this->flash = 'Rotation 360° enregistrée.';
    }

    /** LE MODÈLE 3D — un fichier par véhicule, et seulement pour ceux qui en ont un. */
    public function remplacerLeModele3d(): void
    {
        $this->exigerUnVehiculePourMedias();

        $this->validate([
            'modele3d' => ['required', 'file', 'mimes:glb,gltf', 'max:32768'],
        ], [
            'modele3d.mimes' => 'Le modèle doit être un fichier glTF (.glb ou .gltf).',
            'modele3d.max' => 'Le modèle dépasse 32 Mo — au-delà, la fiche devient inutilisable sur mobile.',
        ]);

        $vehicule = RentalVehicle::query()->findOrFail($this->vehiculePourMedias);

        $this->effacerLesMedias($vehicule, RentalVehicleMedia::TYPE_MODELE_3D);

        $vehicule->media()->create([
            'type' => RentalVehicleMedia::TYPE_MODELE_3D,
            'path' => $this->modele3d->store('rental/'.$vehicule->code.'/3d', 'public'),
            'position' => 0,
        ]);

        $this->modele3d = null;
        $this->flash = 'Modèle 3D enregistré.';
    }

    public function supprimerUnMedia(int $mediaId): void
    {
        $media = RentalVehicleMedia::query()->findOrFail($mediaId);

        Storage::disk('public')->delete($media->path);
        $media->delete();

        $this->flash = 'Média supprimé.';
    }

    // ── Les agences ──────────────────────────────────────────────────────

    public function editerLAgence(int $id): void
    {
        $point = RentalPickupPoint::query()->findOrFail($id);

        $this->agenceEnEdition = $point->id;
        $this->agence = [
            'name' => $point->name,
            'address' => $point->address,
            'postal_code' => $point->postal_code,
            'city' => $point->city,
            'country_code' => $point->country_code,
            'lat' => $point->lat,
            'lng' => $point->lng,
            'phone' => $point->phone,
            'instructions' => $point->instructions,
            'sort_order' => $point->sort_order,
        ];
    }

    public function enregistrerLAgence(): void
    {
        $valide = $this->validate([
            'agence.name' => ['required', 'string', 'max:120'],
            'agence.address' => ['required', 'string', 'max:200'],
            'agence.postal_code' => ['nullable', 'string', 'max:16'],
            'agence.city' => ['nullable', 'string', 'max:120'],
            'agence.country_code' => ['required', 'string', 'size:2'],
            'agence.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'agence.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'agence.phone' => ['nullable', 'string', 'max:32'],
            'agence.instructions' => ['nullable', 'string', 'max:2000'],
            'agence.sort_order' => ['nullable', 'integer', 'min:0'],
        ])['agence'];

        $valide['country_code'] = strtoupper((string) $valide['country_code']);

        if ($this->agenceEnEdition !== null) {
            RentalPickupPoint::query()->findOrFail($this->agenceEnEdition)->update($valide);
            $this->flash = 'Agence mise à jour.';
        } else {
            RentalPickupPoint::query()->create($valide + ['is_active' => true]);
            $this->flash = 'Agence créée.';
        }

        $this->reinitialiserLAgence();
    }

    public function basculerLAgence(int $id): void
    {
        $point = RentalPickupPoint::query()->findOrFail($id);
        $point->update(['is_active' => ! $point->is_active]);

        $this->flash = $point->is_active ? 'Agence ouverte.' : 'Agence fermée.';
    }

    public function reinitialiserLAgence(): void
    {
        $this->agenceEnEdition = null;
        $this->agence = [
            'name' => '', 'address' => '', 'postal_code' => '', 'city' => '',
            'country_code' => 'BE', 'lat' => null, 'lng' => null,
            'phone' => '', 'instructions' => '', 'sort_order' => 0,
        ];
        $this->resetValidation();
    }

    // ── Les locations ────────────────────────────────────────────────────

    public function marquerRetiree(int $id): void
    {
        $location = RentalBooking::query()->findOrFail($id);
        $location->forceFill(['status' => RentalBooking::STATUT_RETIREE, 'picked_up_at' => now()])->save();

        $this->flash = 'Location '.$location->reference.' : véhicule remis au client.';
    }

    /** LE RETOUR LIBÈRE LE VÉHICULE. */
    public function marquerRendue(int $id): void
    {
        $location = RentalBooking::query()->findOrFail($id);
        $location->forceFill(['status' => RentalBooking::STATUT_RENDUE, 'returned_at' => now()])->save();

        $this->flash = 'Location '.$location->reference.' : véhicule rendu.';
    }

    public function annulerLaLocation(int $id): void
    {
        try {
            app(RentalBookingService::class)->annuler(RentalBooking::query()->findOrFail($id), 'Annulée depuis l’administration.');
            $this->flash = 'Location annulée.';
        } catch (\Throwable $e) {
            $this->flash = 'Erreur : '.$e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $kpis = [
            'parc' => RentalVehicle::query()->count(),
            'en_vitrine' => RentalVehicle::query()->actif()->count(),
            'locations_en_cours' => RentalBooking::query()->quiBloque()->count(),
            'agences' => RentalPickupPoint::query()->actif()->count(),
        ];

        return view('livewire.admin.rental.nos-locations-center', [
            'kpis' => $kpis,
            'vehicules' => $this->tab === 'parc' || $this->tab === 'medias'
                ? RentalVehicle::query()
                    ->with(['pickupPoint', 'galerie', 'rotation360', 'modele3d'])
                    ->when($this->recherche, fn ($q) => $q->where(fn ($w) => $w
                        ->where('brand', 'like', '%'.$this->recherche.'%')
                        ->orWhere('model', 'like', '%'.$this->recherche.'%')
                        ->orWhere('plate', 'like', '%'.$this->recherche.'%')))
                    ->ordonne()
                    ->paginate(15)
                : null,
            'agences' => RentalPickupPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => $this->tab === 'locations'
                ? RentalBooking::query()
                    ->with('vehicle')
                    ->when($this->filtreStatut, fn ($q) => $q->where('status', $this->filtreStatut))
                    ->orderByDesc('starts_at')
                    ->paginate(20)
                : null,
            'vehiculeMedias' => $this->vehiculePourMedias
                ? RentalVehicle::query()->with(['galerie', 'rotation360', 'modele3d'])->find($this->vehiculePourMedias)
                : null,
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function reglesDuVehicule(): array
    {
        return [
            'fiche.brand' => ['required', 'string', 'max:60'],
            'fiche.model' => ['required', 'string', 'max:60'],
            'fiche.plate' => ['nullable', 'string', 'max:16'],
            'fiche.year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'fiche.color' => ['nullable', 'string', 'max:40'],
            'fiche.category' => ['required', 'string', 'max:32'],
            'fiche.transmission' => ['required', 'in:'.RentalVehicle::TRANSMISSION_MANUELLE.','.RentalVehicle::TRANSMISSION_AUTOMATIQUE],
            'fiche.fuel' => ['required', 'string', 'max:16'],
            'fiche.seats' => ['required', 'integer', 'min:1', 'max:9'],
            'fiche.doors' => ['required', 'integer', 'min:2', 'max:6'],
            'fiche.luggage' => ['required', 'integer', 'min:0', 'max:10'],
            'fiche.features' => ['nullable', 'array'],
            'fiche.daily_price' => ['required', 'numeric', 'min:0'],
            'fiche.deposit' => ['nullable', 'numeric', 'min:0'],
            'fiche.waiver_daily_price' => ['nullable', 'numeric', 'min:0'],
            'fiche.waiver_deposit' => ['nullable', 'numeric', 'min:0'],
            'fiche.included_km_per_day' => ['nullable', 'integer', 'min:0'],
            'fiche.extra_km_price' => ['nullable', 'numeric', 'min:0'],
            'fiche.min_rental_days' => ['required', 'integer', 'min:1', 'max:365'],
            'fiche.max_rental_days' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:fiche.min_rental_days'],
            'fiche.min_driver_age' => ['required', 'integer', 'min:16', 'max:99'],
            'fiche.min_license_years' => ['required', 'integer', 'min:0', 'max:50'],
            'fiche.pickup_point_id' => ['nullable', 'integer', 'exists:rental_pickup_points,id'],
            'fiche.description' => ['nullable', 'string', 'max:4000'],
            'fiche.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    private function libellesDuVehicule(): array
    {
        return [
            'fiche.brand' => 'marque',
            'fiche.model' => 'modèle',
            'fiche.daily_price' => 'prix par jour',
            'fiche.deposit' => 'caution',
            'fiche.waiver_daily_price' => 'supplément garantie',
            'fiche.waiver_deposit' => 'caution avec garantie',
            'fiche.max_rental_days' => 'durée maximale',
        ];
    }

    private function exigerUnVehiculePourMedias(): void
    {
        abort_if($this->vehiculePourMedias === null, 422, 'Aucun véhicule sélectionné.');
    }

    private function effacerLesMedias(RentalVehicle $vehicule, string $type): void
    {
        foreach ($vehicule->media()->where('type', $type)->get() as $media) {
            Storage::disk('public')->delete($media->path);
            $media->delete();
        }
    }

    /** La devise d'un véhicule suit le pays de son agence. Une agence marocaine loue en dirhams. */
    private function deviseDeLAgence(?int $pointId): string
    {
        $point = $pointId ? RentalPickupPoint::query()->find($pointId) : null;

        return DeviseParPays::pour($point?->country_code)
            ?? Devise::plateforme();
    }

    private function enCentimes(mixed $valeur): int
    {
        return (int) round(((float) $valeur) * 100);
    }

    private function enUnites(?int $centimes): string
    {
        return number_format(((int) $centimes) / 100, 2, '.', '');
    }
}
