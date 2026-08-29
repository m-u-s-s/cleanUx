<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerVehicle;
use App\Models\PeerVehicleAvailability;
use App\Models\PeerVehicleDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * L'ANNONCE D'UN VEHICULE.
 *
 * Publier n'est pas un bouton : il faut des photos, des papiers valides, une adresse et un
 * prix. `motifsDeBlocage` les dit tous d'un coup, plutot que de refuser une fois par cause.
 */
#[Layout('layouts.app')]
class PeerVehicleEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $vehiculeId;

    /** @var array<string, mixed> */
    public array $champs = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $photosAAjouter = [];

    public ?string $typeDocument = PeerVehicleDocument::TYPE_CARTE_GRISE;

    public ?TemporaryUploadedFile $fichierDocument = null;

    public string $expirationDocument = '';

    public string $fermetureDebut = '';

    public string $fermetureFin = '';

    public string $fermetureMotif = '';

    public ?string $message = null;

    public ?string $erreur = null;

    public function mount(PeerVehicle $vehicle): void
    {
        abort_unless($vehicle->owner_id === auth()->id(), 403);

        $this->vehiculeId = $vehicle->id;

        $this->champs = $vehicle->only([
            'brand', 'model', 'year', 'color', 'plate', 'category', 'transmission', 'fuel',
            'seats', 'doors', 'luggage', 'description',
            'daily_price_cents', 'deposit_cents', 'included_km_per_day', 'extra_km_price_cents',
            'discount_3_days_percent', 'discount_7_days_percent', 'discount_28_days_percent',
            'min_rental_days', 'max_rental_days', 'min_driver_age', 'min_license_years',
            'instant_booking', 'address_line', 'postal_code', 'city', 'country_code',
            'delivery_enabled', 'delivery_radius_km', 'delivery_price_cents', 'cancellation_policy',
        ]);

        // Les montants se saisissent en euros, pas en centimes : le stockage reste en centimes.
        foreach (['daily_price_cents', 'deposit_cents', 'extra_km_price_cents', 'delivery_price_cents'] as $cle) {
            $this->champs[$cle] = round(((int) $this->champs[$cle]) / 100, 2);
        }
    }

    #[Computed]
    public function vehicule(): PeerVehicle
    {
        return PeerVehicle::query()->with(['media', 'documents', 'availability'])->findOrFail($this->vehiculeId);
    }

    /**
     * CE QUI EMPECHE LA PUBLICATION — tout, d'un coup.
     *
     * @return list<string>
     */
    #[Computed]
    public function motifsDeBlocage(): array
    {
        $vehicule = $this->vehicule();
        $motifs = [];

        if (trim($vehicule->brand) === '' || trim($vehicule->model) === '' || trim($vehicule->plate) === '') {
            $motifs[] = __('La marque, le modèle et la plaque sont obligatoires.');
        }

        if ($vehicule->media->isEmpty()) {
            $motifs[] = __('Ajoutez au moins une photo.');
        }

        if (trim((string) $vehicule->city) === '') {
            $motifs[] = __('Indiquez où le véhicule se trouve.');
        }

        if ($vehicule->daily_price_cents <= 0) {
            $motifs[] = __('Fixez un prix par jour.');
        }

        foreach (PeerVehicleDocument::TYPES_REQUIS as $type) {
            $valide = $vehicule->documents
                ->where('document_type', $type)
                ->contains(fn (PeerVehicleDocument $d): bool => $d->estValide());

            if (! $valide) {
                $motifs[] = $type === PeerVehicleDocument::TYPE_CARTE_GRISE
                    ? __('La carte grise doit être validée.')
                    : __('L’attestation d’assurance doit être validée.');
            }
        }

        if (! auth()->user()?->canReceiveStripeConnectPayments()) {
            $motifs[] = __('Terminez votre inscription au paiement pour être réglé.');
        }

        return $motifs;
    }

    public function enregistrer(): void
    {
        $this->erreur = null;

        $this->validate([
            'champs.brand' => ['required', 'string', 'max:60'],
            'champs.model' => ['required', 'string', 'max:60'],
            'champs.year' => ['required', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'champs.plate' => ['required', 'string', 'max:20'],
            'champs.category' => ['required', 'string', 'max:30'],
            'champs.transmission' => ['required', 'in:manuelle,automatique'],
            'champs.fuel' => ['required', 'string', 'max:20'],
            'champs.seats' => ['required', 'integer', 'min:1', 'max:9'],
            'champs.daily_price_cents' => ['required', 'numeric', 'min:1'],
            'champs.deposit_cents' => ['required', 'numeric', 'min:0'],
            'champs.city' => ['required', 'string', 'max:120'],
            'champs.min_rental_days' => ['required', 'integer', 'min:1', 'max:365'],
            'champs.max_rental_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $valeurs = $this->champs;

        foreach (['daily_price_cents', 'deposit_cents', 'extra_km_price_cents', 'delivery_price_cents'] as $cle) {
            $valeurs[$cle] = (int) round(((float) ($valeurs[$cle] ?? 0)) * 100);
        }

        $this->vehicule()->fill($valeurs)->save();

        $this->message = __('Annonce enregistrée.');
        unset($this->vehicule, $this->motifsDeBlocage);
    }

    public function ajouterDesPhotos(): void
    {
        $this->validate(['photosAAjouter.*' => ['image', 'max:8192']]);

        $vehicule = $this->vehicule();
        $ordre = (int) $vehicule->media()->max('sort_order');

        foreach ($this->photosAAjouter as $fichier) {
            $chemin = $fichier->store('peer-vehicles/'.$vehicule->reference, 'public');

            $vehicule->media()->create([
                'path' => $chemin,
                'sort_order' => ++$ordre,
                // La premiere photo devient la couverture : une annonce sans vignette
                // n'apparait nulle part dans la recherche.
                'is_cover' => $vehicule->media()->count() === 0,
                'sha256' => hash_file('sha256', Storage::disk('public')->path($chemin)) ?: null,
            ]);
        }

        $this->photosAAjouter = [];
        $this->message = __('Photos ajoutées.');
        unset($this->vehicule, $this->motifsDeBlocage);
    }

    public function definirLaCouverture(int $mediaId): void
    {
        $vehicule = $this->vehicule();

        $vehicule->media()->update(['is_cover' => false]);
        $vehicule->media()->whereKey($mediaId)->update(['is_cover' => true]);

        unset($this->vehicule);
    }

    public function supprimerUnePhoto(int $mediaId): void
    {
        $photo = $this->vehicule()->media()->findOrFail($mediaId);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        unset($this->vehicule, $this->motifsDeBlocage);
    }

    public function deposerUnDocument(): void
    {
        $this->validate([
            'fichierDocument' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png'],
            'typeDocument' => ['required', 'string', 'max:40'],
            'expirationDocument' => ['nullable', 'date'],
        ]);

        $vehicule = $this->vehicule();
        $chemin = $this->fichierDocument->store('peer-documents/'.$vehicule->reference, 'local');

        $vehicule->documents()->create([
            'document_type' => $this->typeDocument,
            'status' => PeerVehicleDocument::STATUT_EN_REVUE,
            'file_path' => $chemin,
            'file_name' => $this->fichierDocument->getClientOriginalName(),
            'mime_type' => $this->fichierDocument->getMimeType(),
            'file_size' => $this->fichierDocument->getSize(),
            'expires_at' => $this->expirationDocument === '' ? null : $this->expirationDocument,
        ]);

        $this->fichierDocument = null;
        $this->expirationDocument = '';
        $this->message = __('Document déposé. Un administrateur le vérifie.');
        unset($this->vehicule, $this->motifsDeBlocage);
    }

    public function fermerUnePeriode(): void
    {
        $this->validate([
            'fermetureDebut' => ['required', 'date'],
            'fermetureFin' => ['required', 'date', 'after_or_equal:fermetureDebut'],
        ]);

        $this->vehicule()->availability()->create([
            'starts_on' => $this->fermetureDebut,
            'ends_on' => $this->fermetureFin,
            'kind' => PeerVehicleAvailability::FERMEE,
            'reason' => $this->fermetureMotif === '' ? null : $this->fermetureMotif,
        ]);

        $this->fermetureDebut = '';
        $this->fermetureFin = '';
        $this->fermetureMotif = '';
        $this->message = __('Période fermée.');
        unset($this->vehicule);
    }

    public function rouvrirUnePeriode(int $periodeId): void
    {
        $this->vehicule()->availability()->whereKey($periodeId)->delete();

        unset($this->vehicule);
    }

    /** PUBLIER — la demande part en revue, elle ne publie pas d'elle-meme. */
    public function demanderLaPublication(): void
    {
        $this->erreur = null;

        if ($this->motifsDeBlocage() !== []) {
            $this->erreur = __('Complétez d’abord ce qui manque.');

            return;
        }

        $this->vehicule()->forceFill(['status' => PeerVehicle::STATUT_EN_REVUE])->save();

        $this->message = __('Annonce envoyée en vérification.');
        unset($this->vehicule);
    }

    public function mettreEnPause(): void
    {
        $this->vehicule()->forceFill(['status' => PeerVehicle::STATUT_EN_PAUSE])->save();

        $this->message = __('Annonce mise en pause. Les locations en cours continuent.');
        unset($this->vehicule);
    }

    public function reprendre(): void
    {
        $vehicule = $this->vehicule();

        if ($this->motifsDeBlocage() !== []) {
            $this->erreur = __('Complétez d’abord ce qui manque.');

            return;
        }

        $vehicule->forceFill([
            'status' => PeerVehicle::STATUT_PUBLIE,
            'published_at' => $vehicule->published_at ?? now(),
        ])->save();

        $this->message = __('Annonce de nouveau visible.');
        unset($this->vehicule);
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-vehicle-editor');
    }
}
